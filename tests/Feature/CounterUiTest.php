<?php

use App\Enums\ReservationStatus;
use App\Models\EquipmentType;
use App\Models\Loan;
use App\Models\Penalty;
use App\Models\User;
use App\Services\PenaltyService;
use App\Services\ReservationService;
use Livewire\Livewire;

function counterStaff(): User
{
    return User::factory()->jskkStaff()->create();
}

test('students cannot open counter pages', function () {
    $this->actingAs(User::factory()->create());

    foreach (['/counter', '/counter/checkout', '/counter/returns'] as $uri) {
        $this->get($uri)->assertForbidden();
    }
});

test('staff see the counter desk with live queues', function () {
    $staff = counterStaff();
    $type = EquipmentType::factory()->create();

    $reservation = app(ReservationService::class)->create(User::factory()->create(), [$type->id => 1]);

    $this->actingAs($staff)
        ->get('/counter')
        ->assertOk()
        ->assertSee($reservation->user->name);

    Livewire::actingAs($staff)
        ->test('pages::counter.dashboard')
        ->assertSee('Pending approvals');
});

test('staff approve and reject requests from the desk', function () {
    $staff = counterStaff();
    $service = app(ReservationService::class);
    $type = EquipmentType::factory()->create(['total_quantity' => 3]);

    $reservation = $service->create(User::factory()->create(), [$type->id => 1]);

    Livewire::actingAs($staff)
        ->test('pages::counter.dashboard')
        ->call('decide', $reservation->id, true)
        ->assertSee('approved');

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Approved);
});

test('staff check out an approved reservation by pickup code', function () {
    $staff = counterStaff();
    $student = User::factory()->create();
    $service = app(ReservationService::class);
    $type = EquipmentType::factory()->create(['total_quantity' => 3]);

    $reservation = $service->create($student, [$type->id => 2]);
    $service->decide($reservation, $staff, true);

    Livewire::actingAs($staff)
        ->test('pages::counter.checkout')
        ->set('code', $reservation->token)
        ->call('findReservation')
        ->assertSet('foundReservationId', $reservation->id)
        ->assertSee($student->name)
        ->call('confirmPickup')
        ->assertSee('due by');

    expect($student->loans()->count())->toBe(1)
        ->and($reservation->refresh()->status)->toBe(ReservationStatus::Fulfilled);
});

test('wrong pickup codes are refused inline', function () {
    Livewire::actingAs(counterStaff())
        ->test('pages::counter.checkout')
        ->set('code', 'bogus-code')
        ->call('findReservation')
        ->assertSet('foundReservationId', null)
        ->assertHasErrors(['code']);
});

test('walk-in loans are created from a matric lookup', function () {
    $staff = counterStaff();
    $student = User::factory()->create(['matric_no' => 'PMS555001']);
    $type = EquipmentType::factory()->create(['total_quantity' => 6]);

    Livewire::actingAs($staff)
        ->test('pages::counter.checkout')
        ->set('matric', 'pms555001')
        ->call('findStudent')
        ->assertSet('walkInStudentId', $student->id)
        ->assertSee($student->name)
        ->set("quantities.{$type->id}", 2)
        ->call('confirmWalkIn')
        ->assertSee('Checked out to');

    $loan = $student->loans()->first();

    expect($loan)->not->toBeNull()
        ->and($loan->items->first()->quantity)->toBe(2);
});

test('non-students cannot be given walk-in loans', function () {
    $staffUser = User::factory()->jskkStaff()->create(['matric_no' => 'STAFF0001']);

    Livewire::actingAs(counterStaff())
        ->test('pages::counter.checkout')
        ->set('matric', 'STAFF0001')
        ->call('findStudent')
        ->assertHasErrors(['matric'])
        ->assertSet('walkInStudentId', null);
});

test('returns close loans and flag late ones', function () {
    $staff = counterStaff();
    $student = User::factory()->create();
    $type = EquipmentType::factory()->create();

    $onTime = Loan::factory()->withItem($type)->for($student)->create();
    $late = Loan::factory()->overdue()->withItem($type)->for($student)->create();

    Livewire::actingAs($staff)
        ->test('pages::counter.returns')
        ->assertSee($student->name)
        ->call('confirmReturn', $onTime->id)
        ->assertSee('Return recorded');

    expect($onTime->refresh()->returned_at)->not->toBeNull()
        ->and($onTime->penalties()->count())->toBe(0);

    Livewire::test('pages::counter.returns')
        ->set('search', 'PMS')
        ->call('confirmReturn', $late->id)
        ->assertSee('penalty');

    expect($late->refresh()->returned_at)->not->toBeNull()
        ->and($late->penalties()->count())->toBe(1)
        ->and(app(PenaltyService::class)->blocks($student))->toBeTrue();
});

test('the returns search narrows to matching students', function () {
    $staff = counterStaff();
    $hit = User::factory()->create(['name' => 'Nurul Aiman']);
    $miss = User::factory()->create(['name' => 'Zul Helmi']);
    $type = EquipmentType::factory()->create();

    Loan::factory()->withItem($type)->for($hit)->create();
    Loan::factory()->withItem($type)->for($miss)->create();

    Livewire::actingAs($staff)
        ->test('pages::counter.returns')
        ->set('search', 'Nurul')
        ->assertSee($hit->name)
        ->assertDontSee($miss->name);
});

test('lifting a penalty frees the student to borrow again', function () {
    $student = User::factory()->has(Penalty::factory())->create();

    $component = Livewire::actingAs(counterStaff())
        ->test('pages::counter.dashboard');

    $penalty = Penalty::query()->where('user_id', $student->id)->firstOrFail();

    $component->call('liftPenalty', $penalty->id);

    expect($penalty->refresh()->lifted_at)->not->toBeNull()
        ->and(app(PenaltyService::class)->blocks($student))->toBeFalse();
});
