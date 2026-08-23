<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Mail\OverduePenaltyMail;
use App\Mail\ReturnConfirmedMail;
use App\Models\EquipmentType;
use App\Models\Loan;
use App\Models\Reservation;
use App\Models\SiteSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Counter operations: checking gear out and receiving returns.
 */
final class LoanService
{
    public function __construct(private PenaltyService $penalties)
    {
        //
    }

    /**
     * Check gear out against an approved reservation (QR pickup).
     *
     * @param  array<int|string, mixed>  $extras  Accessory quantities staff added at the counter.
     *
     * @throws ValidationException when pickup is not possible.
     */
    public function checkoutFromReservation(string $token, User $staff, bool $cardCollected = true, array $extras = []): Loan
    {
        /** @var Reservation|null $reservation */
        $reservation = Reservation::query()
            ->where('token', $token)
            ->with('items.equipmentType')
            ->first();

        if ($reservation === null) {
            throw ValidationException::withMessages([
                'checkout' => __('No reservation matches this code.'),
            ]);
        }

        if ($reservation->status !== ReservationStatus::Approved) {
            throw ValidationException::withMessages([
                'checkout' => __('This reservation is not ready for pickup (:status).', [
                    'status' => $reservation->status->label(),
                ]),
            ]);
        }

        $lines = $reservation->items
            ->mapWithKeys(fn ($item): array => [$item->equipment_type_id => $item->quantity]);

        foreach (collect($extras)
            ->mapWithKeys(fn ($quantity, $typeId): array => [(int) $typeId => (int) $quantity])
            ->filter(fn (int $quantity): bool => $quantity > 0) as $typeId => $extraQuantity) {
            $lines[$typeId] = ((int) ($lines[$typeId] ?? 0)) + $extraQuantity;
        }

        return DB::transaction(function () use ($reservation, $lines, $staff, $cardCollected): Loan {
            // Re-check stock inside the transaction, ignoring this reservation's own hold.
            foreach ($lines as $typeId => $quantity) {
                $this->assertAvailable((int) $typeId, (int) $quantity, $reservation->getKey());
            }

            $loan = $this->createLoan($reservation->user, $lines, $staff, $cardCollected, $reservation);

            $reservation->forceFill(['status' => ReservationStatus::Fulfilled])->save();

            return $loan;
        });
    }

    /**
     * Check gear out for a walk-in student without a reservation.
     *
     * @param  array<int|string, mixed>  $quantities  Map of equipment type id to quantity.
     *
     * @throws ValidationException when the student cannot borrow or stock is insufficient.
     */
    public function walkInCheckout(User $student, array $quantities, User $staff, bool $cardCollected = true): Loan
    {
        $lines = collect($quantities)
            ->mapWithKeys(fn ($quantity, $typeId): array => [(int) $typeId => (int) $quantity])
            ->filter(fn (int $quantity): bool => $quantity > 0);

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'checkout' => __('Pick at least one piece of equipment.'),
            ]);
        }

        return DB::transaction(function () use ($student, $lines, $staff, $cardCollected): Loan {
            foreach ($lines as $typeId => $quantity) {
                $this->assertAvailable($typeId, $quantity);
            }

            return $this->createLoan($student, $lines, $staff, $cardCollected, null);
        });
    }

    /**
     * Receive a return at the counter and penalise late arrivals.
     *
     * @throws ValidationException when the loan is already returned.
     */
    public function returnLoan(Loan $loan, User $staff, ?string $conditionNote = null, bool $cardReturned = true): Loan
    {
        if ($loan->returned_at !== null) {
            throw ValidationException::withMessages([
                'return' => __('This loan has already been returned.'),
            ]);
        }

        return DB::transaction(function () use ($loan, $staff, $conditionNote, $cardReturned): Loan {
            $loan->forceFill([
                'returned_at' => now(),
                'returned_to' => $staff->getKey(),
                'card_returned' => $cardReturned,
                'condition_note' => $conditionNote,
            ])->save();

            if ($loan->returned_at !== null && $loan->due_at->lessThan($loan->returned_at)) {
                if (! $loan->penalties()->exists()) {
                    $penalty = $this->penalties->imposeLateReturn($loan);
                    Mail::to($loan->user->email)
                        ->queue(new OverduePenaltyMail($loan, $penalty));
                }
            }

            Mail::to($loan->user->email)
                ->queue(new ReturnConfirmedMail($loan));

            return $loan->refresh();
        });
    }

    /**
     * The latest a loan taken out right now can come back: the shorter of the
     * standard borrowing window and today's closing time.
     */
    public function computeDueAt(): CarbonImmutable
    {
        $settings = SiteSetting::current();
        $now = now();

        $dueAt = $now->copy()->addMinutes($settings->max_loan_minutes);

        /** @var CarbonImmutable $closing */
        $closing = CarbonImmutable::createFromFormat('H:i:s', $settings->closing_time, $now->getTimezone())
            ->setDateFrom($now);

        if ($closing->greaterThan($now) && $dueAt->greaterThan($closing)) {
            $dueAt = $closing;
        }

        return $dueAt;
    }

    /**
     * Guard that the given student may take another loan.
     *
     * @throws ValidationException when blocked by penalty or active-loan limit.
     */
    private function assertMayBorrow(User $student): void
    {
        if ($this->penalties->blocks($student)) {
            throw ValidationException::withMessages([
                'checkout' => __(':name has an active penalty and cannot borrow.', ['name' => $student->name]),
            ]);
        }

        $activeLoans = Loan::query()
            ->where('user_id', $student->getKey())
            ->active()
            ->count();

        $limit = SiteSetting::current()->max_active_loans;

        if ($activeLoans >= $limit) {
            throw ValidationException::withMessages([
                'checkout' => __(':name already has :n active loans (limit :limit).', [
                    'name' => $student->name,
                    'n' => $activeLoans,
                    'limit' => $limit,
                ]),
            ]);
        }
    }

    /**
     * Guard that enough units of a type are free right now.
     */
    private function assertAvailable(int $typeId, int $quantity, ?int $exceptReservationId = null): void
    {
        /** @var EquipmentType|null $type */
        $type = EquipmentType::query()->find($typeId);

        if ($type === null || ! $type->is_active) {
            throw ValidationException::withMessages([
                'checkout' => __('One of the selected items is no longer available.'),
            ]);
        }

        $available = $type->availableQuantity($exceptReservationId);

        if ($quantity > $available) {
            throw ValidationException::withMessages([
                'checkout' => __('Only :n of ":name" available right now.', [
                    'n' => $available,
                    'name' => $type->name,
                ]),
            ]);
        }
    }

    /**
     * Persist the loan with its item lines.
     *
     * @param  Collection<int, positive-int>  $lines
     */
    private function createLoan(User $student, Collection $lines, User $staff, bool $cardCollected, ?Reservation $reservation): Loan
    {
        $this->assertMayBorrow($student);

        /** @var Loan $loan */
        $loan = Loan::query()->create([
            'token' => Str::random(40),
            'user_id' => $student->getKey(),
            'reservation_id' => $reservation?->getKey(),
            'issued_by' => $staff->getKey(),
            'checked_out_at' => now(),
            'due_at' => $this->computeDueAt(),
            'card_collected' => $cardCollected,
        ]);

        $loan->items()->createMany(
            $lines->map(fn (int $quantity, int $typeId): array => [
                'equipment_type_id' => $typeId,
                'quantity' => $quantity,
            ])->values()->all(),
        );

        return $loan->refresh();
    }
}
