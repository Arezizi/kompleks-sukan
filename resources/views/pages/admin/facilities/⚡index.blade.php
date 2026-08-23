<?php

use App\Enums\FacilityStatus;
use App\Models\Facility;
use App\Models\FacilityStatusLog;
use App\Models\SiteSetting;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Facility status')] class extends Component {
    public bool $bannerActive = false;

    public string $bannerMessage = '';

    /**
     * Facility currently being re-statused, if any.
     */
    public ?int $editingId = null;

    public string $status = FacilityStatus::Open->value;

    public string $statusNote = '';

    public function mount(): void
    {
        $settings = SiteSetting::current();

        $this->bannerActive = $settings->weather_banner_active;
        $this->bannerMessage = $settings->weather_banner_message ?? '';
    }

    /**
     * Save the global weather banner shown on the public site.
     */
    public function saveBanner(): void
    {
        $validated = $this->validate([
            'bannerActive' => ['boolean'],
            'bannerMessage' => ['required_if:bannerActive,true', 'string', 'max:500'],
        ], [
            'bannerMessage.required_if' => __('Write the banner message before turning it on.'),
        ]);

        SiteSetting::current()->update([
            'weather_banner_active' => $validated['bannerActive'],
            'weather_banner_message' => $validated['bannerMessage'] === '' ? null : $validated['bannerMessage'],
        ]);

        session()->flash('status', __('Weather banner updated.'));
    }

    /**
     * Open the status editor for a facility.
     */
    public function editStatus(int $facilityId): void
    {
        /** @var Facility $facility */
        $facility = Facility::query()->findOrFail($facilityId);

        $this->editingId = $facility->id;
        $this->status = $facility->status->value;
        $this->statusNote = '';
    }

    /**
     * Apply the new status and write an audit trail entry.
     */
    public function applyStatus(): void
    {
        if ($this->editingId === null) {
            return;
        }

        $validated = $this->validate([
            'status' => ['required', Rule::enum(FacilityStatus::class)],
            'statusNote' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var Facility $facility */
        $facility = Facility::query()->findOrFail($this->editingId);

        $facility->update([
            'status' => FacilityStatus::from($validated['status']),
            'status_note' => $validated['statusNote'] === '' ? null : $validated['statusNote'],
            'status_changed_by' => auth()->id(),
        ]);

        FacilityStatusLog::query()->create([
            'facility_id' => $facility->id,
            'status' => FacilityStatus::from($validated['status']),
            'note' => $validated['statusNote'] === '' ? null : $validated['statusNote'],
            'changed_by' => auth()->id(),
        ]);

        $this->reset('editingId', 'statusNote');

        session()->flash('status', __('":name" is now :status.', ['name' => $facility->name, 'status' => $facility->status->label()]));
    }
}; ?>

@php
    $facilities = App\Models\Facility::query()->ordered()->with('statusChangedBy')->get();
@endphp

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Facility status') }}</flux:heading>
        <flux:text class="mt-2">
            {{ __('Keep the public status board honest — mark closures and maintenance as they happen.') }}
        </flux:text>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" data-test="flash-status">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    {{-- Weather banner --}}
    <form wire:submit="saveBanner" class="space-y-4 rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900" data-test="banner-form">
        <flux:heading>{{ __('Weather banner') }}</flux:heading>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <flux:switch label="Show banner on the public site" wire:model="bannerActive" data-test="banner-active" />

            <flux:button type="submit" variant="primary" size="sm" data-test="save-banner">
                {{ __('Save banner') }}
            </flux:button>
        </div>

        <flux:field>
            <flux:textarea label="Message" wire:model="bannerMessage" rows="2" placeholder="{{ __('e.g. Outdoor areas closed - heavy rain.') }}" data-test="banner-message" />
            <flux:error name="bannerMessage" />
        </flux:field>
    </form>

    {{-- Facilities --}}
    <div class="space-y-3" data-test="facility-status-list">
        <flux:heading>{{ __('All facilities') }}</flux:heading>

        @foreach ($facilities as $facility)
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900" wire:key="facility-{{ $facility->id }}" data-test="facility-row">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0 space-y-0.5">
                        <p class="truncate text-sm font-semibold">{{ $facility->name }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $facility->category->label() }}
                            @if ($facility->location !== null && filled($facility->location))
                                · {{ $facility->location }}
                            @endif
                        </p>

                        @if ($facility->status_note !== null && filled($facility->status_note))
                            <p class="text-xs italic text-zinc-500 dark:text-zinc-400">“{{ $facility->status_note }}”</p>
                        @endif

                        @if ($facility->statusChangedBy !== null)
                            <p class="text-xs text-zinc-400 dark:text-zinc-500">
                                {{ __('Last changed by :name', ['name' => $facility->statusChangedBy->name]) }}
                            </p>
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:badge color="{{ $facility->status->color() }}" variant="outline" data-test="facility-badge">
                            {{ $facility->status->label() }}
                        </flux:badge>

                        @if ($editingId === $facility->id)
                            <flux:button size="sm" variant="subtle" wire:click="$set('editingId', null)" data-test="cancel-status-button">
                                {{ __('Cancel') }}
                            </flux:button>
                        @else
                            <flux:button size="sm" wire:click="editStatus({{ $facility->id }})" data-test="change-status-button">
                                {{ __('Change') }}
                            </flux:button>
                        @endif
                    </div>
                </div>

                @if ($editingId === $facility->id)
                    <form wire:submit="applyStatus" class="mt-3 space-y-3 rounded-xl border border-dashed border-zinc-300 p-4 dark:border-zinc-700" data-test="status-form">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <flux:field>
                                <flux:select label="New status" wire:model="status" data-test="status-select">
                                    @foreach (FacilityStatus::cases() as $case)
                                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                                    @endforeach
                                </flux:select>
                            </flux:field>

                            <flux:field>
                                <flux:input label="Note (optional)" wire:model="statusNote" placeholder="{{ __('e.g. Net replacement until Friday.') }}" data-test="status-note" />
                            </flux:field>
                        </div>

                        <div class="flex justify-end">
                            <flux:button type="submit" variant="primary" size="sm" data-test="apply-status-button">
                                {{ __('Apply status') }}
                            </flux:button>
                        </div>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</section>
