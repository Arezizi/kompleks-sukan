<?php

use App\Enums\FacilityCategory;
use App\Enums\FacilityStatus;
use App\Enums\IncidentType;
use App\Enums\ReservationStatus;
use App\Models\EquipmentType;
use App\Models\Facility;
use App\Models\Loan;
use App\Models\Penalty;
use App\Models\Reservation;
use App\Models\SiteSetting;
use App\Models\User;

test('facility factory persists with enum casts', function () {
    $facility = Facility::factory()->create();

    expect($facility->status)->toBe(FacilityStatus::Open)
        ->and($facility->category)->toBeInstanceOf(FacilityCategory::class)
        ->and(Facility::query()->where('slug', $facility->slug)->exists())->toBeTrue();
});

test('facility scopes filter visibility and order by category', function () {
    $indoor = Facility::factory()->indoor()->create();
    Facility::factory()->outdoor()->create();

    expect(Facility::query()->ordered()->first()->id)->toBe($indoor->id);

    $indoor->update(['is_active' => false]);

    expect(Facility::query()->visible()->find($indoor->id))->toBeNull();
});

test('equipment type tracks suggested accessories through the pivot', function () {
    $racket = EquipmentType::factory()->create(['name' => 'Badminton Racket']);
    $tube = EquipmentType::factory()->create(['name' => 'Shuttlecocks']);

    $racket->suggestedAccessories()->attach([$tube->id => ['prompt' => 'Add shuttlecocks?']]);

    $suggested = $racket->suggestedAccessories()->first();

    expect($suggested->id)->toBe($tube->id)
        ->and($suggested->pivot->prompt)->toBe('Add shuttlecocks?');
});

test('active loans are on loan until returned', function () {
    $type = EquipmentType::factory()->create(['total_quantity' => 5]);
    $active = Loan::factory()->withItem($type)->create();
    Loan::factory()->returned()->withItem($type)->create();

    expect($type->fresh()->onLoanQuantity())->toBe(1)
        ->and(Loan::query()->active()->get()->modelKeys())->toContain($active->id);
});

test('overdue scope matches only past-due unreturned loans', function () {
    $overdue = Loan::factory()->overdue()->create();
    Loan::factory()->returned()->create();

    expect(Loan::query()->overdue()->get()->modelKeys())->toContain($overdue->id)
        ->and(Loan::query()->overdue()->count())->toBe(1);
});

test('penalty scope matches only currently enforced penalties', function () {
    $user = User::factory()->create();

    $current = Penalty::factory()->for($user)->create();
    Penalty::factory()->lifted()->for($user)->create();
    Penalty::factory()->for($user)->create(['ends_at' => now()->subDay()]);

    $active = Penalty::query()->active()->get();

    expect($active->modelKeys())->toContain($current->id)
        ->and($active->count())->toBe(1);
});

test('only pending and approved reservations hold stock', function () {
    $pending = Reservation::factory()->create();
    $approved = Reservation::factory()->approved()->create();
    $rejected = Reservation::factory()->rejected()->create();

    $holding = Reservation::query()->holdingStock()->get();

    expect($holding->modelKeys())->toContain($pending->id)
        ->and($holding->modelKeys())->toContain($approved->id)
        ->and($holding->modelKeys())->not->toContain($rejected->id);
});

test('reservation status persists as backed enum', function () {
    $reservation = Reservation::factory()->fulfilled()->create();

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Fulfilled);
});

test('facility status logs record changes against the facility', function () {
    $staff = User::factory()->jskkStaff()->create();
    $facility = Facility::factory()->underMaintenance('Pump replacement')->create();

    $facility->statusLogs()->create([
        'status' => FacilityStatus::Maintenance,
        'note' => 'Pump replacement',
        'changed_by' => $staff->id,
    ]);

    $log = $facility->statusLogs()->first();

    expect($log->status)->toBe(FacilityStatus::Maintenance)
        ->and($log->changedBy->is($staff))->toBeTrue();
});

test('site settings resolve to a single defaulted row', function () {
    $first = SiteSetting::current();
    $second = SiteSetting::current();

    expect($first->id)->toBe($second->id)
        ->and($first->closing_time)->toBe('18:30:00')
        ->and($first->max_loan_minutes)->toBe(120)
        ->and($first->penalty_days)->toBe(14);
});

test('student relations expose reservations, loans, penalties and incidents', function () {
    $student = User::factory()->create();

    $student->reservations()->create([
        'status' => ReservationStatus::Pending,
        'token' => 'token-123',
    ]);
    $student->loans()->create([
        'issued_by' => User::factory()->jskkStaff()->create()->id,
        'checked_out_at' => now(),
        'due_at' => now()->addHours(2),
    ]);
    $student->penalties()->create([
        'starts_at' => now(),
        'ends_at' => now()->addDays(14),
        'reason' => 'Late return',
    ]);
    $student->incidentsReported()->create([
        'title' => 'Sprained ankle',
        'description' => 'Slipped near the badminton court.',
        'occurred_at' => now(),
    ]);

    $incident = $student->incidentsReported()->first();

    expect($student->reservations()->count())->toBe(1)
        ->and($student->loans()->count())->toBe(1)
        ->and($student->penalties()->count())->toBe(1)
        ->and($student->incidentsReported()->count())->toBe(1)
        ->and($incident->type)->toBe(IncidentType::Other);
});
