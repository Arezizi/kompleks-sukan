<?php

use App\Models\SiteSetting;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::public')] #[Title('Borrowing rules')] class extends Component {}; ?>

@php
    $settings = SiteSetting::current();
    $closingTime = Carbon::createFromFormat('H:i:s', $settings->closing_time)->format('g:i A');
    $loanHours = intdiv($settings->max_loan_minutes, 60);
    $loanMinutes = $settings->max_loan_minutes % 60;
    $loanDuration = $loanMinutes === 0
        ? __(':n hour(s)', ['n' => $loanHours])
        : ($loanHours === 0 ? __(':n minute(s)', ['n' => $loanMinutes]) : __(':h hr :m min', ['h' => $loanHours, 'm' => $loanMinutes]));
@endphp

<div class="mx-auto max-w-4xl space-y-10">
    {{-- Header --}}
    <header class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-zinc-900 via-indigo-950 to-sky-950 px-6 py-12 shadow-xl shadow-zinc-950/20 sm:px-10">
        <div class="pointer-events-none absolute -bottom-24 -left-16 size-80 rounded-full bg-sky-500/15 blur-3xl" aria-hidden="true"></div>

        <div class="relative max-w-2xl space-y-3">
            <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold tracking-wide text-sky-200 uppercase backdrop-blur">
                <flux:icon icon="book-open" variant="mini" class="size-3.5" />
                {{ __('House rules') }}
            </span>
            <flux:heading level="1" size="xl" class="!text-white">{{ __('Borrowing rules & how it works') }}</flux:heading>
            <p class="leading-relaxed text-indigo-100/90">
                {{ __('Everything you need to know before borrowing equipment at the PMS sports complex.') }}
            </p>
        </div>
    </header>

    {{-- Steps --}}
    <section class="space-y-6">
        <flux:heading size="lg">{{ __('The process') }}</flux:heading>
        <ol class="space-y-4">
            @foreach ([
                ['user', 'Log in with your matric number', 'Accounts are created from the official student roster — no sign-up needed. First login sets your own password.'],
                ['clipboard-document-check', 'Reserve your gear online', 'Pick the equipment and quantity you need. The system suggests matching accessories — like shuttlecocks for rackets.'],
                ['qr-code', 'Scan & collect at the counter', 'Show your reservation QR at the sports counter and hand over your student card as deposit. Your loan starts the moment you walk away.'],
                ['clock', 'Return before the deadline', 'Bring everything back in good condition within the borrowing window. Your card comes back when the gear does.'],
            ] as [$icon, $title, $copy])
                <li class="flex items-start gap-4 rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <span class="inline-grid size-11 shrink-0 place-items-center rounded-xl bg-indigo-600/10 text-indigo-600 dark:bg-indigo-400/10 dark:text-indigo-300">
                        <flux:icon icon="{{ $icon }}" class="size-5" />
                    </span>
                    <div class="space-y-1">
                        <flux:heading>{{ __($title) }}</flux:heading>
                        <flux:text class="leading-relaxed text-zinc-500 dark:text-zinc-400">{{ __($copy) }}</flux:text>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- The fine print --}}
    <section class="space-y-6">
        <flux:heading size="lg">{{ __('The fine print') }}</flux:heading>

        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ([
                ['clock', 'Borrowing window', 'Gear is issued for a maximum of '.$loanDuration.' — and always due back by '.$closingTime.', whichever comes first. Nothing stays out overnight.'],
                ['moon', 'Day use only', 'All courts, fields and the gym operate during daylight hours only. Plan sessions early; staff lock up promptly at closing time.'],
                ['credit-card', 'Card deposit', 'Your student card is held at the counter for every loan and returned only when all items come back complete and undamaged.'],
                ['exclamation-triangle', 'Late returns', 'Missing the return window triggers an automatic '.$settings->penalty_days.'-day borrowing pause across all gear. Repeat late returns extend from the latest offence.'],
                ['wrench-screwdriver', 'Care of equipment', 'Return gear clean and complete. Damage or missing parts are recorded by staff against your loan and incident log.'],
                ['shield-check', 'Safety first', 'Report any injury or near-miss to clinic / sports staff. Incidents are logged per facility so problem areas get fixed fast.'],
            ] as [$icon, $title, $copy])
                <article class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                    <span class="mb-3 inline-grid size-10 place-items-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                        <flux:icon icon="{{ $icon }}" class="size-5" />
                    </span>
                    <h3 class="text-sm font-bold">{{ __($title) }}</h3>
                    <p class="mt-1 text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">{{ $copy }}</p>
                </article>
            @endforeach
        </div>
    </section>

    {{-- CTA --}}
    <section class="flex flex-col items-start gap-4 rounded-2xl bg-gradient-to-r from-indigo-600 via-indigo-600 to-sky-600 p-6 sm:flex-row sm:items-center">
        <span class="inline-grid size-11 shrink-0 place-items-center rounded-xl bg-white/15 text-white">
            <flux:icon icon="arrow-right-circle" class="size-6" />
        </span>
        <div class="flex-1 space-y-1">
            <flux:heading class="!text-white">{{ __('Ready to play?') }}</flux:heading>
            <flux:text class="!text-indigo-100">{{ __('Check the status board, then reserve your gear.') }}</flux:text>
        </div>
        @guest
            <flux:button :href="route('login')" wire:navigate class="shrink-0 !bg-white !text-indigo-900 hover:!bg-indigo-50">
                {{ __('Student login') }}
            </flux:button>
        @else
            <flux:button :href="route('dashboard')" wire:navigate class="shrink-0 !bg-white !text-indigo-900 hover:!bg-indigo-50">
                {{ __('Go to dashboard') }}
            </flux:button>
        @endguest
    </section>
</div>
