<?php

use App\Models\EquipmentType;
use App\Models\Facility;
use App\Models\Loan;
use App\Models\SiteSetting;
use App\Models\User;

test('facility page shows daily operating hours from settings', function () {
    SiteSetting::current()->update([
        'opening_time' => '17:00:00',
        'closing_time' => '18:50:00',
    ]);

    $facility = Facility::factory()->create();

    $this->get("/facilities/{$facility->slug}")
        ->assertOk()
        ->assertSee('Operating hours')
        ->assertSee('5:00 PM')
        ->assertSee('6:50 PM')
        ->assertSee('Daily');
});

test('operating hours tolerate time values without seconds', function () {
    SiteSetting::factory()->create([
        'opening_time' => '17:00',
        'closing_time' => '18:50',
    ]);

    $facility = Facility::factory()->create();

    $this->travelTo(today()->setTime(17, 30));

    $this->get("/facilities/{$facility->slug}")
        ->assertOk()
        ->assertSee('5:00 PM')
        ->assertSee('6:50 PM')
        ->assertSee('data-test="peak-badge"', false);
});

test('a peak hours badge shows while the complex is open', function () {
    SiteSetting::current();

    $facility = Facility::factory()->create();

    $this->travelTo(today()->setTime(17, 30));

    $this->get("/facilities/{$facility->slug}")
        ->assertOk()
        ->assertSee('Peak hours')
        ->assertSee('data-test="peak-badge"', false)
        ->assertDontSee('data-test="off-peak-badge"', false);
});

test('an off-peak badge shows outside the operating window', function () {
    SiteSetting::current();

    $facility = Facility::factory()->create();

    $this->travelTo(today()->setTime(9, 0));

    $this->get("/facilities/{$facility->slug}")
        ->assertOk()
        ->assertSee('Off-peak hours')
        ->assertSee('data-test="off-peak-badge"', false)
        ->assertDontSee('data-test="peak-badge"', false)
        ->assertSee('Opens again today at 5:00 PM');
});

test('the banner shows a real photo when the facility has an image', function () {
    $facility = Facility::factory()->create([
        'image_path' => 'images/facilities/banner-test.jpg',
    ]);

    $directory = public_path('images/facilities');

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $file = public_path($facility->image_path);
    file_put_contents($file, 'fake-image-bytes');

    try {
        $this->get("/facilities/{$facility->slug}")
            ->assertOk()
            ->assertSee('data-test="facility-banner-image"', false)
            ->assertSee(asset($facility->image_path), false);
    } finally {
        @unlink($file);
    }
});

test('the banner falls back to a decorative court visual without an image', function () {
    $facility = Facility::factory()->create();

    $this->get("/facilities/{$facility->slug}")
        ->assertOk()
        ->assertDontSee('data-test="facility-banner-image"', false);
});

test('the facility page lists linked gear with live availability counters', function () {
    $facility = Facility::factory()->create();
    $rackets = EquipmentType::factory()->create(['name' => 'Badminton Racket', 'total_quantity' => 10]);
    $other = EquipmentType::factory()->create(['name' => 'Football']);

    Loan::factory()->withItem($rackets, 4)->create();

    $facility->equipmentTypes()->sync([$rackets->id]);

    $this->get("/facilities/{$facility->slug}")
        ->assertOk()
        ->assertSee('Equipment available here')
        ->assertSee('Badminton Racket')
        ->assertSee('6 available')
        ->assertSee('of 10 total')
        ->assertSee('reserve='.$rackets->id, false)
        ->assertDontSee('Football');
});

test('fully borrowed gear shows as out of stock without a reserve link', function () {
    $facility = Facility::factory()->create();
    $shuttles = EquipmentType::factory()->create(['name' => 'Shuttlecocks', 'total_quantity' => 6]);

    Loan::factory()->withItem($shuttles, 6)->create();

    $facility->equipmentTypes()->sync([$shuttles->id]);

    $this->get("/facilities/{$facility->slug}")
        ->assertOk()
        ->assertSee('Shuttlecocks')
        ->assertSee('Out of stock')
        ->assertDontSee('reserve='.$shuttles->id, false);
});

test('inactive gear stays hidden even when linked to the facility', function () {
    $facility = Facility::factory()->create();
    $secret = EquipmentType::factory()->create(['name' => 'Secret Gear', 'is_active' => false]);

    $facility->equipmentTypes()->sync([$secret->id]);

    $this->get("/facilities/{$facility->slug}")
        ->assertOk()
        ->assertDontSee('Secret Gear')
        ->assertSee('No dedicated gear is linked to this facility yet');
});

test('gear deep links open the reserve modal straight away', function () {
    $student = User::factory()->create();
    $rackets = EquipmentType::factory()->create(['name' => 'Badminton Racket']);

    $this->actingAs($student)
        ->get('/gear?reserve='.$rackets->id)
        ->assertOk()
        ->assertSee('Reserve Badminton Racket');
});

test('guests following a gear deep link are asked to log in first', function () {
    $rackets = EquipmentType::factory()->create();

    $this->get('/gear?reserve='.$rackets->id)
        ->assertRedirect(route('login', absolute: false));
});

test('unknown deep link ids simply render the catalog', function () {
    $this->get('/gear?reserve=999')
        ->assertOk()
        ->assertDontSee('Request reservation');
});
