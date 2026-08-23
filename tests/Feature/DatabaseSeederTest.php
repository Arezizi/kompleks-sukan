<?php

use App\Enums\FacilityCategory;
use App\Enums\UserRole;
use App\Models\EquipmentType;
use App\Models\Facility;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('seeder populates the full sports complex catalog', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Facility::count())->toBe(26)
        ->and(Facility::query()->where('category', FacilityCategory::Indoor)->count())->toBe(8)
        ->and(Facility::query()->where('category', FacilityCategory::Outdoor)->count())->toBe(18)
        ->and(Facility::query()->where('status', '!=', 'open')->count())->toBe(0);
});

test('seeder populates equipment types with accessory cross-prompts', function () {
    $this->seed(DatabaseSeeder::class);

    expect(EquipmentType::count())->toBe(9);

    $racket = EquipmentType::query()->where('slug', 'badminton-racket')->firstOrFail();
    $suggested = $racket->suggestedAccessories()->first();

    expect($suggested)->not->toBeNull()
        ->and($suggested->slug)->toBe('shuttlecocks-tube-of-6')
        ->and($suggested->pivot->prompt)->toBe('Add a tube of shuttlecocks for your session?');
});

test('seeder creates demo staff and student accounts', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'admin@pms.example')->firstOrFail()->role)->toBe(UserRole::Admin)
        ->and(User::query()->where('email', 'jskk@pms.example')->firstOrFail()->role)->toBe(UserRole::JskkStaff)
        ->and(User::query()->where('email', 'clinic@pms.example')->firstOrFail()->role)->toBe(UserRole::ClinicStaff)
        ->and(User::query()->where('matric_no', 'PMS100001')->exists())->toBeTrue();
});

test('seeder creates default site settings', function () {
    $this->seed(DatabaseSeeder::class);

    expect(SiteSetting::count())->toBe(1)
        ->and(SiteSetting::current()->closing_time)->toBe('18:30:00')
        ->and(SiteSetting::current()->penalty_days)->toBe(14);
});
