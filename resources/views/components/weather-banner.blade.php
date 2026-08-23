@php
    $settings = \App\Models\SiteSetting::current();
@endphp

@if ($settings->weather_banner_active && filled($settings->weather_banner_message))
    <div
        {{ $attributes->merge(['class' => 'border-b border-amber-400/40 bg-gradient-to-r from-amber-100 via-orange-100 to-amber-100 dark:border-amber-400/20 dark:from-amber-500/15 dark:via-orange-500/10 dark:to-amber-500/15']) }}
        data-test="weather-banner"
        role="status"
    >
        <div class="mx-auto flex w-full max-w-7xl items-center gap-3 px-6 py-3 lg:px-8">
            <span class="inline-grid size-8 shrink-0 place-items-center rounded-lg bg-amber-500/20 text-amber-700 dark:text-amber-300">
                <flux:icon icon="cloud" class="size-5" />
            </span>
            <div class="min-w-0">
                <p class="text-sm font-bold text-amber-900 dark:text-amber-200">{{ __('Weather notice') }}</p>
                <p class="truncate text-sm text-amber-800 dark:text-amber-200/80">{{ $settings->weather_banner_message }}</p>
            </div>
        </div>
    </div>
@endif
