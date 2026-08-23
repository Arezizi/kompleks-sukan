<?php

use App\Enums\ReservationStatus;
use App\Services\QrCodeService;
use App\Services\ReservationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('My bookings')] class extends Component {
    /**
     * Cancel one of the student's own reservations.
     */
    public function cancel(int $reservationId): void
    {
        $reservation = Auth::user()->reservations()->findOrFail($reservationId);

        app(ReservationService::class)->cancel($reservation);
    }
}; ?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('My bookings') }}</flux:heading>
        <flux:text class="mt-2">
            {{ __('Track your gear requests and active loans. Show the pickup code at the sports counter once your request is approved.') }}
        </flux:text>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" data-test="flash-status">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    {{-- Active loans --}}
    @php
        $activeLoans = Auth::user()->loans()->with('items.equipmentType')->active()->orderByDesc('checked_out_at')->get();
    @endphp

    <div class="space-y-3" data-test="active-loans">
        <flux:heading>{{ __('Active loans') }}</flux:heading>

        @if ($activeLoans->isEmpty())
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Nothing on loan right now.') }}</p>
        @else
            <ul class="divide-y divide-zinc-100 rounded-2xl border border-zinc-200 bg-white dark:divide-zinc-800 dark:border-zinc-800 dark:bg-zinc-900">
                @foreach ($activeLoans as $loan)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3" wire:key="loan-{{ $loan->id }}">
                        <div class="min-w-0 space-y-1">
                            <p class="truncate text-sm font-semibold">
                                {{ $loan->items->map(fn ($item) => $item->quantity.'× '.$item->equipmentType->name)->implode(', ') }}
                            </p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 {{ $loan->due_at->isPast() ? 'font-semibold text-red-600 dark:text-red-400' : '' }}">
                                {{ __('Due by :time', ['time' => $loan->due_at->format('g:i A')]) }}
                                @if ($loan->due_at->isPast())
                                    — {{ __('overdue') }}
                                @endif
                            </p>
                        </div>
                        @if ($loan->due_at->isPast())
                            <flux:badge size="sm" variant="pill" class="!bg-red-500/10 !text-red-600 dark:!text-red-400">{{ __('Overdue') }}</flux:badge>
                        @else
                            <flux:badge size="sm" variant="pill" class="!bg-emerald-500/10 !text-emerald-600 dark:!text-emerald-400">{{ __('On loan') }}</flux:badge>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Reservations --}}
    @php
        $reservations = Auth::user()->reservations()
            ->with('items.equipmentType')
            ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Approved, ReservationStatus::Rejected])
            ->orderByDesc('created_at')
            ->get();
    @endphp

    <div class="space-y-3" data-test="my-reservations">
        <flux:heading>{{ __('Gear requests') }}</flux:heading>

        @if ($reservations->isEmpty())
            <div class="rounded-2xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700" data-test="no-reservations">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('No open requests.') }}
                    <a href="{{ route('gear.index') }}" wire:navigate class="font-semibold text-[var(--color-accent-content)] underline underline-offset-4 dark:text-indigo-300">{{ __('Browse the gear catalog') }}</a>
                </p>
            </div>
        @else
            <ul class="space-y-4">
                @foreach ($reservations as $reservation)
                    <li class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900" wire:key="reservation-{{ $reservation->id }}" data-test="reservation-card">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 space-y-1">
                                <p class="text-sm font-semibold">
                                    {{ $reservation->items->map(fn ($item) => $item->quantity.'× '.$item->equipmentType->name)->implode(', ') }}
                                </p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('Requested') }} {{ $reservation->created_at->format('d M, g:i A') }}
                                    @if ($reservation->status === ReservationStatus::Approved && $reservation->pickup_deadline !== null)
                                        · {{ __('Collect before :time', ['time' => $reservation->pickup_deadline->format('d M, g:i A')]) }}
                                    @endif
                                </p>
                                @if ($reservation->decision_note !== null && filled($reservation->decision_note))
                                    <p class="text-xs italic text-zinc-500 dark:text-zinc-400">“{{ $reservation->decision_note }}”</p>
                                @endif
                            </div>

                            @php switch ($reservation->status) {
                                case ReservationStatus::Pending:
                                    $badgeClass = '!bg-amber-500/10 !text-amber-600 dark:!text-amber-400';
                                    break;
                                case ReservationStatus::Approved:
                                    $badgeClass = '!bg-emerald-500/10 !text-emerald-600 dark:!text-emerald-400';
                                    break;
                                default:
                                    $badgeClass = '!bg-red-500/10 !text-red-600 dark:!text-red-400';
                            } @endphp

                            <flux:badge size="sm" variant="pill" class="{{ $badgeClass }}" data-test="reservation-status">
                                {{ $reservation->status->label() }}
                            </flux:badge>
                        </div>

                        @if ($reservation->status === ReservationStatus::Approved)
                            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/60" data-test="pickup-code-block">
                                <div class="grid shrink-0 place-items-center self-center rounded-lg bg-white p-2 ring-1 ring-zinc-200 dark:bg-white dark:ring-zinc-700" data-test="pickup-qr">
                                    {!! QrCodeService::svg(QrCodeService::reservationPickupUrl($reservation->token), 120) !!}
                                </div>
                                <div class="min-w-0 space-y-1 text-center sm:text-left">
                                    <code class="block truncate font-mono text-xs font-bold tracking-wider" data-test="pickup-code">{{ $reservation->token }}</code>
                                    <span class="block text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ __('Show this QR or code at the counter to collect your gear.') }}
                                    </span>
                                </div>
                            </div>
                        @endif

                        @if (in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Approved], true))
                            <div class="mt-3">
                                <flux:button size="sm" variant="danger" wire:click="cancel({{ $reservation->id }})"
                                    wire:confirm="{{ __('Cancel this reservation?') }}" data-test="cancel-reservation"
                                >
                                    {{ __('Cancel') }}
                                </flux:button>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Quick re-reserve --}}
    <div class="flex items-center gap-3 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
        <span class="inline-grid size-10 shrink-0 place-items-center rounded-xl bg-indigo-600/10 text-indigo-600 dark:text-indigo-300">
            <flux:icon icon="plus" class="size-5" />
        </span>
        <div class="flex-1">
            <flux:heading>{{ __('Need something else?') }}</flux:heading>
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Reserve more gear from the catalog.') }}</flux:text>
        </div>
        <flux:button size="sm" variant="primary" :href="route('gear.index')" wire:navigate>{{ __('Browse gear') }}</flux:button>
    </div>
</section>
