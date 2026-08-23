<?php

use App\Models\Penalty;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Penalties')] class extends Component {
    /**
     * Lift an active penalty early (staff discretion).
     */
    public function lift(int $penaltyId): void
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

@php
    $penalties = App\Models\Penalty::query()
        ->with(['user', 'loan', 'liftedBy'])
        ->orderByDesc('starts_at')
        ->limit(100)
        ->get();
@endphp

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Penalties') }}</flux:heading>
        <flux:text class="mt-2">
            {{ __('Borrowing bans from late returns. Active penalties block all new reservations and checkouts.') }}
        </flux:text>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" data-test="flash-status">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="space-y-3" data-test="penalty-list">
        @forelse ($penalties as $penalty)
            @php
                $active = $penalty->lifted_at === null && $penalty->ends_at->isFuture();
            @endphp
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900 {{ $active ? 'border-l-4 border-l-red-500' : '' }}" wire:key="penalty-{{ $penalty->id }}" data-test="penalty-row">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0 space-y-0.5">
                        <p class="truncate text-sm font-semibold">{{ $penalty->user->name }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $penalty->user->matric_no ?? $penalty->user->email }}
                            · {{ $penalty->starts_at->format('d M') }} → {{ $penalty->ends_at->format('d M Y') }}
                            @if ($penalty->loan_id !== null)
                                · {{ __('loan #:id', ['id' => $penalty->loan_id]) }}
                            @endif
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($penalty->imposed_automatically)
                            <flux:badge color="zinc" data-test="auto-badge">{{ __('Automatic') }}</flux:badge>
                        @else
                            <flux:badge color="purple" data-test="manual-badge">{{ __('Manual') }}</flux:badge>
                        @endif

                        @if ($penalty->lifted_at !== null)
                            <flux:badge color="emerald" data-test="lifted-badge">
                                {{ __('Lifted :date', ['date' => $penalty->lifted_at->format('d M')]) }}
                                @if ($penalty->liftedBy !== null)
                                    · {{ $penalty->liftedBy->name }}
                                @endif
                            </flux:badge>
                        @elseif ($active)
                            <flux:badge color="red" data-test="active-badge">{{ __('Active') }}</flux:badge>
                            <flux:button size="sm" variant="subtle" wire:click="lift({{ $penalty->id }})" data-test="lift-button">
                                {{ __('Lift') }}
                            </flux:button>
                        @else
                            <flux:badge color="zinc" data-test="expired-badge">{{ __('Expired') }}</flux:badge>
                        @endif
                    </div>
                </div>

                <p class="mt-2 text-xs text-zinc-600 dark:text-zinc-300">{{ $penalty->reason }}</p>
            </div>
        @empty
            <p class="rounded-2xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                {{ __('No penalties on record. Everyone is playing fair.') }}
            </p>
        @endforelse
    </div>
</section>
