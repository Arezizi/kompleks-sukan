<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Mail\ReservationApprovedMail;
use App\Models\EquipmentType;
use App\Models\Reservation;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates and manages gear reservations.
 *
 * Lifecycle: Pending -> Approved (staff, with pickup deadline) -> Fulfilled at
 * the counter; or Rejected / Cancelled / Expired. Pending and Approved
 * reservations hold stock.
 */
final class ReservationService
{
    /**
     * How long an approved reservation waits at the counter before expiring.
     */
    public const PICKUP_WINDOW_HOURS = 24;

    public function __construct(private PenaltyService $penalties)
    {
        //
    }

    /**
     * Create a pending reservation for the given student.
     *
     * @param  array<int|string, mixed>  $quantities  Map of equipment type id to requested quantity.
     * @return Reservation The created reservation with its item lines attached.
     *
     * @throws ValidationException when borrowing is blocked or stock is insufficient.
     */
    public function create(User $student, array $quantities, ?string $note = null): Reservation
    {
        $settings = SiteSetting::current();

        if ($this->penalties->blocks($student)) {
            throw ValidationException::withMessages([
                'reservation' => __('Your account has an active penalty. Visit the sports counter for help.'),
            ]);
        }

        $lines = $this->normalizeLines($quantities);

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'reservation' => __('Pick at least one piece of equipment.'),
            ]);
        }

        $openReservations = Reservation::query()
            ->where('user_id', $student->getKey())
            ->holdingStock()
            ->count();

        if ($openReservations >= $settings->max_active_loans) {
            throw ValidationException::withMessages([
                'reservation' => __('You already have :n open reservations. Cancel one or collect it first.', ['n' => $settings->max_active_loans]),
            ]);
        }

        /** @var Collection<int, EquipmentType> $types */
        $types = EquipmentType::query()
            ->whereIn('id', $lines->keys())
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        foreach ($lines as $typeId => $quantity) {
            $type = $types->get($typeId);

            if ($type === null) {
                throw ValidationException::withMessages([
                    'reservation' => __('One of the selected items is no longer available.'),
                ]);
            }

            $available = $type->availableQuantity();

            if ($quantity > $available) {
                throw ValidationException::withMessages([
                    'reservation' => __('Only :n of ":name" available right now.', [
                        'n' => $available,
                        'name' => $type->name,
                    ]),
                ]);
            }
        }

        return DB::transaction(function () use ($student, $lines, $note): Reservation {
            /** @var Reservation $reservation */
            $reservation = $student->reservations()->create([
                'status' => ReservationStatus::Pending,
                'token' => Str::random(40),
                'note' => $note,
            ]);

            $reservation->items()->createMany(
                $lines->map(fn (int $quantity, int $typeId): array => [
                    'equipment_type_id' => $typeId,
                    'quantity' => $quantity,
                ])->values()->all(),
            );

            return $reservation->refresh();
        });
    }

    /**
     * Cancel a reservation on behalf of its student (pending or approved only).
     */
    public function cancel(Reservation $reservation): void
    {
        if (! in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Approved], true)) {
            throw ValidationException::withMessages([
                'reservation' => __('Only waiting or ready reservations can be cancelled.'),
            ]);
        }

        $reservation->forceFill(['status' => ReservationStatus::Cancelled])->save();
    }

    /**
     * Approve or reject a pending reservation as staff.
     */
    public function decide(Reservation $reservation, User $staff, bool $approve, ?string $note = null): void
    {
        if ($reservation->status !== ReservationStatus::Pending) {
            throw ValidationException::withMessages([
                'reservation' => __('This request has already been handled.'),
            ]);
        }

        if ($approve) {
            foreach ($reservation->items as $item) {
                $available = $item->equipmentType->availableQuantity($reservation->getKey());

                if ($item->quantity > $available) {
                    throw ValidationException::withMessages([
                        'reservation' => __('Cannot approve — only :n of ":name" available. Reject instead.', [
                            'n' => $available,
                            'name' => $item->equipmentType->name,
                        ]),
                    ]);
                }
            }
        }

        $reservation->forceFill([
            'status' => $approve ? ReservationStatus::Approved : ReservationStatus::Rejected,
            'decided_by' => $staff->getKey(),
            'decision_note' => $note,
            'pickup_deadline' => $approve ? now()->addHours(self::PICKUP_WINDOW_HOURS) : null,
        ])->save();

        if ($approve) {
            Mail::to($reservation->user->email)
                ->queue(new ReservationApprovedMail($reservation));
        }
    }

    /**
     * Expire approved reservations whose pickup window lapsed.
     *
     * @return int Number of reservations expired.
     */
    public function expireStale(): int
    {
        return Reservation::query()
            ->where('status', ReservationStatus::Approved)
            ->whereNotNull('pickup_deadline')
            ->where('pickup_deadline', '<', now())
            ->update(['status' => ReservationStatus::Expired->value]);
    }

    /**
     * Keep only positive integer quantities keyed by equipment type id.
     *
     * @param  array<int|string, mixed>  $quantities
     * @return Collection<int, positive-int>
     */
    private function normalizeLines(array $quantities): Collection
    {
        return collect($quantities)
            ->mapWithKeys(fn ($quantity, $typeId): array => [(int) $typeId => (int) $quantity])
            ->filter(fn (int $quantity): bool => $quantity > 0);
    }
}
