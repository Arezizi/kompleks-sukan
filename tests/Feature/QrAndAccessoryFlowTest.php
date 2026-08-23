<?php

use App\Models\EquipmentType;
use App\Models\Loan;
use App\Models\User;
use App\Services\LoanService;
use App\Services\ReservationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function seedSuggestion(EquipmentType $gear, EquipmentType $accessory, string $prompt): void
{
    DB::table('accessory_suggestions')->insert([
        'equipment_type_id' => $gear->id,
        'suggested_equipment_type_id' => $accessory->id,
        'prompt' => $prompt,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('issued loans receive unique QR tokens', function () {
    $staff = User::factory()->jskkStaff()->create();
    $type = EquipmentType::factory()->create(['total_quantity' => 5]);

    $first = app(LoanService::class)->walkInCheckout(
        User::factory()->create(),
        [$type->id => 1],
        $staff,
    );

    $second = app(LoanService::class)->walkInCheckout(
        User::factory()->create(),
        [$type->id => 1],
        $staff,
    );

    expect($first->token)->toBeString()
        ->and($first->token)->not->toBe('')
        ->and($second->token)->not->toBe($first->token);
});

test('students see a pickup QR for approved reservations', function () {
    $student = User::factory()->create();
    $type = EquipmentType::factory()->create();

    $reservation = app(ReservationService::class)->create($student, [$type->id => 1]);
    app(ReservationService::class)->decide($reservation, User::factory()->jskkStaff()->create(), true);

    $this->actingAs($student)
        ->get('/reservations')
        ->assertOk()
        ->assertSee('pickup-qr', false)
        ->assertSee('<svg', false)
        ->assertSee($reservation->token);
});

test('scanning a pickup QR opens the checkout prefilled', function () {
    $staff = User::factory()->jskkStaff()->create();
    $student = User::factory()->create();
    $type = EquipmentType::factory()->create(['total_quantity' => 3]);

    $reservation = app(ReservationService::class)->create($student, [$type->id => 2]);
    app(ReservationService::class)->decide($reservation, $staff, true);

    $this->actingAs($staff)
        ->get('/counter/checkout?code='.$reservation->token)
        ->assertOk()
        ->assertSee($student->name);
});

test('confirming a pickup shows the loan return QR', function () {
    $staff = User::factory()->jskkStaff()->create();
    $student = User::factory()->create();
    $type = EquipmentType::factory()->create(['total_quantity' => 3]);

    $reservation = app(ReservationService::class)->create($student, [$type->id => 1]);
    app(ReservationService::class)->decide($reservation, $staff, true);

    Livewire::actingAs($staff)
        ->test('pages::counter.checkout')
        ->set('code', $reservation->token)
        ->call('findReservation')
        ->call('confirmPickup')
        ->assertSee('loan-qr', false)
        ->assertSee('Hand this return QR');

    $loan = $student->loans()->first();

    expect($loan->token)->toBeString()->not->toBe('');
});

test('a scanned loan QR pulls up its return form', function () {
    $staff = User::factory()->jskkStaff()->create();
    $student = User::factory()->create();
    $type = EquipmentType::factory()->create();

    $loan = Loan::factory()->withItem($type)->for($student)->create();

    $this->actingAs($staff)
        ->get('/counter/returns?loan='.$loan->token)
        ->assertOk()
        ->assertSee($student->name)
        ->assertSee('Confirm return');

    Livewire::actingAs($staff)
        ->test('pages::counter.returns')
        ->set('loanToken', $loan->token)
        ->call('findLoan')
        ->assertSet('foundLoanId', $loan->id)
        ->assertSet('returningId', $loan->id);

    expect($student->loans()->active()->count())->toBe(1);
});

test('unknown or returned loan tokens are refused', function () {
    $loan = Loan::factory()->returned()->create();

    Livewire::actingAs(User::factory()->jskkStaff()->create())
        ->test('pages::counter.returns')
        ->set('loanToken', $loan->token)
        ->call('findLoan')
        ->assertHasErrors('loanToken');
});

test('reservation pickups can add suggested accessories', function () {
    $staff = User::factory()->jskkStaff()->create();
    $student = User::factory()->create();
    $racket = EquipmentType::factory()->create(['name' => 'Badminton Racket', 'slug' => 'badminton-racket', 'total_quantity' => 5]);
    $shuttles = EquipmentType::factory()->create(['name' => 'Shuttlecocks', 'slug' => 'shuttlecocks', 'total_quantity' => 10]);

    seedSuggestion($racket, $shuttles, 'Add a tube of shuttlecocks for your session?');

    $service = app(ReservationService::class);
    $reservation = $service->create($student, [$racket->id => 1]);
    $service->decide($reservation, $staff, true);

    Livewire::actingAs($staff)
        ->test('pages::counter.checkout')
        ->set('code', $reservation->token)
        ->call('findReservation')
        ->assertSee('Shuttlecocks')
        ->assertSee('Add a tube of shuttlecocks')
        ->call('toggleExtra', $shuttles->id)
        ->call('confirmPickup');

    $loan = $student->loans()->with('items')->first();

    expect($loan->items)->toHaveCount(2)
        ->and($loan->items->contains(fn ($item) => $item->equipment_type_id === $shuttles->id && $item->quantity === 1))->toBeTrue();
});

test('walk-in loans surface accessory cross-prompts', function () {
    $staff = User::factory()->jskkStaff()->create();
    $student = User::factory()->create();
    $stick = EquipmentType::factory()->create(['name' => 'Hockey Stick', 'slug' => 'hockey-stick', 'total_quantity' => 8]);
    $ball = EquipmentType::factory()->create(['name' => 'Hockey Ball', 'slug' => 'hockey-ball', 'total_quantity' => 12]);

    seedSuggestion($stick, $ball, 'Grab a ball to go with it.');

    Livewire::actingAs($staff)
        ->test('pages::counter.checkout')
        ->set('matric', $student->matric_no ?? '')
        ->call('findStudent')
        ->set("quantities.{$stick->id}", 1)
        ->assertSee('Hockey Ball')
        ->assertSee('Grab a ball to go with it.')
        ->call('toggleAccessory', $ball->id)
        ->call('confirmWalkIn');

    $loan = $student->loans()->with('items')->first();

    expect($loan->items)->toHaveCount(2)
        ->and($loan->items->contains(fn ($item) => $item->equipment_type_id === $ball->id))->toBeTrue();
});

test('accessory extras respect live stock levels', function () {
    $staff = User::factory()->jskkStaff()->create();
    $student = User::factory()->create();
    $racket = EquipmentType::factory()->create(['total_quantity' => 5]);
    $shuttles = EquipmentType::factory()->create(['total_quantity' => 1]);

    seedSuggestion($racket, $shuttles, 'Add shuttlecocks?');

    // Someone else already holds the only shuttlecock tube.
    Loan::factory()->withItem($shuttles)->create();

    $service = app(ReservationService::class);
    $reservation = $service->create($student, [$racket->id => 1]);
    $service->decide($reservation, $staff, true);

    app(LoanService::class)->checkoutFromReservation($reservation->token, $staff, true, [$shuttles->id => 1]);
})->throws(ValidationException::class);
