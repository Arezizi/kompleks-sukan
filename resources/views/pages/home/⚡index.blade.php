<?php

use App\Enums\FacilityStatus;
use App\Models\EquipmentType;
use App\Models\Facility;
use App\Models\SiteSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::public')] #[Title('Sports Complex & Equipment Hub')] class extends Component {
    /**
     * Slug of the facility type whose sub-facilities are shown in the modal.
     */
    public ?string $activeGroup = null;

    /**
     * Icon used on the category card for a given type slug.
     */
    public function iconFor(string $slug): string
    {
        return match (true) {
            str_contains($slug, 'badminton') => 'sparkles',
            str_contains($slug, 'ping-pong') => 'view-columns',
            str_contains($slug, 'gym') => 'bolt',
            str_contains($slug, 'track') || str_contains($slug, 'athletics') => 'arrow-trending-up',
            str_contains($slug, 'archery') => 'viewfinder-circle',
            str_contains($slug, 'petanque') => 'circle-stack',
            str_contains($slug, 'football') || str_contains($slug, 'rugby') || str_contains($slug, 'hockey') => 'flag',
            default => 'trophy',
        };
    }
}; ?>

@php
    $settings = SiteSetting::current();
    $facilities = Facility::query()->visible()->ordered()->get();
    $gearTypes = EquipmentType::query()->where('is_active', true)->count();

    $openCount = $facilities->where('status', FacilityStatus::Open)->count();
    $maintenanceCount = $facilities->where('status', FacilityStatus::Maintenance)->count();
    $closedCount = $facilities->where('status', FacilityStatus::Closed)->count();

    $closingTime = Carbon::createFromFormat('H:i:s', $settings->closing_time)->format('g:i A');

    $groups = $facilities
        ->groupBy(fn (Facility $facility) => $facility->typeSlug())
        ->map(function ($items) {
            $first = $items->first();
            $count = $items->count();

            $open = $items->where('status', FacilityStatus::Open)->count();
            $maintenance = $items->where('status', FacilityStatus::Maintenance)->count();
            $closed = $items->where('status', FacilityStatus::Closed)->count();

            $summary = match (true) {
                $open === $count => ['label' => __('All open'), 'color' => 'emerald'],
                $maintenance === $count => ['label' => __('Under maintenance'), 'color' => 'amber'],
                $closed === $count => ['label' => __('All closed'), 'color' => 'red'],
                default => ['label' => __(':x of :n open', ['x' => $open, 'n' => $count]), 'color' => $closed > 0 ? 'red' : 'amber'],
            };

            $lastWord = str($first->typeName())->afterLast(' ');
            $unit = ($count === 1 || $lastWord->contains('('))
                ? null
                : strtolower(Str::plural($lastWord));

            return (object) [
                'key' => $first->typeSlug(),
                'name' => $first->typeName(),
                'category' => $first->category,
                'location' => $first->location,
                'meta' => $unit !== null
                    ? __(':n :unit', ['n' => $count, 'unit' => $unit])
                    : $first->location,
                'facilities' => $items->values(),
                ...$summary,
            ];
        })
        ->values();
@endphp

<div class="space-y-10">
    {{-- Hero --}}
    <section class="relative isolate overflow-hidden rounded-3xl bg-gradient-to-br from-orange-950 via-orange-900 to-amber-950 shadow-xl shadow-orange-950/20">
        <div class="pointer-events-none absolute -top-24 -right-24 size-96 rounded-full bg-amber-500/20 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-32 -left-16 size-80 rounded-full bg-orange-500/15 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,rgba(255,255,255,0.08),transparent_60%)]" aria-hidden="true"></div>

        <div class="relative grid items-center gap-10 px-6 py-12 sm:px-10 sm:py-16 lg:grid-cols-[1.2fr_1fr] lg:px-14">
            <div class="space-y-6">
                <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3.5 py-1.5 text-xs font-semibold tracking-[0.18em] text-amber-100 uppercase backdrop-blur">
                    <flux:icon class="size-3.5" icon="academic-cap" />
                    {{ __('Politeknik Muadzam Shah') }}
                </span>

                <div class="space-y-4">
                    <h1 class="text-balance text-4xl leading-tight font-bold tracking-tight text-white sm:text-5xl xl:text-6xl">
                        {{ __('Sports Complex & Equipment Hub') }}
                    </h1>
                    <p class="text-pretty max-w-xl text-base leading-relaxed text-orange-100/90 sm:text-lg">
                        {{ __('Your official portal for Politeknik Muadzam Shah sports facilities and smart equipment borrowing. Check real-time court availability, reserve gear online, and collect instantly at the sports counter.') }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    @auth
                        <flux:button variant="primary" :href="route('dashboard')" wire:navigate class="!bg-white !text-orange-900 hover:!bg-orange-50">
                            {{ __('Open my dashboard') }}
                            <flux:icon icon="arrow-right" variant="mini" class="size-4" />
                        </flux:button>
                    @else
                        <flux:button variant="primary" :href="route('login')" wire:navigate class="!bg-white !text-orange-900 hover:!bg-orange-50">
                            {{ __('Student login') }}
                            <flux:icon icon="arrow-right" variant="mini" class="size-4" />
                        </flux:button>
                    @endauth

                    <flux:button :href="'#status-board'" class="!border-white/25 !bg-white/10 !text-white backdrop-blur hover:!bg-white/20">
                        <flux:icon icon="squares-2x2" variant="mini" class="size-4" />
                        {{ __('View status board') }}
                    </flux:button>
                </div>

                <dl class="grid max-w-lg grid-cols-3 gap-x-4 pt-2 sm:gap-x-6">
                    <div class="space-y-1 border-l-2 border-orange-400/60 py-0.5 pl-3">
                        <dd class="text-xl font-bold tabular-nums text-white sm:text-2xl">{{ $facilities->count() }}</dd>
                        <dt class="text-xs font-medium tracking-wide text-orange-200">{{ __('Facilities') }}</dt>
                    </div>
                    <div class="space-y-1 border-l-2 border-amber-400/60 py-0.5 pl-3">
                        <dd class="text-xl font-bold tabular-nums text-white sm:text-2xl">{{ $gearTypes }}+</dd>
                        <dt class="text-xs font-medium tracking-wide text-orange-200">{{ __('Gear types') }}</dt>
                    </div>
                    <div class="space-y-1 border-l-2 border-yellow-400/60 py-0.5 pl-3">
                        <dd class="text-xl font-bold tabular-nums whitespace-nowrap text-white sm:text-2xl">{{ $closingTime }}</dd>
                        <dt class="text-xs font-medium tracking-wide text-orange-200">{{ __('Closes daily') }}</dt>
                    </div>
                </dl>
            </div>

            {{-- Live status summary --}}
            <div class="rounded-2xl border border-white/15 bg-white/[0.07] p-6 shadow-2xl backdrop-blur-md">
                <div class="flex items-center justify-between gap-3 pb-5">
                    <div class="flex items-center gap-2">
                        <span class="relative flex size-2.5">
                            <span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex size-2.5 rounded-full bg-emerald-400"></span>
                        </span>
                        <flux:text class="!font-semibold !text-white">{{ __('Live right now') }}</flux:text>
                    </div>
                    <flux:badge size="sm" variant="pill" class="!bg-amber-400/15 !text-amber-300">
                        {{ __('Day use only') }}
                    </flux:badge>
                </div>

                <dl class="grid grid-cols-3 gap-3 pb-5" data-test="live-status-summary">
                    <div class="rounded-xl bg-emerald-400/10 p-4 text-center ring-1 ring-inset ring-emerald-400/25">
                        <dd class="text-3xl font-bold tabular-nums text-emerald-300" data-test="open-count">{{ $openCount }}</dd>
                        <dt class="mt-1 text-xs font-semibold tracking-wide text-emerald-200/80 uppercase">{{ __('Open') }}</dt>
                    </div>
                    <div class="rounded-xl bg-amber-400/10 p-4 text-center ring-1 ring-inset ring-amber-400/25">
                        <dd class="text-3xl font-bold tabular-nums text-amber-300" data-test="maintenance-count">{{ $maintenanceCount }}</dd>
                        <dt class="mt-1 text-xs font-semibold tracking-wide text-amber-200/80 uppercase">{{ __('Maintenance') }}</dt>
                    </div>
                    <div class="rounded-xl bg-red-400/10 p-4 text-center ring-1 ring-inset ring-red-400/25">
                        <dd class="text-3xl font-bold tabular-nums text-red-300" data-test="closed-count">{{ $closedCount }}</dd>
                        <dt class="mt-1 text-xs font-semibold tracking-wide text-red-200/80 uppercase">{{ __('Closed') }}</dt>
                    </div>
                </dl>

                <flux:button variant="subtle" :href="'#status-board'" class="w-full !justify-center !border-white/15 !bg-white/10 !text-white hover:!bg-white/20">
                    {{ __('Browse all facilities') }}
                    <flux:icon icon="arrow-down" variant="mini" class="size-4" />
                </flux:button>
            </div>
        </div>
    </section>

    {{-- Status board --}}
    <section id="status-board" class="scroll-mt-24 space-y-6">
        <div class="mx-auto max-w-2xl space-y-2 text-center">
            <flux:heading size="xl">{{ __('Facility status board') }}</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400">
                {{ __('Pick a facility type to see every court, field and hall with its live status and house rules.') }}
            </flux:text>
        </div>

        @foreach (['indoor' => 'building-library', 'outdoor' => 'sun'] as $category => $categoryIcon)
            @php
                $categoryGroups = $groups->where('category.value', $category);
            @endphp

            @continue($categoryGroups->isEmpty())

            <div class="space-y-4">
                <h3 class="flex items-center gap-2 px-1 text-sm font-bold tracking-wider text-zinc-500 uppercase dark:text-zinc-400">
                    <flux:icon icon="{{ $categoryIcon }}" class="size-4" />
                    {{ __($category) }}
                    <span class="font-normal normal-case">({{ $categoryGroups->count() }})</span>
                </h3>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($categoryGroups as $group)
                        <button type="button" wire:click="$set('activeGroup', '{{ $group->key }}')"
                            class="group flex flex-col gap-3 rounded-2xl border border-zinc-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700"
                            data-test="facility-type-card"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <span class="inline-grid size-11 shrink-0 place-items-center rounded-xl bg-orange-600/10 text-orange-700 dark:bg-orange-400/10 dark:text-orange-300">
                                    <flux:icon icon="{{ $this->iconFor($group->key) }}" class="size-6" />
                                </span>
                                @php
                                    $summaryStyles = ['emerald' => '!bg-emerald-500/10 !text-emerald-600 dark:!text-emerald-400', 'amber' => '!bg-amber-500/10 !text-amber-600 dark:!text-amber-400', 'red' => '!bg-red-500/10 !text-red-600 dark:!text-red-400'];
                                @endphp
                                <flux:badge size="sm" class="ring-1 ring-inset {{ $summaryStyles[$group->color] }}">
                                    {{ $group->label }}
                                </flux:badge>
                            </div>

                            <flux:heading>{{ $group->name }}</flux:heading>

                            @if ($group->meta)
                                <p class="flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                                    <flux:icon icon="map-pin" variant="mini" class="size-3.5 shrink-0" />
                                    {{ $group->meta }}
                                </p>
                            @endif

                            <p class="mt-auto flex items-center gap-1.5 text-xs font-semibold text-accent-content opacity-0 transition group-hover:opacity-100 dark:text-orange-300">
                                {{ __('View :n facilities', ['n' => $group->facilities->count()]) }}
                                <flux:icon icon="arrow-right" variant="mini" class="size-3.5" />
                            </p>
                        </button>
                    @endforeach
                </div>
            </div>
        @endforeach
    </section>

    {{-- Sub-facility modal --}}
    <flux:modal wire:model="activeGroup" name="facility-group" class="w-full max-w-md">
        @php
            $activeGroup = $groups->firstWhere('key', $this->activeGroup);
        @endphp

        @if ($activeGroup)
            <div class="space-y-5" data-test="facility-group-modal">
                <div class="flex items-start gap-3">
                    <span class="inline-grid size-11 shrink-0 place-items-center rounded-xl bg-orange-600/10 text-orange-700 dark:bg-orange-400/10 dark:text-orange-300">
                        <flux:icon icon="{{ $this->iconFor($activeGroup->key) }}" class="size-6" />
                    </span>
                    <div class="min-w-0 space-y-0.5">
                        <flux:heading size="lg">{{ $activeGroup->name }}</flux:heading>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $activeGroup->category->label().' · '.$activeGroup->location }}
                        </p>
                    </div>
                </div>

                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800" data-test="facility-sub-list">
                    @foreach ($activeGroup->facilities as $facility)
                        <li data-test="facility-tile">
                            <a href="{{ route('facilities.show', $facility->slug) }}" wire:navigate
                                wire:click="$set('activeGroup', null)"
                                class="group flex items-center justify-between gap-3 py-3 transition hover:opacity-80"
                            >
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold">{{ $facility->name }}</p>
                                    @if (filled($facility->status_note))
                                        <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $facility->status_note }}</p>
                                    @endif
                                </div>
                                <x-facility-status-badge :status="$facility->status" />
                            </a>
                        </li>
                    @endforeach
                </ul>

                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Tap any facility for full details and house rules.') }}
                </p>
            </div>
        @endif
    </flux:modal>

    {{-- Features --}}
    <section class="space-y-6">
        <div class="mx-auto max-w-2xl space-y-2 text-center">
            <flux:heading size="xl">{{ __('Everything in one hub') }}</flux:heading>
            <flux:text class="text-zinc-500 dark:text-zinc-400">
                {{ __('One portal for every court, field and piece of gear on campus — managed by the sports complex office.') }}
            </flux:text>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <flux:card class="group transition hover:-translate-y-0.5 hover:shadow-lg">
                <span class="mb-4 inline-grid size-11 place-items-center rounded-xl bg-orange-600/10 text-orange-700 dark:bg-orange-400/10 dark:text-orange-300">
                    <flux:icon icon="building-library" class="size-6" />
                </span>
                <flux:heading>{{ __('Facility directory') }}</flux:heading>
                <flux:text class="mt-1.5 block leading-relaxed text-zinc-500 dark:text-zinc-400">
                    {{ __('Browse all indoor and outdoor courts, fields and the gym — with real-time Open, Maintenance and Closed status before you walk over.') }}
                </flux:text>
            </flux:card>

            <flux:card class="group transition hover:-translate-y-0.5 hover:shadow-lg">
                <span class="mb-4 inline-grid size-11 place-items-center rounded-xl bg-amber-600/10 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300">
                    <flux:icon icon="qr-code" class="size-6" />
                </span>
                <flux:heading>{{ __('Smart equipment borrowing') }}</flux:heading>
                <flux:text class="mt-1.5 block leading-relaxed text-zinc-500 dark:text-zinc-400">
                    {{ __('Reserve balls, rackets and sticks online, get smart accessory prompts, then scan your QR at the counter for an instant checkout.') }}
                </flux:text>
            </flux:card>

            <flux:card class="group transition hover:-translate-y-0.5 hover:shadow-lg">
                <span class="mb-4 inline-grid size-11 place-items-center rounded-xl bg-yellow-600/10 text-yellow-700 dark:bg-yellow-400/10 dark:text-yellow-300">
                    <flux:icon icon="shield-check" class="size-6" />
                </span>
                <flux:heading>{{ __('Fair rules, safe play') }}</flux:heading>
                <flux:text class="mt-1.5 block leading-relaxed text-zinc-500 dark:text-zinc-400">
                    {{ __('Clear return windows, automatic penalties for overdue gear, card deposit logging and an incident & first-aid record kept by staff.') }}
                </flux:text>
            </flux:card>
        </div>
    </section>

    {{-- How it works --}}
    <section id="how-it-works" class="scroll-mt-24">
        <div class="rounded-3xl border border-zinc-200 bg-white p-8 sm:p-10 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto max-w-2xl space-y-2 pb-8 text-center">
                <flux:heading size="xl">{{ __('Borrowing in three steps') }}</flux:heading>
                <flux:text class="text-zinc-500 dark:text-zinc-400">
                    {{ __('From request to return — the whole loop is tracked so nothing goes missing.') }}
                </flux:text>
            </div>

            <ol class="relative grid gap-8 sm:grid-cols-3 sm:gap-6">
                <div class="absolute inset-x-0 top-5 hidden border-t-2 border-dashed border-zinc-200 sm:block dark:border-zinc-700" aria-hidden="true"></div>

                @foreach ([
                    ['1', 'arrows-right-left', 'Reserve your gear', 'Pick what you need from the catalogue — the system suggests matching accessories like shuttlecocks for rackets.'],
                    ['2', 'qr-code', 'Scan at the counter', 'Show your booking QR at the sports counter, hand over your student card as deposit and walk away with your gear.'],
                    ['3', 'clock', 'Return on time', 'Bring everything back within 2 hours and before 6:30 PM — late returns trigger a 14-day borrowing pause.'],
                ] as [$step, $icon, $title, $copy])
                    <li class="relative flex flex-col items-center gap-3 text-center">
                        <span class="z-10 inline-grid size-10 place-items-center rounded-full bg-gradient-to-br from-orange-600 to-amber-500 text-sm font-bold text-white shadow-md shadow-orange-600/30">
                            {{ $step }}
                        </span>
                        <span class="text-orange-600 dark:text-orange-400">
                            <flux:icon icon="{{ $icon }}" class="size-6" />
                        </span>
                        <div class="space-y-1">
                            <flux:heading>{{ __($title) }}</flux:heading>
                            <flux:text class="block leading-relaxed text-zinc-500 dark:text-zinc-400">{{ __($copy) }}</flux:text>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Rules CTA band --}}
    <section>
        <div class="flex flex-col items-start gap-4 rounded-2xl border border-zinc-200 bg-gradient-to-r from-orange-50 via-amber-50 to-orange-50 p-6 sm:flex-row sm:items-center dark:border-zinc-800 dark:from-orange-500/10 dark:via-amber-500/10 dark:to-orange-500/10">
            <span class="inline-grid size-11 shrink-0 place-items-center rounded-xl bg-orange-600/10 text-orange-600 dark:text-orange-400">
                <flux:icon icon="book-open" class="size-6" />
            </span>
            <div class="flex-1 space-y-1">
                <flux:heading>{{ __('Know before you go') }}</flux:heading>
                <flux:text class="leading-relaxed text-zinc-600 dark:text-zinc-300">
                    {{ __('Borrowing windows, deposit rules and penalty policy — read the house rules so your session never gets cut short.') }}
                </flux:text>
            </div>
            <flux:button variant="primary" :href="route('rules.index')" wire:navigate class="shrink-0 !bg-orange-600 !text-white hover:!bg-orange-500">
                {{ __('Read the rules') }}
                <flux:icon icon="arrow-right" variant="mini" class="size-4" />
            </flux:button>
        </div>
    </section>
</div>
