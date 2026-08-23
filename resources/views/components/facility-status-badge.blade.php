@props([
    'status',
])

@php
    $styles = match ($status) {
        \App\Enums\FacilityStatus::Open => '!bg-emerald-500/10 !text-emerald-600 ring-emerald-500/30 dark:!text-emerald-400',
        \App\Enums\FacilityStatus::Maintenance => '!bg-amber-500/10 !text-amber-600 ring-amber-500/30 dark:!text-amber-400',
        \App\Enums\FacilityStatus::Closed => '!bg-red-500/10 !text-red-600 ring-red-500/30 dark:!text-red-400',
    };

    $icons = [
        'open' => 'check-circle',
        'maintenance' => 'wrench-screwdriver',
        'closed' => 'x-circle',
    ];
@endphp

<flux:badge size="sm" class="ring-1 ring-inset {{ $styles }}" {{ $attributes->except('size') }} data-test="facility-status-badge">
    <flux:icon icon="{{ $icons[$status->value] }}" variant="solid" class="size-3.5" />
    {{ $status->label() }}
</flux:badge>
