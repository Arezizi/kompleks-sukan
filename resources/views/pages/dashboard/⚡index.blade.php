<?php

use App\Models\Loan;
use App\Models\Reservation;
use App\Services\PenaltyService;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    //
}; ?>

@php
    $user = auth()->user();
    $activePenalty = app(PenaltyService::class)->activePenaltyFor($user);

    $activeLoans = Loan::query()
        ->where('user_id', $user->id)
        ->active()
        ->with('items.equipmentType')
        ->orderBy('due_at')
        ->get();

    $reservations = Reservation::query()
        ->where('user_id', $user->id)
        ->holdingStock()
        ->with('items.equipmentType')
        ->orderByDesc('created_at')
        ->get();

    $history = Loan::query()
        ->where('user_id', $user->id)
        ->whereNotNull('returned_at')
        ->with('items.equipmentType')
        ->orderByDesc('returned_at')
        ->limit(10)
        ->get();
@endphp

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Student Dashboard') }}</flux:heading>
        <flux:text class="mt-2">
            {{ __('Welcome back, :name. Keep track of active loans, countdowns and pickup reservations.', ['name' => $user->name]) }}
        </flux:text>
    </div>

    @if ($activePenalty !== null)
        <flux:callout variant="danger" icon="exclamation-triangle" data-test="penalty-banner">
            <flux:callout.text>
                {{ __('Active borrowing ban until :date due to a late return. Visit the counter.', ['date' => $activePenalty->ends_at->format('d M Y')]) }}
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- Active Loans --}}
    <div class="space-y-3" data-test="active-loans-section">
        <flux:heading>{{ __('Active loans') }}</flux:heading>

        @forelse ($activeLoans as $loan)
            @php
                $isOverdue = $loan->due_at->isPast();
            @endphp
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900 {{ $isOverdue ? 'border-red-500 bg-red-500/5' : '' }}" wire:key="loan-{{ $loan->id }}" data-test="active-loan-row">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <p class="text-sm font-semibold">
                            {{ $loan->items->map(fn ($i) => $i->quantity.'× '.$i->equipmentType->name)->implode(', ') }}
                        </p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Checked out at :time', ['time' => $loan->checked_out_at->format('d M, g:i A')]) }}
                        </p>
                    </div>

                    <div class="text-right">
                        @if ($isOverdue)
                            <flux:badge color="red">{{ __('Overdue!') }}</flux:badge>
                        @else
                            <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400" x-data="{
                                due: '{{ $loan->due_at->toIso8601String() }}',
                                diff: '',
                                init() {
                                    setInterval(() => {
                                        let ms = new Date(this.due) - new Date();
                                        if (ms <= 0) { this.diff = 'Due now'; return; }
                                        let m = Math.floor(ms / 60000);
                                        let s = Math.floor((ms % 60000) / 1000);
                                        let h = Math.floor(m / 60);
                                        m = m % 60;
                                        this.diff = (h > 0 ? h + 'h ' : '') + m + 'm ' + s + 's left';
                                    }, 1000);
                                }
                            }" x-text="diff" data-test="due-countdown">
                                {{ __('Due at :time', ['time' => $loan->due_at->format('g:i A')]) }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="rounded-2xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                {{ __('No equipment on loan right now.') }}
            </p>
        @endforelse
    </div>

    {{-- Reservations holding stock --}}
    <div class="space-y-3" data-test="reservations-section">
        <div class="flex items-center justify-between">
            <flux:heading>{{ __('Current bookings') }}</flux:heading>
            <flux:button size="sm" :href="route('reservations.index')" wire:navigate>{{ __('Manage bookings') }}</flux:button>
        </div>

        @forelse ($reservations as $res)
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900" wire:key="res-{{ $res->id }}" data-test="student-booking-row">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold">{{ $res->items->map(fn ($i) => $i->quantity.'× '.$i->equipmentType->name)->implode(', ') }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $res->status->label() }} · Token: <code class="font-mono font-bold">{{ $res->token }}</code></p>
                    </div>
                    @if ($res->pickup_deadline !== null)
                        <span class="text-xs text-zinc-500">{{ __('Pickup before :date', ['date' => $res->pickup_deadline->format('d M, g:i A')]) }}</span>
                    @endif
                </div>
            </div>
        @empty
            <p class="rounded-2xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                {{ __('No active reservations.') }}
            </p>
        @endforelse
    </div>

    {{-- Borrowing history --}}
    <div class="space-y-3" data-test="history-section">
        <flux:heading>{{ __('Borrowing history') }}</flux:heading>

        @forelse ($history as $loan)
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 text-xs dark:border-zinc-800 dark:bg-zinc-900" wire:key="history-{{ $loan->id }}" data-test="history-row">
                <div class="flex justify-between">
                    <span class="font-semibold">{{ $loan->items->map(fn ($i) => $i->quantity.'× '.$i->equipmentType->name)->implode(', ') }}</span>
                    <span class="text-emerald-600 dark:text-emerald-400">{{ __('Returned :date', ['date' => $loan->returned_at?->format('d M, g:i A')]) }}</span>
                </div>
            </div>
        @empty
            <p class="rounded-2xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                {{ __('No past loans yet.') }}
            </p>
        @endforelse
    </div>
</section>
