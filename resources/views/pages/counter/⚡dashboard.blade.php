<?php

use App\Enums\ReservationStatus;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Penalty;
use App\Models\Reservation;
use App\Models\SiteSetting;
use App\Services\PenaltyService;
use App\Services\ReservationService;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Counter desk')] class extends Component {
    /**
     * Reservation currently being decided (reject note entry).
     */
    public ?int $decidingId = null;

    public string $decisionNote = '';

    /**
     * Approve or reject the given pending reservation.
     */
    public function decide(int $reservationId, bool $approve): void
    {
        $reservation = Reservation::query()->findOrFail($reservationId);

        app(ReservationService::class)->decide(
            $reservation,
            auth()->user(),
            $approve,
            $this->decisionNote !== '' && ! $approve ? $this->decisionNote : null,
        );

        $this->reset('decidingId', 'decisionNote');

        session()->flash('status', $approve
            ? __('Reservation approved.')
            : __('Reservation rejected.'));
    }

    /**
     * Lift an active penalty early (staff discretion).
     */
    public function liftPenalty(int $penaltyId): void
    {
        /** @var Penalty|null $penalty */
        $penalty = Penalty::query()
            ->whereNull('lifted_at')
            ->findOrFail($penaltyId);

        $penalty->forceFill([
            'lifted_at' => now(),
            'lifted_by' => auth()->id(),
        ])->save();

        session()->flash('status', __('Penalty lifted.'));
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Counter desk') }}</flux:heading>
            <flux:text class="mt-2">
                {{ __('Approve gear requests, hand out equipment and keep an eye on overdue loans.') }}
            </flux:text>
        </div>

        <flux:dropdown position="bottom" align="end">
            <flux:button icon="arrow-down-tray" size="sm">{{ __('Exports') }}</flux:button>

            <flux:menu>
                <flux:menu.item as="a" href="{{ route('admin.export', ['type' => 'loans']) }}" icon="arrow-down-tray">
                    {{ __('Loans (CSV)') }}
                </flux:menu.item>
                <flux:menu.item as="a" href="{{ route('admin.export', ['type' => 'reservations']) }}" icon="arrow-down-tray">
                    {{ __('Reservations (CSV)') }}
                </flux:menu.item>
                <flux:menu.item as="a" href="{{ route('admin.export', ['type' => 'incidents']) }}" icon="arrow-down-tray">
                    {{ __('Incidents (CSV)') }}
                </flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" data-test="flash-status">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    @php
        $settings = SiteSetting::current();

        $pending = Reservation::query()
            ->where('status', ReservationStatus::Pending)
            ->with(['items.equipmentType', 'user'])
            ->orderBy('created_at')
            ->get();

        $ready = Reservation::query()
            ->where('status', ReservationStatus::Approved)
            ->with(['items.equipmentType', 'user'])
            ->orderBy('pickup_deadline')
            ->get();

        $overdue = Loan::query()->overdue()
            ->with(['items.equipmentType', 'user'])
            ->orderBy('due_at')
            ->get();

        $blockedStudents = collect()
            ->merge($pending->pluck('user'))->merge($ready->pluck('user'))
            ->unique('id')
            ->filter(fn ($user) => app(PenaltyService::class)->blocks($user));

        $stats = [
            ['label' => __('Pending approvals'), 'value' => $pending->count(), 'color' => 'amber'],
            ['label' => __('Ready for pickup'), 'value' => $ready->count(), 'color' => 'emerald'],
            ['label' => __('Overdue loans'), 'value' => $overdue->count(), 'color' => 'red'],
            ['label' => __('Gear on loan'), 'value' => (int) LoanItem::query()->whereHas('loan', fn ($q) => $q->whereNull('returned_at'))->sum('quantity'), 'color' => 'sky'],
        ];

        $statStyles = [
            'amber' => 'text-amber-600 dark:text-amber-400',
            'emerald' => 'text-emerald-600 dark:text-emerald-400',
            'red' => 'text-red-600 dark:text-red-400',
            'sky' => 'text-sky-600 dark:text-sky-400',
        ];
    @endphp

    {{-- Stat strip --}}
    <dl class="grid grid-cols-2 gap-4 lg:grid-cols-4" data-test="counter-stats">
        @foreach ($stats as ['label' => $label, 'value' => $value, 'color' => $color])
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <dd class="text-3xl font-bold tabular-nums {{ $statStyles[$color] }}">{{ $value }}</dd>
                <dt class="mt-1 text-xs font-medium tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ $label }}</dt>
            </div>
        @endforeach
    </dl>

    <div class="grid gap-6 xl:grid-cols-2">
        {{-- Pending approvals --}}
        <div class="space-y-3" data-test="pending-queue">
            <flux:heading>{{ __('Pending approvals') }}</flux:heading>

            @forelse ($pending as $reservation)
                <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900" wire:key="pending-{{ $reservation->id }}" data-test="pending-row">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0 space-y-0.5">
                            <p class="truncate text-sm font-semibold">{{ $reservation->user->name }}</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $reservation->user->matric_no ?? $reservation->user->email }}
                                · {{ $reservation->created_at->format('d M, g:i A') }}
                            </p>
                        </div>
                        <span class="rounded-lg bg-zinc-100 px-2 py-1 text-xs tabular-nums dark:bg-zinc-800">
                            {{ $reservation->items->map(fn ($item) => $item->quantity.'× '.$item->equipmentType->name)->implode(', ') }}
                        </span>
                    </div>

                    @if ($reservation->note !== null && filled($reservation->note))
                        <p class="mt-2 text-xs italic text-zinc-500 dark:text-zinc-400">“{{ $reservation->note }}”</p>
                    @endif

                    @if ($decidingId === $reservation->id)
                        <div class="mt-3 space-y-2" data-test="reject-form">
                            <flux:input wire:model="decisionNote" :placeholder="__('Reason (optional)')" />
                            <div class="flex gap-2">
                                <flux:button size="sm" variant="danger" wire:click="decide({{ $reservation->id }}, false)" data-test="confirm-reject">
                                    {{ __('Confirm rejection') }}
                                </flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="$set('decidingId', null)">{{ __('Keep waiting') }}</flux:button>
                            </div>
                        </div>
                    @else
                        <div class="mt-3 flex gap-2">
                            <flux:button size="sm" variant="primary" wire:click="decide({{ $reservation->id }}, true)" data-test="approve-button">
                                {{ __('Approve') }}
                            </flux:button>
                            <flux:button size="sm" variant="danger" wire:click="$set('decidingId', {{ $reservation->id }})" data-test="reject-button">
                                {{ __('Reject') }}
                            </flux:button>
                        </div>
                    @endif
                </div>
            @empty
                <p class="rounded-2xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                    {{ __('No pending requests — nice and quiet.') }}
                </p>
            @endforelse

            @if ($errors->has('reservation'))
                <flux:callout variant="danger" icon="exclamation-triangle">
                    <flux:callout.text>{{ $errors->first('reservation') }}</flux:callout.text>
                </flux:callout>
            @endif
        </div>

        <div class="space-y-6">
            {{-- Ready for pickup --}}
            <div class="space-y-3" data-test="ready-pickups">
                <flux:heading>{{ __('Ready for pickup') }}</flux:heading>

                @forelse ($ready as $reservation)
                    <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/5 p-4" wire:key="ready-{{ $reservation->id }}" data-test="ready-row">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0 space-y-0.5">
                                <p class="truncate text-sm font-semibold">{{ $reservation->user->name }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $reservation->items->map(fn ($item) => $item->quantity.'× '.$item->equipmentType->name)->implode(', ') }}
                                </p>
                            </div>
                            <code class="rounded bg-white px-2 py-1 font-mono text-[11px] font-bold tracking-wider shadow-sm dark:bg-zinc-800">{{ $reservation->token }}</code>
                        </div>
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Collect before :time', ['time' => $reservation->pickup_deadline?->format('d M, g:i A')]) }}
                        </p>
                    </div>
                @empty
                    <p class="rounded-2xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                        {{ __('Nothing waiting at the counter.') }}
                    </p>
                @endforelse
            </div>

            {{-- Overdue --}}
            <div class="space-y-3" data-test="overdue-list">
                <flux:heading>{{ __('Overdue loans') }}</flux:heading>

                @forelse ($overdue as $loan)
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-red-500/30 bg-red-500/5 p-4" wire:key="overdue-{{ $loan->id }}" data-test="overdue-row">
                        <div class="min-w-0 space-y-0.5">
                            <p class="truncate text-sm font-semibold">{{ $loan->user->name }} <span class="font-normal text-zinc-500">({{ $loan->user->matric_no ?? '—' }})</span></p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $loan->items->map(fn ($item) => $item->quantity.'× '.$item->equipmentType->name)->implode(', ') }}
                                · {{ __('due') }} {{ $loan->due_at->format('d M, g:i A') }}
                            </p>
                        </div>
                        <flux:button size="sm" :href="route('counter.returns', ['search' => $loan->user->matric_no ?? ''])" wire:navigate>
                            {{ __('Handle') }}
                        </flux:button>
                    </div>
                @empty
                    <p class="rounded-2xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                        {{ __('Everything is back on time. Great!') }}
                    </p>
                @endforelse
            </div>

            {{-- Blocked students --}}
            @if ($blockedStudents->isNotEmpty())
                <div class="space-y-3" data-test="blocked-students">
                    <flux:heading>{{ __('Blocked by penalty') }}</flux:heading>
                    <ul class="divide-y divide-zinc-100 rounded-2xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                        @foreach ($blockedStudents as $student)
                            @php $penalty = app(PenaltyService::class)->activePenaltyFor($student); @endphp
                            <li class="flex items-center justify-between gap-3 px-4 py-3" wire:key="block-{{ $student->id }}">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold">{{ $student->name }}</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ __('until :date', ['date' => $penalty?->ends_at?->format('d M')]) }}
                                    </p>
                                </div>
                                <flux:button size="sm" variant="subtle" wire:click="liftPenalty({{ $penalty->id }})">
                                    {{ __('Lift') }}
                                </flux:button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</section>
