<?php

use App\Enums\FacilityStatus;
use App\Models\EquipmentType;
use App\Models\Facility;
use App\Models\FacilityStatusLog;
use App\Models\Loan;
use App\Models\Reservation;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('home page groups facilities into one card per facility type', function () {
    foreach ([1, 2, 3, 4] as $i) {
        Facility::factory()->create(['name' => "Badminton Court {$i}", 'slug' => "badminton-court-{$i}"]);
    }

    Facility::factory()->underMaintenance('Resurfacing')->create(['name' => 'Maintenance Court']);
    Facility::factory()->closed('Private event')->create(['name' => 'Closed Court']);

    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('Facility status board')
        ->assertSee('Badminton Court')
        ->assertSee('All open')
        ->assertSee('Under maintenance')
        ->assertSee('All closed');

    expect($response->getContent())
        ->not->toContain('Badminton Court 1')
        ->not->toContain('data-test="facility-group-modal"');
});

test('selecting a facility type card reveals its individual sub-facilities', function () {
    foreach ([1, 2] as $i) {
        Facility::factory()->create(['name' => "Badminton Court {$i}", 'slug' => "badminton-court-{$i}"]);
    }
    Facility::factory()->underMaintenance('Resurfacing')->create(['name' => 'Badminton Court 3', 'slug' => 'badminton-court-3']);

    Livewire::test('pages::home.index')
        ->assertSet('activeGroup', null)
        ->set('activeGroup', 'badminton-court')
        ->assertSee('facility-group-modal', false)
        ->assertSee('Badminton Court 1')
        ->assertSee('Badminton Court 2')
        ->assertSee('Resurfacing');
});

test('home page hides inactive facilities from the board', function () {
    Facility::factory()->create(['name' => 'Visible Court', 'is_active' => true]);
    Facility::factory()->create(['name' => 'Hidden Court', 'is_active' => false]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Visible Court')
        ->assertDontSee('Hidden Court');
});

test('home page shows a weather banner only when one is active', function () {
    SiteSetting::current()->update([
        'weather_banner_active' => false,
        'weather_banner_message' => null,
    ]);

    $this->get('/')->assertOk()->assertDontSee('Weather notice');

    SiteSetting::query()->update([
        'weather_banner_active' => true,
        'weather_banner_message' => 'Outdoor areas closed — heavy rain.',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Weather notice')
        ->assertSee('Outdoor areas closed — heavy rain.');
});

test('facility detail pages show description, rules and status history', function () {
    $staff = User::factory()->jskkStaff()->create();

    $facility = Facility::factory()->underMaintenance('Resurfacing in progress')->create([
        'name' => 'Championship Court',
        'rules' => 'No food or drinks on the court.',
    ]);

    FacilityStatusLog::query()->create([
        'facility_id' => $facility->id,
        'status' => FacilityStatus::Maintenance,
        'note' => 'Net replaced',
        'changed_by' => $staff->id,
    ]);

    $this->get("/facilities/{$facility->slug}")
        ->assertOk()
        ->assertSee('Championship Court')
        ->assertSee('No food or drinks on the court.')
        ->assertSee('Status note from staff')
        ->assertSee('Resurfacing in progress')
        ->assertSee('Net replaced')
        ->assertSee($staff->name);
});

test('unknown and inactive facility slugs return 404', function () {
    Facility::factory()->create(['is_active' => false, 'slug' => 'hidden-court']);

    $this->get('/facilities/does-not-exist')->assertNotFound();
    $this->get('/facilities/hidden-court')->assertNotFound();
});

test('gear catalog shows live availability per equipment type', function () {
    $gear = EquipmentType::factory()->create(['total_quantity' => 10]);

    Loan::factory()->withItem($gear, 3)->create(); // active loan: returned_at null
    Reservation::factory()->approved()->create()->items()->create([
        'equipment_type_id' => $gear->id,
        'quantity' => 2,
    ]);

    $this->get('/gear')
        ->assertOk()
        ->assertSee('5 available')
        ->assertSee('of 10 total');
});

test('fully borrowed gear shows out of stock', function () {
    $gear = EquipmentType::factory()->create(['total_quantity' => 4]);

    Loan::factory()->withItem($gear, 4)->create();

    $this->get('/gear')
        ->assertOk()
        ->assertSee('Out of stock');
});

test('inactive gear is hidden from the catalog', function () {
    EquipmentType::factory()->create(['name' => 'Secret Ball', 'is_active' => false]);

    $this->get('/gear')->assertOk()->assertDontSee('Secret Ball');
});

test('rules page reflects configured operating rules', function () {
    SiteSetting::current()->update([
        'closing_time' => '19:00:00',
        'max_loan_minutes' => 90,
        'penalty_days' => 21,
    ]);

    $this->get('/rules')
        ->assertOk()
        ->assertSee('7:00 PM')
        ->assertSee('1 hr 30 min')
        ->assertSee('21-day borrowing pause');
});

test('first login activation token is not exposed publicly', function () {
    User::factory()->create([
        'password' => Hash::make('password'),
        'setup_token' => 'secret-token-value',
    ]);

    $this->get('/')->assertOk()->assertDontSee('secret-token-value');
});
