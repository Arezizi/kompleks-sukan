<?php

use App\Enums\IncidentSeverity;
use App\Enums\IncidentType;
use App\Models\Facility;
use App\Models\Incident;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Incident log')] class extends Component {
    public string $title = '';

    public string $type = IncidentType::Injury->value;

    public string $severity = IncidentSeverity::Low->value;

    /** @var array<int, string>|null */
    public ?array $facilityOptions = null;

    public string $facilityId = '';

    public string $description = '';

    public string $actionTaken = '';

    /**
     * Record a new incident report.
     */
    public function log(): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(IncidentType::class)],
            'severity' => ['required', Rule::enum(IncidentSeverity::class)],
            'facilityId' => ['nullable', 'exists:facilities,id'],
            'description' => ['required', 'string', 'max:5000'],
            'actionTaken' => ['nullable', 'string', 'max:5000'],
        ]);

        Incident::query()->create([
            'reported_by' => auth()->id(),
            'facility_id' => $validated['facilityId'] !== '' ? (int) $validated['facilityId'] : null,
            'loan_id' => null,
            'type' => IncidentType::from($validated['type']),
            'severity' => IncidentSeverity::from($validated['severity']),
            'title' => $validated['title'],
            'description' => $validated['description'],
            'action_taken' => $validated['actionTaken'] === '' ? null : $validated['actionTaken'],
            'occurred_at' => now(),
        ]);

        $this->reset('title', 'description', 'actionTaken');
        $this->type = IncidentType::Injury->value;
        $this->severity = IncidentSeverity::Low->value;
        $this->facilityId = '';

        session()->flash('status', __('Incident recorded.'));
    }
}; ?>

@php
    $incidents = App\Models\Incident::query()
        ->with(['reporter', 'facility'])
        ->orderByDesc('occurred_at')
        ->limit(50)
        ->get();

    if ($facilityOptions === null) {
        $facilityOptions = App\Models\Facility::query()->ordered()->pluck('name', 'id')->all();
    }
@endphp

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Incident log') }}</flux:heading>
        <flux:text class="mt-2">
            {{ __('Record injuries, first aid and damage reports from around the complex.') }}
        </flux:text>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" data-test="flash-status">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    {{-- Log a new incident --}}
    <form wire:submit="log" class="space-y-4 rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900" data-test="incident-form">
        <flux:heading>{{ __('Log a new incident') }}</flux:heading>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:field class="lg:col-span-2">
                <flux:input label="Title" wire:model="title" data-test="incident-title" />
                <flux:error name="title" />
            </flux:field>

            <flux:field>
                <flux:select label="Type" wire:model="type" data-test="incident-type">
                    @foreach (IncidentType::cases() as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="type" />
            </flux:field>

            <flux:field>
                <flux:select label="Severity" wire:model="severity" data-test="incident-severity">
                    @foreach (IncidentSeverity::cases() as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="severity" />
            </flux:field>

            <flux:field class="sm:col-span-2 lg:col-span-2">
                <flux:select label="Facility (optional)" wire:model="facilityId">
                    <option value="">{{ __('— None —') }}</option>
                    @foreach ($facilityOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="facilityId" />
            </flux:field>

            <div class="sm:col-span-2 space-y-2">
                <flux:field>
                    <flux:textarea label="What happened" wire:model="description" rows="3" data-test="incident-description" />
                    <flux:error name="description" />
                </flux:field>
                <flux:field>
                    <flux:textarea label="Action taken (optional)" wire:model="actionTaken" rows="2" data-test="incident-action" />
                    <flux:error name="actionTaken" />
                </flux:field>
            </div>
        </div>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" icon="clipboard-document-check" data-test="save-incident">
                {{ __('Record incident') }}
            </flux:button>
        </div>
    </form>

    {{-- Recent incidents --}}
    <div class="space-y-3" data-test="incident-list">
        <flux:heading>{{ __('Recent incidents') }}</flux:heading>

        @forelse ($incidents as $incident)
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900" wire:key="incident-{{ $incident->id }}" data-test="incident-row">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0 space-y-0.5">
                        <p class="text-sm font-semibold">
                            {{ $incident->title }}
                            <span class="ml-1 text-xs font-normal text-zinc-500 dark:text-zinc-400">({{ $incident->type->label() }})</span>
                        </p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $incident->occurred_at->format('d M Y, g:i A') }}
                            @if ($incident->facility !== null)
                                · {{ $incident->facility->name }}
                            @endif
                            @if ($incident->reporter !== null)
                                · {{ __('reported by :name', ['name' => $incident->reporter->name]) }}
                            @endif
                        </p>
                    </div>

                    @if ($incident->resolved_at !== null)
                        <flux:badge color="green">{{ __('Resolved') }}</flux:badge>
                    @else
                        <flux:badge color="{{ $incident->severity->color() }}" data-test="severity-badge">
                            {{ $incident->severity->label() }}
                        </flux:badge>
                    @endif
                </div>

                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $incident->description }}</p>

                @if ($incident->action_taken !== null && filled($incident->action_taken))
                    <p class="mt-1 text-xs italic text-zinc-500 dark:text-zinc-400">
                        {{ __('Action taken') }}: {{ $incident->action_taken }}
                    </p>
                @endif
            </div>
        @empty
            <p class="rounded-2xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                {{ __('No incidents logged yet.') }}
            </p>
        @endforelse
    </div>
</section>
