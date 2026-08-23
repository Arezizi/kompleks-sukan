<?php

use App\Enums\FacilityStatus;
use App\Models\Facility;
use App\Models\FacilityStatusLog;
use App\Models\SiteSetting;
use App\Models\User;

test('guests and students cannot open facility control', function () {
    $this->get('/admin/facilities')->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get('/admin/facilities')
        ->assertForbidden();
});

test('staff see every facility with its current status', function () {
    Facility::factory()->create(['name' => 'Archery Range']);

    Livewire::actingAs(User::factory()->jskkStaff()->create())
        ->test('pages::admin.facilities.index')
        ->assertOk()
        ->assertSee('Archery Range')
        ->assertSee(FacilityStatus::Open->label());
});

test('changing a status updates the facility and writes an audit entry', function () {
    $staff = User::factory()->jskkStaff()->create();
    $facility = Facility::factory()->create();

    Livewire::actingAs($staff)
        ->test('pages::admin.facilities.index')
        ->call('editStatus', $facility->id)
        ->set('status', FacilityStatus::Maintenance->value)
        ->set('statusNote', 'Net replacement until Friday.')
        ->call('applyStatus')
        ->assertHasNoErrors();

    expect($facility->refresh()->status)->toBe(FacilityStatus::Maintenance)
        ->and($facility->status_note)->toBe('Net replacement until Friday.')
        ->and($facility->status_changed_by)->toBe($staff->id);

    $log = FacilityStatusLog::query()->where('facility_id', $facility->id)->firstOrFail();

    expect($log->status)->toBe(FacilityStatus::Maintenance)
        ->and($log->note)->toBe('Net replacement until Friday.')
        ->and($log->changed_by)->toBe($staff->id);
});

test('the weather banner persists and shows on the public site', function () {
    Livewire::actingAs(User::factory()->jskkStaff()->create())
        ->test('pages::admin.facilities.index')
        ->set('bannerActive', true)
        ->set('bannerMessage', 'Outdoor areas closed - heavy rain.')
        ->call('saveBanner')
        ->assertHasNoErrors();

    expect(SiteSetting::current()->refresh()->weather_banner_active)->toBeTrue()
        ->and(SiteSetting::current()->weather_banner_message)->toBe('Outdoor areas closed - heavy rain.');

    $this->get('/')
        ->assertOk()
        ->assertSee('Outdoor areas closed - heavy rain.');
});
