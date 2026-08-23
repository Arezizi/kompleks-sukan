<?php

use App\Models\EquipmentType;
use App\Services\ReservationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::public')] #[Title('Gear catalog')] class extends Component {
    /**
     * Requested quantity per equipment type id inside the reserve modal.
     *
     * @var array<int, int>
     */
    public array $quantities = [];

    /**
     * The equipment type currently being reserved.
     */
    #[Locked]
    public ?int $reserveType = null;

    /**
     * Whether the reserve modal is visible.
     */
    public bool $showReserve = false;

    /**
     * Equipment type id arriving via ?reserve= — deep links (e.g. from a
     * facility page) open the reserve modal straight away.
     */
    #[Url]
    public ?int $reserve = null;

    /**
     * Optionally open the reserve modal for an item linked in the URL.
     */
    public function mount(): void
    {
        if ($this->reserve === null) {
            return;
        }

        $exists = EquipmentType::query()
            ->whereKey($this->reserve)
            ->where('is_active', true)
            ->exists();

        $target = $this->reserve;
        $this->reserve = null;

        if ($exists) {
            $this->startReserve($target);
        }
    }

    /**
     * Open the reserve modal for a given equipment type.
     */
    public function startReserve(int $typeId): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login', absolute: false);

            return;
        }

        $this->reserveType = $typeId;
        $this->quantities = [$typeId => 1];
        $this->resetValidation();
        $this->showReserve = true;
    }

    /**
     * Toggle an optional suggested accessory on or off.
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
     * Create the reservation request.
     */
    public function submitReservation(): void
    {
        /** @var ReservationService $service */
        $service = app(ReservationService::class);
        $service->create(Auth::user(), $this->quantities);

        $this->showReserve = false;

        session()->flash('status', __('Reservation requested! Track it under My bookings.'));

        $this->redirectRoute('reservations.index', absolute: false, navigate: true);
    }
}; ?>

@php
    $gear = EquipmentType::query()->where('is_active', true)->orderBy('name')->with('suggestedAccessories')->get()
        ->map(fn ($type) => (object) [
            'model' => $type,
            'available' => max(0, $type->total_quantity - $type->onLoanQuantity() - $type->reservedQuantity()),
        ]);
@endphp

<div class="space-y-10">
    {{-- Header --}}
    <section class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-950 via-teal-900 to-cyan-950 px-6 py-12 shadow-xl shadow-emerald-950/20 sm:px-10">
        <div class="pointer-events-none absolute -top-24 -right-24 size-80 rounded-full bg-emerald-500/20 blur-3xl" aria-hidden="true"></div>

        <div class="relative max-w-2xl space-y-3">
            <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold tracking-wide text-emerald-200 uppercase backdrop-blur">
                <flux:icon icon="bolt" variant="mini" class="size-3.5" />
                {{ __('Equipment hub') }}
            </span>
            <flux:heading level="1" size="xl" class="!text-white">{{ __('Borrowable gear catalog') }}</flux:heading>
            <p class="leading-relaxed text-emerald-100/90">
                {{ __('All equipment is issued free at the sports complex counter against your student card. Availability updates live as students borrow and return.') }}
            </p>
        </div>
    </section>

    {{-- Catalog grid --}}
    @if ($gear->isEmpty())
        <flux:callout variant="info" icon="information-circle">
            {{ __('No equipment is listed yet — check back soon.') }}
        </flux:callout>
    @else
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4" data-test="gear-grid">
            @foreach ($gear as $item)
                <article class="flex flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900" data-test="gear-card">
                    {{-- Placeholder visual --}}
                    <div class="grid h-28 place-items-center bg-gradient-to-br from-zinc-100 to-zinc-200 dark:from-zinc-800 dark:to-zinc-900">
                        <span class="inline-grid size-14 place-items-center rounded-2xl bg-white/70 text-indigo-600 shadow-sm dark:bg-zinc-700/70 dark:text-indigo-300">
                            <flux:icon icon="cube-transparent" class="size-8" />
                        </span>
                    </div>

                    <div class="flex flex-1 flex-col gap-2 p-4">
                        <flux:heading>{{ $item->model->name }}</flux:heading>
                        <flux:text class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">{{ $item->model->description }}</flux:text>

                        <div class="mt-auto space-y-2 pt-2">
                            @if ($item->available > 0)
                                <flux:badge size="sm" class="!bg-emerald-500/10 !text-emerald-600 ring-1 ring-inset ring-emerald-500/30 dark:!text-emerald-400" data-test="gear-available">
                                    <flux:icon icon="check-circle" variant="solid" class="size-3.5" />
                                    {{ __(':n available', ['n' => $item->available]) }}
                                </flux:badge>
                            @else
                                <flux:badge size="sm" class="!bg-red-500/10 !text-red-600 ring-1 ring-inset ring-red-500/30 dark:!text-red-400" data-test="gear-out-of-stock">
                                    <flux:icon icon="x-circle" variant="solid" class="size-3.5" />
                                    {{ __('Out of stock') }}
                                </flux:badge>
                            @endif

                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                <div class="h-full rounded-full {{ $item->available > 0 ? 'bg-gradient-to-r from-emerald-500 to-teal-400' : 'bg-red-400' }}"
                                    style="width: {{ $item->model->total_quantity > 0 ? round($item->available / $item->model->total_quantity * 100) : 0 }}%">
                                </div>
                            </div>
                            <p class="text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                                {{ __('of :total total', ['total' => $item->model->total_quantity]) }}
                            </p>

                            @auth
                                <flux:button size="sm" variant="primary" class="w-full !justify-center"
                                    wire:click="startReserve({{ $item->model->id }})"
                                    :disabled="$item->available === 0"
                                    data-test="reserve-button"
                                >
                                    {{ __('Reserve') }}
                                </flux:button>
                            @endauth
                            @guest
                                <flux:button size="sm" variant="subtle" class="w-full !justify-center" :href="route('login')" wire:navigate>
                                    {{ __('Log in to reserve') }}
                                </flux:button>
                            @endguest
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    {{-- Reserve modal --}}
    <flux:modal wire:model="showReserve" name="reserve-gear" class="w-full max-w-md">
        @php
            $reserving = $gear->firstWhere(fn ($entry) => $entry->model->id === $reserveType)?->model;
            $availableOf = fn (?int $id): int => (int) ($gear->firstWhere(fn ($entry) => $entry->model->id === $id)->available ?? 0);
        @endphp

        @if ($reserving !== null)
            <form wire:submit="submitReservation" class="space-y-5" data-test="reserve-modal">
                <div>
                    <flux:heading size="lg">{{ __('Reserve :name', ['name' => $reserving->name]) }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Counter staff will approve your request, then you collect it with your student card.') }}
                    </flux:text>
                </div>

                @if ($errors->has('reservation'))
                    <flux:callout variant="danger" icon="exclamation-circle">
                        <flux:callout.text>{{ $errors->first('reservation') }}</flux:callout.text>
                    </flux:callout>
                @endif

                <flux:field>
                    <flux:label>{{ $reserving->name }}</flux:label>
                    <flux:description>{{ __('Free right now') }}: {{ $availableOf($reserving->id) }}</flux:description>
                    <flux:input type="number" wire:model="quantities.{{ $reserving->id }}" min="1" :max="$availableOf($reserving->id)" data-test="reserve-quantity" />
                </flux:field>

                @if ($reserving->suggestedAccessories->isNotEmpty())
                    <div class="space-y-2 rounded-xl border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/60" data-test="accessory-suggestions">
                        <p class="text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">
                            {{ __('Suggested accessories') }}
                        </p>
                        @foreach ($reserving->suggestedAccessories as $accessory)
                            <button type="button" wire:click="toggleAccessory({{ $accessory->id }})"
                                class="flex w-full items-start gap-2 text-left text-sm"
                            >
                                <span class="mt-0.5 grid size-4 shrink-0 place-items-center rounded border {{ isset($quantities[$accessory->id]) ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-zinc-300 bg-white dark:border-zinc-600 dark:bg-zinc-900' }}">
                                    @if (isset($quantities[$accessory->id]))
                                        <flux:icon icon="check" variant="mini" class="size-3" />
                                    @endif
                                </span>
                                <span class="flex-1">
                                    <span class="font-medium">{{ $accessory->name }}</span>
                                    <span class="block text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $accessory->pivot->prompt }} · {{ __('Free right now') }}: {{ $availableOf($accessory->id) }}
                                    </span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="flex items-center justify-end gap-3 pt-1">
                    <flux:button type="button" variant="ghost" wire:click="$set('showReserve', false)">
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button variant="primary" type="submit" data-test="reserve-submit">
                        {{ __('Request reservation') }}
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>

    {{-- How borrowing works band --}}
    <section class="rounded-2xl border border-zinc-200 bg-white p-6 sm:flex-row sm:items-center dark:border-zinc-800 dark:bg-zinc-900 flex flex-col items-start gap-4">
        <span class="inline-grid size-11 shrink-0 place-items-center rounded-xl bg-emerald-600/10 text-emerald-600 dark:text-emerald-400">
            <flux:icon icon="qr-code" class="size-6" />
        </span>
        <div class="flex-1 space-y-1">
            <flux:heading>{{ __('Want this gear for your next game?') }}</flux:heading>
            <flux:text class="leading-relaxed text-zinc-600 dark:text-zinc-300">
                {{ __('Reserve online, get staff approval and collect at the counter against your student card — the whole trip takes under two minutes.') }}
            </flux:text>
        </div>
        @guest
            <flux:button variant="primary" :href="route('login')" wire:navigate class="shrink-0">
                {{ __('Reserve gear') }}
            </flux:button>
        @else
            <flux:button variant="primary" :href="route('reservations.index')" wire:navigate class="shrink-0" data-test="my-bookings-link">
                {{ __('My bookings') }}
            </flux:button>
        @endguest
    </section>
</div>
