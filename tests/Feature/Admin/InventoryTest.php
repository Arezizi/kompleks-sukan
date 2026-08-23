<?php

use App\Models\EquipmentType;
use App\Models\Loan;
use App\Models\User;
use Livewire\Livewire;

test('guests and students cannot open the inventory manager', function () {
    $this->get('/admin/inventory')->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get('/admin/inventory')
        ->assertForbidden();
});

test('staff see gear rows with availability counts', function () {
    EquipmentType::factory()->create(['name' => 'Hockey Sticks', 'total_quantity' => 12]);

    Livewire::actingAs(User::factory()->jskkStaff()->create())
        ->test('pages::admin.inventory.index')
        ->assertOk()
        ->assertSee('Hockey Sticks')
        ->assertSee('12');
});

test('staff add a new gear type', function () {
    Livewire::actingAs(User::factory()->jskkStaff()->create())
        ->test('pages::admin.inventory.index')
        ->set('formOpen', true)
        ->set('name', 'Table Tennis Balls')
        ->set('totalQuantity', 40)
        ->call('save')
        ->assertHasNoErrors();

    expect(EquipmentType::query()->where('slug', 'table-tennis-balls')->exists())->toBeTrue();
});

test('duplicate gear names are rejected', function () {
    EquipmentType::factory()->create(['name' => 'Netballs', 'slug' => 'netballs']);

    Livewire::actingAs(User::factory()->jskkStaff()->create())
        ->test('pages::admin.inventory.index')
        ->set('formOpen', true)
        ->set('name', 'Netballs')
        ->call('save')
        ->assertHasErrors(['name']);
});

test('stock cannot drop below units already on loan or reserved', function () {
    $staff = User::factory()->jskkStaff()->create();
    $type = EquipmentType::factory()->create(['total_quantity' => 5]);
    Loan::factory()->withItem($type, 3)->create();

    Livewire::actingAs($staff)
        ->test('pages::admin.inventory.index')
        ->call('edit', $type->id)
        ->set('totalQuantity', 2)
        ->call('save')
        ->assertHasErrors(['totalQuantity']);

    expect($type->refresh()->total_quantity)->toBe(5);
});

test('gear types can be hidden from and restored to the catalog', function () {
    $type = EquipmentType::factory()->create(['is_active' => true]);

    Livewire::actingAs(User::factory()->jskkStaff()->create())
        ->test('pages::admin.inventory.index')
        ->call('toggleActive', $type->id);

    expect($type->refresh()->is_active)->toBeFalse();

    Livewire::test('pages::admin.inventory.index')
        ->call('toggleActive', $type->id);

    expect($type->refresh()->is_active)->toBeTrue();
});
