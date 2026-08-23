<?php

use App\Enums\ReservationStatus;
use App\Models\EquipmentType;
use App\Models\Loan;
use App\Models\Penalty;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\User;
use App\Services\LoanService;
use App\Services\PenaltyService;
use App\Services\ReservationService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

test('approved reservations are checked out with their own lines', function () {
    Carbon::setTestNow('2026-08-23 10:00:00');

    $staff = User::factory()->jskkStaff()->create();
    $student = User::factory()->create();
    $type = EquipmentType::factory()->create(['total_quantity' => 5]);

    $service = app(ReservationService::class);
    $reservation = $service->create($student, [$type->id => 2]);
    $service->decide($reservation, $staff, true);

    $loan = app(LoanService::class)->checkoutFromReservation($reservation->token, $staff);

    expect($loan->user_id)->toBe($student->id)
        ->and($loan->issued_by)->toBe($staff->id)
        ->and($loan->items->first()->quantity)->toBe(2)
        ->and($loan->due_at->format('H:i'))->toBe('12:00')
        ->and($loan->card_collected)->toBeTrue()
        ->and($reservation->refresh()->status)->toBe(ReservationStatus::Fulfilled);
});

test('due times are capped at the daily closing time', function () {
    Carbon::setTestNow('2026-08-23 17:45:00');

    $staff = User::factory()->jskkStaff()->create();
    $type = EquipmentType::factory()->create(['total_quantity' => 5]);

    $loan = app(LoanService::class)->walkInCheckout(
        User::factory()->create(),
        [$type->id => 1],
        $staff,
    );

    expect($loan->due_at->format('H:i'))->toBe('18:30');
});

test('walk-in checkouts work without a reservation', function () {
    $staff = User::factory()->jskkStaff()->create();
    $student = User::factory()->create();
    $type = EquipmentType::factory()->create(['total_quantity' => 5]);

    $loan = app(LoanService::class)->walkInCheckout($student, [$type->id => 3], $staff);

    expect($loan->reservation_id)->toBeNull()
        ->and($loan->items->first()->quantity)->toBe(3)
        ->and($type->availableQuantity())->toBe(2);
});

test('unknown pickup codes are refused', function () {
    app(LoanService::class)->checkoutFromReservation('nope', User::factory()->jskkStaff()->create());
})->throws(ValidationException::class);

test('non-approved reservations cannot be collected', function () {
    $type = EquipmentType::factory()->create();

    $reservation = Reservation::factory()
        ->has(ReservationItem::factory()->state(['equipment_type_id' => $type->id]), 'items')
        ->create(); // Pending.

    app(LoanService::class)->checkoutFromReservation($reservation->token, User::factory()->jskkStaff()->create());
})->throws(ValidationException::class);

test('students at the active-loan limit cannot borrow more', function () {
    $staff = User::factory()->jskkStaff()->create();
    $student = User::factory()->create();
    $type = EquipmentType::factory()->create(['total_quantity' => 10]);

    Loan::factory()->count(2)->for($student)->withItem($type, 1)->create();

    app(LoanService::class)->walkInCheckout($student, [$type->id => 1], $staff);
})->throws(ValidationException::class);

test('penalised students cannot borrow', function () {
    $staff = User::factory()->jskkStaff()->create();
    $student = User::factory()->has(Penalty::factory())->create();
    $type = EquipmentType::factory()->create(['total_quantity' => 10]);

    app(LoanService::class)->walkInCheckout($student, [$type->id => 1], $staff);
})->throws(ValidationException::class);

test('on-time returns close the loan cleanly', function () {
    $staff = User::factory()->jskkStaff()->create();
    $type = EquipmentType::factory()->create();

    $loan = Loan::factory()->withItem($type)->create();

    $returned = app(LoanService::class)->returnLoan($loan, $staff, 'All good', true);

    expect($returned->returned_at)->not->toBeNull()
        ->and($returned->returned_to)->toBe($staff->id)
        ->and($returned->card_returned)->toBeTrue()
        ->and($returned->condition_note)->toBe('All good')
        ->and($returned->penalties)->toHaveCount(0);
});

test('late returns trigger an automatic penalty', function () {
    Carbon::setTestNow('2026-08-23 15:00:00');

    $staff = User::factory()->jskkStaff()->create();
    $type = EquipmentType::factory()->create();

    $loan = Loan::factory()->overdue()->withItem($type)->for(User::factory()->create())->create();

    $returned = app(LoanService::class)->returnLoan($loan, $staff);

    expect($returned->penalties)->toHaveCount(1);

    $penalty = $returned->penalties->first();

    expect($penalty->imposed_automatically)->toBeTrue()
        ->and(abs($penalty->starts_at->diffInDays($penalty->ends_at)))->toEqualWithDelta(14.0, 0.001)
        ->and(app(PenaltyService::class)->blocks($returned->user))->toBeTrue();
});

test('a loan can only be returned once', function () {
    $staff = User::factory()->jskkStaff()->create();
    $type = EquipmentType::factory()->create();

    $loan = Loan::factory()->returned()->withItem($type)->create();

    app(LoanService::class)->returnLoan($loan, $staff);
})->throws(ValidationException::class);
