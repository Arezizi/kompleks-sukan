<?php

use App\Models\EquipmentType;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Inventory')] class extends Component {
    /**
     * Whether the add/edit modal is visible.
     */
    public bool $formOpen = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public int $totalQuantity = 10;

    public bool $isActive = true;

    /**
     * Create a new gear type or update the one being edited.
     */
    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'totalQuantity' => ['required', 'integer', 'min:0', 'max:10000'],
            'isActive' => ['boolean'],
        ]);

        $slug = Str::slug($validated['name']);

        $slugTaken = EquipmentType::query()
            ->where('slug', $slug)
            ->when($this->editingId !== null, fn ($query) => $query->whereKeyNot($this->editingId))
            ->exists();

        if ($slugTaken) {
            throw ValidationException::withMessages([
                'name' => __('A gear type with a similar name already exists.'),
            ]);
        }

        $attributes = [
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] === '' ? null : $validated['description'],
            'total_quantity' => $validated['totalQuantity'],
            'is_active' => $validated['isActive'],
        ];

        if ($this->editingId !== null) {
            /** @var EquipmentType $type */
            $type = EquipmentType::query()->findOrFail($this->editingId);

            $outstanding = $type->onLoanQuantity() + $type->reservedQuantity();

            if ($attributes['total_quantity'] < $outstanding) {
                throw ValidationException::withMessages([
                    'totalQuantity' => __('Cannot go below :n units already on loan or reserved.', ['n' => $outstanding]),
                ]);
            }

            $type->update($attributes);

            session()->flash('status', __('Gear type updated.'));
        } else {
            EquipmentType::query()->create($attributes);

            session()->flash('status', __('Gear type added to inventory.'));
        }

        $this->closeForm();
    }

    /**
     * Open the modal pre-filled for editing the given type.
     */
    public function edit(int $typeId): void
    {
        /** @var EquipmentType $type */
        $type = EquipmentType::query()->findOrFail($typeId);

        $this->editingId = $type->id;
        $this->name = $type->name;
        $this->description = $type->description ?? '';
        $this->totalQuantity = $type->total_quantity;
        $this->isActive = $type->is_active;
        $this->formOpen = true;
    }

    /**
     * Activate or retire the given gear type.
     */
    public function toggleActive(int $typeId): void
    {
        /** @var EquipmentType $type */
        $type = EquipmentType::query()->findOrFail($typeId);

        $type->update(['is_active' => ! $type->is_active]);

        session()->flash('status', $type->is_active
            ? __('":name" is now listed in the catalog.', ['name' => $type->name])
            : __('":name" is now hidden from the catalog.', ['name' => $type->name]));
    }

    /**
     * Reset and hide the add/edit modal.
     */
    public function closeForm(): void
    {
        $this->reset('formOpen', 'editingId', 'name', 'description', 'totalQuantity', 'isActive');
        $this->totalQuantity = 10;
        $this->isActive = true;
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ __('Inventory') }}</flux:heading>
            <flux:text class="mt-2">
                {{ __('Manage the gear categories students can borrow and how many units exist.') }}
            </flux:text>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="$set('formOpen', true)" data-test="add-gear-button">
            {{ __('Add gear type') }}
        </flux:button>
    </div>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" data-test="flash-status">
            <flux:callout.text>{{ session('status') }}</flux:callout.text>
        </flux:callout>
    @endif

    @if ($errors->any())
        <flux:callout variant="danger" icon="exclamation-triangle" data-test="inventory-errors">
            <flux:callout.text>{{ $errors->first() }}</flux:callout.text>
        </flux:callout>
    @endif

    @php
        $types = EquipmentType::query()->orderBy('name')->get();

        $onLoan = App\Models\LoanItem::query()
            ->whereHas('loan', fn ($query) => $query->whereNull('returned_at'))
            ->get(['equipment_type_id', 'quantity'])
            ->groupBy('equipment_type_id')
            ->map(fn ($group) => (int) $group->sum('quantity'));

        $reserved = App\Models\ReservationItem::query()
            ->whereHas('reservation', fn ($query) => $query->whereIn('status', [App\Enums\ReservationStatus::Pending, App\Enums\ReservationStatus::Approved]))
            ->get(['equipment_type_id', 'quantity'])
            ->groupBy('equipment_type_id')
            ->map(fn ($group) => (int) $group->sum('quantity'));
    @endphp

    <div class="space-y-3" data-test="inventory-list">
        @forelse ($types as $type)
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900" wire:key="gear-{{ $type->id }}" data-test="equipment-row">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0 space-y-0.5">
                        <p class="truncate text-sm font-semibold">{{ $type->name }}</p>
                        <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $type->description }}</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-4 text-xs tabular-nums">
                        <span>{{ __('Total') }} <strong>{{ $type->total_quantity }}</strong></span>
                        <span class="text-sky-600 dark:text-sky-400">{{ __('On loan') }} <strong>{{ $onLoan[$type->id] ?? 0 }}</strong></span>
                        <span class="text-amber-600 dark:text-amber-400">{{ __('Reserved') }} <strong>{{ $reserved[$type->id] ?? 0 }}</strong></span>
                        <span class="{{ ($type->total_quantity - ($onLoan[$type->id] ?? 0) - ($reserved[$type->id] ?? 0)) > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ __('Available') }} <strong>{{ max(0, $type->total_quantity - ($onLoan[$type->id] ?? 0) - ($reserved[$type->id] ?? 0)) }}</strong>
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($type->is_active)
                            <flux:badge color="green" data-test="active-badge">{{ __('Listed') }}</flux:badge>
                        @else
                            <flux:badge color="zinc" data-test="inactive-badge">{{ __('Hidden') }}</flux:badge>
                        @endif

                        <flux:button size="sm" wire:click="edit({{ $type->id }})" data-test="edit-button">
                            {{ __('Edit') }}
                        </flux:button>
                        <flux:button size="sm" variant="subtle" wire:click="toggleActive({{ $type->id }})" data-test="toggle-active-button">
                            {{ $type->is_active ? __('Hide') : __('List') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        @empty
            <p class="rounded-2xl border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                {{ __('No gear types yet.') }}
            </p>
        @endforelse
    </div>

    <flux:modal wire:model="formOpen" name="equipment-form" class="w-full max-w-md" data-test="equipment-form">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId === null ? __('Add gear type') : __('Edit gear type') }}</flux:heading>

            <flux:field>
                <flux:input label="Name" wire:model="name" data-test="gear-name" />
            </flux:field>

            <flux:field>
                <flux:textarea label="Description" wire:model="description" rows="2" />
            </flux:field>

            <flux:field>
                <flux:input label="Total quantity" type="number" min="0" wire:model="totalQuantity" data-test="gear-total" />
            </flux:field>

            <flux:switch label="Listed in public catalog" wire:model="isActive" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" data-test="save-gear">
                    {{ $editingId === null ? __('Add') : __('Save changes') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</section>
