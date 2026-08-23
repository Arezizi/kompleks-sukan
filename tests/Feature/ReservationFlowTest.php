<?php

use App\Enums\ReservationStatus;
use App\Models\EquipmentType;
use App\Models\Loan;
use App\Models\Penalty;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\User;
use App\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

test('students create pending reservations that hold stock', function () {
    $student = User::factory()->create();
    $type = EquipmentType::factory()->create(['total_quantity' => 5]);

    $reservation = app(ReservationService::class)->create($student, [$type->id => 2]);

    expect($reservation->status)->toBe(ReservationStatus::Pending)
        ->and($reservation->items)->toHaveCount(1)
        ->and($reservation->items->first()->quantity)->toBe(2)
        ->and($type->availableQuantity())->toBe(3);
});

test('empty selections are refused', function () {
    $student = User::factory()->create();

    app(ReservationService::class)->create($student, []);
})->throws(ValidationException::class);

test('requests beyond free stock are refused', function () {
    $student = User::factory()->create();
    $type = EquipmentType::factory()->create(['total_quantity' => 2]);

    app(ReservationService::class)->create($student, [$type->id => 99]);
})->throws(ValidationException::class);

test('penalised students cannot reserve', function () {
    $student = User::factory()->has(Penalty::factory())->create();
    $type = EquipmentType::factory()->create(['total_quantity' => 5]);

    app(ReservationService::class)->create($student, [$type->id => 1]);
})->throws(ValidationException::class);

test('the open-reservation limit applies per student', function () {
    $student = User::factory()->create();
    $type = EquipmentType::factory()->create(['total_quantity' => 10]);

    Reservation::factory()->count(2)->for($student)->create();

    app(ReservationService::class)->create($student, [$type->id => 1]);
})->throws(ValidationException::class);

test('staff approval sets the pickup deadline', function () {
    $staff = User::factory()->jskkStaff()->create();
    $service = app(ReservationService::class);

    $type = EquipmentType::factory()->create(['total_quantity' => 5]);
    $reservation = $service->create(User::factory()->create(), [$type->id => 1]);

    $service->decide($reservation, $staff, true);

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Approved)
        ->and($reservation->decided_by)->toBe($staff->id)
        ->and($reservation->pickup_deadline)->toBeInstanceOf(CarbonImmutable::class);
});

test('approval fails when stock has since dried up', function () {
    $staff = User::factory()->jskkStaff()->create();
    $service = app(ReservationService::class);

    $type = EquipmentType::factory()->create(['total_quantity' => 2]);
    $reservation = $service->create(User::factory()->create(), [$type->id => 2]);

    // Someone else takes both units on an active loan before approval.
    Loan::factory()->for(User::factory())->withItem($type, 2)->create();

    $service->decide($reservation, $staff, true);
})->throws(ValidationException::class);

test('staff can reject with a note', function () {
    $staff = User::factory()->jskkStaff()->create();
    $service = app(ReservationService::class);

    $type = EquipmentType::factory()->create(['total_quantity' => 5]);
    $reservation = $service->create(User::factory()->create(), [$type->id => 1]);

    $service->decide($reservation, $staff, false, 'Court closed today.');

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Rejected)
        ->and($reservation->decision_note)->toBe('Court closed today.');
});

test('students cancel their own reservations', function () {
    $service = app(ReservationService::class);
    $type = EquipmentType::factory()->create(['total_quantity' => 5]);
    $reservation = $service->create(User::factory()->create(), [$type->id => 1]);

    $service->cancel($reservation);

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Cancelled)
        ->and($type->availableQuantity())->toBe(5); // Stock released.
});

test('stale pickups are expired and release stock', function () {
    $service = app(ReservationService::class);
    $type = EquipmentType::factory()->create(['total_quantity' => 5]);

    $stale = Reservation::factory()
        ->approved()
        ->has(ReservationItem::factory()->state(['equipment_type_id' => $type->id, 'quantity' => 2]), 'items')
        ->create(['pickup_deadline' => now()->subHour()]);

    $fresh = Reservation::factory()
        ->approved()
        ->has(ReservationItem::factory()->state(['equipment_type_id' => $type->id, 'quantity' => 1]), 'items')
        ->create();

    expect($service->expireStale())->toBe(1)
        ->and($stale->refresh()->status)->toBe(ReservationStatus::Expired)
        ->and($fresh->refresh()->status)->toBe(ReservationStatus::Approved)
        ->and($type->availableQuantity())->toBe(4);
});
