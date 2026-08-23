<?php

use App\Enums\ReservationStatus;
use App\Models\EquipmentType;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('guests cannot open my bookings', function () {
    $this->get('/reservations')->assertRedirect(route('login', absolute: false));
});

test('students see their bookings page', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/reservations')
        ->assertOk()
        ->assertSee('My bookings');
});

test('students reserve gear from the catalog modal', function () {
    $student = User::factory()->create();
    $type = EquipmentType::factory()->create(['total_quantity' => 4]);

    $component = Livewire::actingAs($student)
        ->test('pages::gear.index')
        ->call('startReserve', $type->id)
        ->assertSet('showReserve', true)
        ->set("quantities.{$type->id}", 2)
        ->call('submitReservation');

    $reservation = $student->reservations()->first();

    expect($reservation)->not->toBeNull()
        ->and($reservation->status)->toBe(ReservationStatus::Pending)
        ->and($reservation->items->first()->quantity)->toBe(2);

    $component->assertRedirect(route('reservations.index', absolute: false));
});

test('the reserve modal surfaces stock errors without creating anything', function () {
    $student = User::factory()->create();
    $type = EquipmentType::factory()->create(['total_quantity' => 1]);

    Livewire::actingAs($student)
        ->test('pages::gear.index')
        ->call('startReserve', $type->id)
        ->set("quantities.{$type->id}", 50)
        ->call('submitReservation')
        ->assertHasErrors(['reservation']);

    expect($student->reservations()->count())->toBe(0);
});

test('accessory suggestions toggle extra lines into the request', function () {
    $student = User::factory()->create();
    $racket = EquipmentType::factory()->create(['total_quantity' => 6]);
    $shuttle = EquipmentType::factory()->create(['total_quantity' => 12]);
    $racket->suggestedAccessories()->attach($shuttle, ['prompt' => 'Grab a tube of shuttlecocks']);

    Livewire::actingAs($student)
        ->test('pages::gear.index')
        ->call('startReserve', $racket->id)
        ->assertSee($shuttle->name)
        ->call('toggleAccessory', $shuttle->id)
        ->call('submitReservation');

    $reservation = $student->reservations()->first();

    expect($reservation->items)->toHaveCount(2)
        ->and($reservation->items->pluck('equipment_type_id'))->toContain($shuttle->id);
});

test('approved reservations show their pickup code and can be cancelled', function () {
    $student = User::factory()->create();
    $type = EquipmentType::factory()->create(['total_quantity' => 4]);

    $service = app(ReservationService::class);
    $reservation = $service->create($student, [$type->id => 1]);
    $service->decide($reservation, User::factory()->jskkStaff()->create(), true);

    $component = Livewire::actingAs($student)
        ->test('pages::reservations.index')
        ->assertSee($reservation->token)
        ->assertSee(ReservationStatus::Approved->label());

    expect($type->availableQuantity())->toBe(3); // Approved holds stock.

    $component->call('cancel', $reservation->id);

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Cancelled)
        ->and($type->availableQuantity())->toBe(4);
});

test('students cannot cancel someone else’s reservation', function () {
    $other = Reservation::factory()->create();

    expect(fn () => Livewire::actingAs(User::factory()->create())
        ->test('pages::reservations.index')
        ->call('cancel', $other->id))->toThrow(ModelNotFoundException::class);

    expect($other->refresh()->status)->toBe(ReservationStatus::Pending);
});
