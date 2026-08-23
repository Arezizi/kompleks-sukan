<?php

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\EquipmentType;
use App\Models\Loan;
use App\Models\Reservation;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Check out gear')] class extends Component {
    /**
     * Reservation pickup code entered by staff (or prefilled from a scanned QR).
     */
    #[Url]
    public string $code = '';

    /**
     * Reservation matched by the code, ready for confirmation.
     */
    #[Locked]
    public ?int $foundReservationId = null;

    /**
     * Accessory quantities staff added to a reservation handout.
     *
     * @var array<int, int>
     */
    public array $extras = [];

    /**
     * Walk-in student lookup.
     */
    public string $matric = '';

    public ?int $walkInStudentId = null;

    /**
     * Quantities per equipment type for a walk-in loan.
     *
     * @var array<int, int>
     */
    public array $quantities = [];

    public bool $cardCollected = true;

    /**
     * The loan just issued — shown with its return QR after checkout.
     */
    #[Locked]
    public ?int $issuedLoanId = null;

    /**
     * Whether the QR scanner modal is open.
     */
    public bool $showScanner = false;

    public function mount(): void
    {
        if ($this->code !== '') {
            $this->findReservation();
        }
    }

    /**
     * Look up an approved reservation by its pickup code.
     */
    public function findReservation(): void
    {
        $this->validate(['code' => ['required', 'string']], [], ['code' => __('pickup code')]);

        $this->reset('foundReservationId', 'walkInStudentId', 'extras', 'issuedLoanId');

        /** @var Reservation|null $reservation */
        $reservation = Reservation::query()
            ->where('token', trim($this->code))
            ->with('items.equipmentType')
            ->first();

        if ($reservation === null || $reservation->status !== ReservationStatus::Approved) {
            $this->addError('code', __('No approved reservation matches this code.'));

            return;
        }

        $this->foundReservationId = $reservation->id;
    }

    /**
     * Accessories suggested for the found reservation's gear.
     *
     * @return Collection<int, array{accessory: EquipmentType, prompt: string}>
     */
    #[Computed]
    public function pickupAccessories(): Collection
    {
        if ($this->foundReservationId === null) {
            return collect();
        }

        /** @var Reservation|null $reservation */
        $reservation = Reservation::query()
            ->with('items.equipmentType.suggestedAccessories')
            ->find($this->foundReservationId);

        if ($reservation === null) {
            return collect();
        }

        $reservedTypeIds = $reservation->items->pluck('equipment_type_id');

        return $reservation->items
            ->flatMap(fn ($item) => $item->equipmentType->suggestedAccessories
                ->map(fn (EquipmentType $accessory): array => [
                    'accessory' => $accessory,
                    'prompt' => filled($accessory->pivot->prompt ?? null)
                        ? $accessory->pivot->prompt
                        : __('Add to this handout?'),
                ]))
            ->reject(fn (array $row) => $reservedTypeIds->contains($row['accessory']->id))
            ->unique(fn (array $row) => $row['accessory']->id)
            ->values();
    }

    /**
     * Toggle an optional accessory on the reservation handout.
     */
    public function toggleExtra(int $typeId): void
    {
        if (array_key_exists($typeId, $this->extras)) {
            unset($this->extras[$typeId]);
        } else {
            $this->extras[$typeId] = 1;
        }
    }

    /**
     * Confirm handing over gear for the found reservation.
     */
    public function confirmPickup(): void
    {
        $service = app(LoanService::class);

        $loan = $service->checkoutFromReservation(trim($this->code), auth()->user(), $this->cardCollected, $this->extras);

        $this->reset('code', 'foundReservationId', 'extras', 'cardCollected');
        $this->issuedLoanId = $loan->id;

        session()->flash('status', __('Checked out to :name — due by :time.', [
            'name' => $loan->user->name,
            'time' => $loan->due_at->format('g:i A'),
        ]));
    }

    /**
     * Find the walk-in student by matric number or email.
     */
    public function findStudent(): void
    {
        $this->validate(['matric' => ['required', 'string']], [], ['matric' => __('matric number')]);

        $this->reset('walkInStudentId', 'quantities', 'foundReservationId', 'issuedLoanId');

        $identifier = str(trim($this->matric))->lower();

        /** @var User|null $student */
        $student = User::query()
            ->whereRaw('lower(matric_no) = ?', [$identifier])
            ->orWhereRaw('lower(email) = ?', [$identifier])
            ->first();

        if ($student === null || $student->role !== UserRole::Student) {
            $this->addError('matric', __('No student account matches that matric number.'));

            return;
        }

        $this->walkInStudentId = $student->id;
    }

    /**
     * Toggle an optional suggested accessory on the walk-in loan.
     */
    public function toggleAccessory(int $typeId): void
    {
        if (array_key_exists($typeId, $this->quantities)) {
            unset($this->quantities[$typeId]);
        } else {
            $this->quantities[$typeId] = 1;
        }
    }

    /**
     * Accessories suggested by the gear already chosen for the walk-in loan.
     *
     * @return Collection<int, array{accessory: EquipmentType, prompt: string}>
     */
    #[Computed]
    public function walkInAccessories(): Collection
    {
        $selectedIds = collect($this->quantities)
            ->filter(fn ($quantity): bool => (int) $quantity > 0)
            ->keys()
            ->map(fn ($id): int => (int) $id);

        if ($selectedIds->isEmpty()) {
            return collect();
        }

        /** @var Collection<int, EquipmentType> $types */
        $types = EquipmentType::query()->with('suggestedAccessories')->findMany($selectedIds);

        return $types
            ->flatMap(fn (EquipmentType $type) => $type->suggestedAccessories
                ->map(fn (EquipmentType $accessory): array => [
                    'accessory' => $accessory,
                    'prompt' => filled($accessory->pivot->prompt ?? null)
                        ? $accessory->pivot->prompt
                        : __('Add to this handout?'),
                ]))
            ->reject(fn (array $row) => $selectedIds->contains($row['accessory']->id))
            ->unique(fn (array $row) => $row['accessory']->id)
            ->values();
    }

    /**
     * Create the walk-in loan.
     */
    public function confirmWalkIn(): void
    {
        $loan = app(LoanService::class)->walkInCheckout(
            User::findOrFail($this->walkInStudentId),
            $this->quantities,
            auth()->user(),
            $this->cardCollected,
        );

        $this->reset('matric', 'walkInStudentId', 'quantities', 'cardCollected');
        $this->issuedLoanId = $loan->id;

        session()->flash('status', __('Checked out to :name — due by :time.', [
            'name' => $loan->user->name,
            'time' => $loan->due_at->format('g:i A'),
        ]));
    }
}; ?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Check out gear') }}</flux:heading>
        <flux:text class="mt-2">
            {{ __('Hand out equipment against a reservation code, or create a walk-in loan on the spot.') }}
        </flux:text>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" data-test="flash-status">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    @if ($errors->has('checkout'))
        <flux:callout variant="danger" icon="exclamation-triangle" data-test="checkout-error">
            <flux:callout.text>{{ $errors->first('checkout') }}</flux:callout.text>
        </flux:callout>
    @endif

    @if ($issuedLoanId !== null)
        @php
            $issuedLoan = Loan::query()->with('items.equipmentType')->find($issuedLoanId);
        @endphp

        @if ($issuedLoan !== null && $issuedLoan->token !== null)
            <div class="flex flex-col gap-4 rounded-2xl border border-emerald-500/30 bg-emerald-500/5 p-5 sm:flex-row sm:items-center" data-test="loan-qr">
                <div class="grid w-32 shrink-0 self-center place-items-center rounded-xl bg-white p-2 ring-1 ring-zinc-200">
                    {!! App\Services\QrCodeService::svg(App\Services\QrCodeService::loanReturnUrl($issuedLoan->token), 100) !!}
                </div>
                <div class="min-w-0 space-y-1">
                    <flux:heading>{{ __('Hand this return QR to :name', ['name' => $issuedLoan->user->name]) }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Scanning it at the returns desk pulls up this loan automatically.') }}
                        {{ __('Due by :time.', ['time' => $issuedLoan->due_at->format('g:i A')]) }}
                    </flux:text>
                </div>
            </div>
        @endif
    @endif

    <div class="grid gap-6 xl:grid-cols-2">
        {{-- Reservation pickup --}}
        <div class="space-y-4 rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900" data-test="pickup-panel">
            <flux:heading>{{ __('1 · Reservation pickup') }}</flux:heading>

            <form wire:submit="findReservation" class="flex flex-wrap items-end gap-2">
                <div class="min-w-0 flex-1">
                    <flux:input wire:model="code" :label="__('Pickup code')" :placeholder="__('Paste, type or scan the student’s code')" data-test="pickup-code-input" />
                </div>
                <flux:button variant="subtle" type="submit" class="mb-[3px]" data-test="find-reservation">{{ __('Find') }}</flux:button>
                <flux:button variant="subtle" icon="qr-code" class="mb-[3px]" wire:click="$set('showScanner', true)" data-test="open-scanner">
                    {{ __('Scan') }}
                </flux:button>
            </form>

            @if ($foundReservationId !== null)
                @php
                    $reservation = Reservation::find($foundReservationId);
                @endphp

                @if ($reservation !== null)
                    <div class="space-y-3 rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-4" data-test="pickup-preview">
                        <div>
                            <p class="text-sm font-semibold">{{ $reservation->user->name }} <span class="font-normal text-zinc-500">({{ $reservation->user->matric_no ?? '—' }})</span></p>
                            <ul class="mt-1 space-y-0.5 text-xs text-zinc-600 dark:text-zinc-300">
                                @foreach ($reservation->items as $item)
                                    <li>{{ $item->quantity }}× {{ $item->equipmentType->name }}</li>
                                @endforeach
                            </ul>
                        </div>

                        @if ($this->pickupAccessories()->isNotEmpty())
                            <div class="space-y-2 rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900" data-test="accessory-suggestions">
                                <p class="text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">
                                    {{ __('Suggested accessories') }}
                                </p>
                                @foreach ($this->pickupAccessories() as $row)
                                    <button type="button" wire:click="toggleExtra({{ $row['accessory']->id }})"
                                        class="flex w-full items-start gap-2 text-left text-sm"
                                        data-test="accessory-toggle"
                                    >
                                        <span class="mt-0.5 grid size-4 shrink-0 place-items-center rounded border {{ isset($extras[$row['accessory']->id]) ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-zinc-300 bg-white dark:border-zinc-600 dark:bg-zinc-900' }}">
                                            @if (isset($extras[$row['accessory']->id]))
                                                <flux:icon icon="check" variant="mini" class="size-3" />
                                            @endif
                                        </span>
                                        <span class="flex-1">
                                            <span class="font-medium">{{ $row['accessory']->name }}</span>
                                            <span class="block text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ $row['prompt'] }} · {{ __('Free right now') }}: {{ $row['accessory']->availableQuantity() }}
                                            </span>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="cardCollected" class="accent-indigo-600" />
                            {{ __('Student card collected as deposit') }}
                        </label>

                        <flux:button variant="primary" wire:click="confirmPickup" data-test="confirm-pickup">
                            {{ __('Hand out & start loan') }}
                        </flux:button>
                    </div>
                @endif
            @endif
        </div>

        {{-- Walk-in --}}
        <div class="space-y-4 rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900" data-test="walkin-panel">
            <flux:heading>{{ __('2 · Walk-in loan') }}</flux:heading>

            <form wire:submit="findStudent" class="flex items-end gap-2">
                <div class="flex-1">
                    <flux:input wire:model="matric" :label="__('Matric no / email')" :placeholder="__('PMS123456')" data-test="walkin-matric" />
                </div>
                <flux:button variant="subtle" type="submit" class="mb-[3px]" data-test="find-student">{{ __('Find') }}</flux:button>
            </form>

            @if ($errors->has('matric'))
                <p class="text-sm text-red-600 dark:text-red-400" data-test="matric-error">{{ $errors->first('matric') }}</p>
            @endif

            @if ($walkInStudentId !== null)
                @php
                    $student = User::find($walkInStudentId);
                    $activeCount = Loan::query()->where('user_id', $walkInStudentId)->active()->count();
                    $types = EquipmentType::query()->where('is_active', true)->orderBy('name')->get();
                @endphp

                <div class="space-y-3" data-test="walkin-form">
                    <p class="text-sm font-semibold">{{ $student->name }} <span class="font-normal text-zinc-500">· {{ __('active loans') }}: {{ $activeCount }}</span></p>

                    <div class="grid max-h-64 gap-2 overflow-y-auto pr-1">
                        @foreach ($types as $type)
                            <label class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700" wire:key="wt-{{ $type->id }}">
                                <span class="min-w-0 truncate">{{ $type->name }}</span>
                                <input type="number" min="0" :max="$type->availableQuantity()" wire:model.live.debounce.300ms="quantities.{{ $type->id }}" class="w-16 rounded-md border border-zinc-300 bg-white px-2 py-1 text-end tabular-nums dark:border-zinc-600 dark:bg-zinc-800" />
                            </label>
                        @endforeach
                    </div>

                    @if ($this->walkInAccessories()->isNotEmpty())
                        <div class="space-y-2 rounded-xl border border-indigo-500/30 bg-indigo-500/5 p-3" data-test="accessory-suggestions">
                            <p class="text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">
                                {{ __('Suggested accessories') }}
                            </p>
                            @foreach ($this->walkInAccessories() as $row)
                                <button type="button" wire:click="toggleAccessory({{ $row['accessory']->id }})"
                                    class="flex w-full items-start gap-2 text-left text-sm"
                                    data-test="accessory-toggle"
                                >
                                    <span class="mt-0.5 grid size-4 shrink-0 place-items-center rounded border {{ isset($quantities[$row['accessory']->id]) ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-zinc-300 bg-white dark:border-zinc-600 dark:bg-zinc-900' }}">
                                        @if (isset($quantities[$row['accessory']->id]))
                                            <flux:icon icon="check" variant="mini" class="size-3" />
                                        @endif
                                    </span>
                                    <span class="flex-1">
                                        <span class="font-medium">{{ $row['accessory']->name }}</span>
                                        <span class="block text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $row['prompt'] }} · {{ __('Free right now') }}: {{ $row['accessory']->availableQuantity() }}
                                        </span>
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="cardCollected" class="accent-indigo-600" />
                        {{ __('Student card collected as deposit') }}
                    </label>

                    <flux:button variant="primary" wire:click="confirmWalkIn" data-test="confirm-walkin">
                        {{ __('Create walk-in loan') }}
                    </flux:button>
                </div>
            @endif
        </div>
    </div>

    <x-qr-scanner name="checkout-scanner" target="code" action="findReservation" />
</section>
