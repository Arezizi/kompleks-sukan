<?php

use App\Models\EquipmentType;
use App\Models\Facility;
use App\Models\SiteSetting;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::public')] class extends Component {
    public Facility $facility;

    public function mount(string $slug): void
    {
        $facility = Facility::query()->visible()->where('slug', $slug)->first();

        abort_unless($facility !== null, 404);

        $this->facility = $facility->load([
            'statusLogs' => fn ($query) => $query->latest()->with('changedBy')->limit(5),
        ]);
    }
}; ?>

@php
    $statusNote = $facility->status_note;
    $lastChanged = $facility->statusLogs->first();

    $settings = SiteSetting::current();

    $now = now();

    $openingTime = $settings->opening_time ?? '17:00:00';
    $closingTime = $settings->closing_time ?? '18:50:00';

    $opensAt = Carbon::parse($openingTime)->setTimezone($now->getTimezone())->setDateFrom($now);
    $closesAt = Carbon::parse($closingTime)->setTimezone($now->getTimezone())->setDateFrom($now);
    $isPeak = $now->greaterThanOrEqualTo($opensAt) && $now->lessThanOrEqualTo($closesAt);

    $bannerImage = filled($facility->image_path) && is_file(public_path($facility->image_path))
        ? asset($facility->image_path)
        : null;

    $gear = $facility->equipmentTypes()
        ->where('equipment_types.is_active', true)
        ->get()
        ->map(fn (EquipmentType $type) => (object) [
            'model' => $type,
            'available' => $type->availableQuantity(),
        ]);
@endphp

<div class="mx-auto max-w-5xl space-y-8">
    <flux:button variant="ghost" size="sm" :href="route('home').'#status-board'" wire:navigate icon-leading="arrow-left" data-test="back-to-board">
        {{ __('Back to status board') }}
    </flux:button>

    {{-- Visual banner --}}
    <header class="relative isolate overflow-hidden rounded-3xl shadow-xl shadow-indigo-950/20 {{ $bannerImage !== null ? '' : 'bg-gradient-to-br from-indigo-950 via-indigo-900 to-sky-950' }}" data-test="facility-banner">
        @if ($bannerImage !== null)
            <img src="{{ $bannerImage }}" alt="{{ __('Photo of :name', ['name' => $facility->name]) }}"
                class="absolute inset-0 size-full object-cover" loading="lazy"
                data-test="facility-banner-image">
            <div class="absolute inset-0 bg-gradient-to-t from-indigo-950 via-indigo-950/70 to-sky-950/25" aria-hidden="true"></div>
        @else
            <svg class="pointer-events-none absolute inset-0 size-full opacity-[0.14]" viewBox="0 0 800 420" preserveAspectRatio="xMidYMid slice" aria-hidden="true" fill="none" stroke="white" stroke-width="2">
                <rect x="48" y="48" width="704" height="324" rx="10" />
                <line x1="400" y1="48" x2="400" y2="372" />
                <circle cx="400" cy="210" r="72" />
                <rect x="48" y="130" width="90" height="160" />
                <rect x="662" y="130" width="90" height="160" />
            </svg>
            <div class="pointer-events-none absolute -top-20 -right-16 size-72 rounded-full bg-sky-500/20 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-24 -left-12 size-64 rounded-full bg-emerald-500/15 blur-3xl" aria-hidden="true"></div>
        @endif

        <div class="relative flex flex-col gap-6 px-6 py-10 sm:px-10 lg:flex-row lg:items-end lg:justify-between">
            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold text-sky-200 backdrop-blur">
                        <flux:icon icon="{{ $facility->category->value === 'indoor' ? 'building-library' : 'sun' }}" variant="mini" class="size-3.5" />
                        {{ $facility->category->label() }}
                    </span>
                    @if ($facility->location)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-medium text-indigo-100 backdrop-blur">
                            <flux:icon icon="map-pin" variant="mini" class="size-3.5" />
                            {{ $facility->location }}
                        </span>
                    @endif
                </div>

                <flux:heading level="1" size="xl" class="!text-white">{{ $facility->name }}</flux:heading>

                @if ($facility->description)
                    <p class="max-w-2xl leading-relaxed text-indigo-100/90">{{ $facility->description }}</p>
                @endif
            </div>

            <div class="shrink-0 space-y-2 rounded-2xl border border-white/15 bg-white/[0.07] p-4 backdrop-blur-md">
                <p class="text-xs font-semibold tracking-wider text-indigo-200 uppercase">Current status</p>
                <x-facility-status-badge :status="$facility->status" class="!px-3 !py-1.5 !text-sm" />
                @if ($lastChanged)
                    <p class="pt-1 text-xs text-indigo-200/70">
                        {{ __('Updated :time', ['time' => $lastChanged->created_at->timezone(config('app.timezone'))->format('j M, g:i A')]) }}
                    </p>
                @endif
            </div>
        </div>
    </header>

    @if ($statusNote)
        <div class="flex items-start gap-4 rounded-2xl border p-5 {{ $facility->status->value === 'open' ? 'border-emerald-300/60 bg-emerald-50 dark:border-emerald-400/20 dark:bg-emerald-500/10' : ($facility->status->value === 'maintenance' ? 'border-amber-300/60 bg-amber-50 dark:border-amber-400/20 dark:bg-amber-500/10' : 'border-red-300/60 bg-red-50 dark:border-red-400/20 dark:bg-red-500/10') }}">
            <span class="inline-grid size-10 shrink-0 place-items-center rounded-xl bg-black/5 dark:bg-white/10">
                <flux:icon icon="information-circle" class="size-5" />
            </span>
            <div>
                <flux:heading>{{ __('Status note from staff') }}</flux:heading>
                <flux:text class="mt-1 leading-relaxed">{{ $statusNote }}</flux:text>
            </div>
        </div>
    @endif

    <div class="grid gap-8 lg:grid-cols-[1.6fr_1fr]">
        <div class="space-y-8">
            {{-- Rules --}}
            <section class="rounded-3xl border border-zinc-200 bg-white p-6 sm:p-8 dark:border-zinc-800 dark:bg-zinc-900" data-test="facility-rules">
                <h2 class="flex items-center gap-2 pb-4 text-lg font-bold tracking-tight">
                    <flux:icon icon="book-open" class="size-5 text-indigo-600 dark:text-indigo-400" />
                    {{ __('House rules') }}
                </h2>

                @if (filled($facility->rules))
                    {!! nl2br(e($facility->rules)) !!}
                @else
                    <p class="text-zinc-500 dark:text-zinc-400">
                        {{ __('No special rules — general complex rules apply. See the borrowing rules page for details.') }}
                    </p>
                @endif
            </section>

            {{-- Equipment quick links --}}
            <section class="rounded-3xl border border-zinc-200 bg-white p-6 sm:p-8 dark:border-zinc-800 dark:bg-zinc-900" data-test="facility-equipment">
                <h2 class="flex items-center gap-2 pb-1 text-lg font-bold tracking-tight">
                    <flux:icon icon="bolt" class="size-5 text-emerald-600 dark:text-emerald-400" />
                    {{ __('Equipment available here') }}
                </h2>
                <p class="pb-5 text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">
                    {{ __('Borrow these at the sports counter for your session at :name — availability updates live.', ['name' => $facility->name]) }}
                </p>

                @if ($gear->isEmpty())
                    <div class="flex flex-col items-start gap-4 rounded-2xl border border-dashed border-zinc-300 p-6 sm:flex-row sm:items-center dark:border-zinc-700">
                        <p class="flex-1 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('No dedicated gear is linked to this facility yet — the full catalog is still yours to borrow from.') }}
                        </p>
                        <flux:button size="sm" variant="subtle" :href="route('gear.index')" wire:navigate class="shrink-0" data-test="browse-catalog-link">
                            {{ __('Browse catalog') }}
                        </flux:button>
                    </div>
                @else
                    <ul class="space-y-3">
                        @foreach ($gear as $item)
                            <li class="flex flex-wrap items-center gap-4 rounded-2xl border border-zinc-200 p-4 transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:border-zinc-700 dark:hover:bg-zinc-800/40" data-test="equipment-row" wire:key="gear-{{ $item->model->id }}">
                                <span class="inline-grid size-11 shrink-0 place-items-center rounded-xl bg-emerald-600/10 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300">
                                    <flux:icon icon="cube" class="size-6" />
                                </span>

                                <div class="min-w-0 flex-1 space-y-2">
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <p class="truncate text-sm font-semibold">{{ $item->model->name }}</p>
                                        @if ($item->available > 0)
                                            <flux:badge size="sm" class="!bg-emerald-500/10 !text-emerald-600 ring-1 ring-inset ring-emerald-500/30 dark:!text-emerald-400" data-test="equipment-available">
                                                <flux:icon icon="check-circle" variant="solid" class="size-3.5" />
                                                {{ __(':n available', ['n' => $item->available]) }}
                                            </flux:badge>
                                        @else
                                            <flux:badge size="sm" class="!bg-red-500/10 !text-red-600 ring-1 ring-inset ring-red-500/30 dark:!text-red-400" data-test="equipment-out-of-stock">
                                                <flux:icon icon="x-circle" variant="solid" class="size-3.5" />
                                                {{ __('Out of stock') }}
                                            </flux:badge>
                                        @endif
                                    </div>

                                    <div class="max-w-xs">
                                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                            <div class="h-full rounded-full {{ $item->available > 0 ? 'bg-gradient-to-r from-emerald-500 to-teal-400' : 'bg-red-400' }}"
                                                style="width: {{ $item->model->total_quantity > 0 ? round($item->available / $item->model->total_quantity * 100) : 0 }}%">
                                            </div>
                                        </div>
                                        <p class="mt-1 text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                                            {{ __('of :total total', ['total' => $item->model->total_quantity]) }}
                                        </p>
                                    </div>
                                </div>

                                @if ($item->available > 0)
                                    <flux:button size="sm" variant="primary" class="shrink-0" :href="route('gear.index', ['reserve' => $item->model->id])" wire:navigate data-test="reserve-link">
                                        {{ __('Reserve') }}
                                        <flux:icon icon="arrow-right" variant="mini" class="size-3.5" />
                                    </flux:button>
                                @else
                                    <flux:button size="sm" variant="ghost" disabled class="shrink-0">
                                        {{ __('Unavailable') }}
                                    </flux:button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <div class="space-y-8">
            {{-- Operating hours --}}
            <section class="rounded-3xl border border-zinc-200 bg-white p-6 sm:p-8 dark:border-zinc-800 dark:bg-zinc-900" data-test="operating-hours">
                <h2 class="flex items-center gap-2 pb-4 text-lg font-bold tracking-tight">
                    <flux:icon icon="clock" class="size-5 text-sky-600 dark:text-sky-400" />
                    {{ __('Operating hours') }}
                </h2>

                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-2xl font-bold tabular-nums tracking-tight whitespace-nowrap" data-test="hours-range">
                            {{ $opensAt->format('g:i A') }} – {{ $closesAt->format('g:i A') }}
                        </p>
                        <p class="mt-1 text-xs font-medium tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('Daily') }}</p>
                    </div>

                    @if ($isPeak)
                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-amber-500/10 px-3 py-1.5 text-xs font-semibold text-amber-600 ring-1 ring-inset ring-amber-500/30 dark:text-amber-400" data-test="peak-badge">
                            <span class="relative flex size-2">
                                <span class="absolute inline-flex size-full animate-ping rounded-full bg-amber-500 opacity-75"></span>
                                <span class="relative inline-flex size-2 rounded-full bg-amber-500"></span>
                            </span>
                            {{ __('Peak hours') }}
                        </span>
                    @else
                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-zinc-100 px-3 py-1.5 text-xs font-semibold text-zinc-600 ring-1 ring-inset ring-zinc-300 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-600" data-test="off-peak-badge">
                            <flux:icon icon="moon" variant="mini" class="size-3.5" />
                            {{ __('Off-peak hours') }}
                        </span>
                    @endif
                </div>

                <p class="border-t border-zinc-100 pt-4 text-xs leading-relaxed text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
                    @if ($isPeak)
                        {{ __('The counter is open right now — all gear must be back by :time.', ['time' => $closesAt->format('g:i A')]) }}
                    @else
                        {{ __('Opens again today at :time — plan gear pickup around the evening session.', ['time' => $opensAt->format('g:i A')]) }}
                    @endif
                </p>
            </section>

            {{-- Details --}}
            <section class="rounded-3xl border border-zinc-200 bg-white p-6 sm:p-8 dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="pb-4 text-lg font-bold tracking-tight">{{ __('At a glance') }}</h2>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Type') }}</dt>
                        <dd class="font-semibold">{{ ucfirst($facility->category->value) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Location') }}</dt>
                        <dd class="text-right font-semibold">{{ $facility->location ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Capacity') }}</dt>
                        <dd class="font-semibold">{{ $facility->capacity ? __('up to :n people', ['n' => number_format($facility->capacity)]) : __('Flexible') }}</dd>
                    </div>
                </dl>
            </section>

            {{-- Status history --}}
            <section class="rounded-3xl border border-zinc-200 bg-white p-6 sm:p-8 dark:border-zinc-800 dark:bg-zinc-900" data-test="status-history">
                <h2 class="pb-4 text-lg font-bold tracking-tight">{{ __('Recent status changes') }}</h2>

                @if ($facility->statusLogs->isEmpty())
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No recorded changes yet.') }}</p>
                @else
                    <ol class="relative space-y-5 border-l border-zinc-200 pl-5 dark:border-zinc-700">
                        @foreach ($facility->statusLogs as $log)
                            <li class="relative">
                                <span class="absolute top-1 -left-[26px] size-2.5 rounded-full ring-4 ring-white dark:ring-zinc-900 {{ ['open' => 'bg-emerald-500', 'maintenance' => 'bg-amber-500', 'closed' => 'bg-red-500'][$log->status->value] }}"></span>
                                <p class="flex items-center gap-2 text-sm font-semibold">
                                    {{ $log->status->label() }}
                                    @if ($log->note)
                                        <span class="font-normal text-zinc-500 dark:text-zinc-400">— {{ $log->note }}</span>
                                    @endif
                                </p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ Carbon::parse($log->created_at)->format('j M Y, g:i A') }}@if ($log->changedBy) · {{ __('by :name', ['name' => $log->changedBy->name]) }}@endif
                                </p>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>
    </div>
</div>
