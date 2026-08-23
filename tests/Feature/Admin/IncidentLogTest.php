<?php

use App\Models\Facility;
use App\Models\Incident;
use App\Models\User;
use Livewire\Livewire;

test('clinic staff may open the incident log, students may not', function () {
    $this->actingAs(User::factory()->clinicStaff()->create())
        ->get('/incidents')
        ->assertOk();

    $this->actingAs(User::factory()->create())
        ->get('/incidents')
        ->assertForbidden();
});

test('staff record an incident and see it listed', function () {
    $staff = User::factory()->clinicStaff()->create();
    $facility = Facility::factory()->create();

    Livewire::actingAs($staff)
        ->test('pages::admin.incidents.index')
        ->set('title', 'Sprained ankle')
        ->set('type', 'injury')
        ->set('severity', 'medium')
        ->set('facilityId', (string) $facility->id)
        ->set('description', 'Student landed badly near the net.')
        ->call('log')
        ->assertHasNoErrors()
        ->assertSee('Sprained ankle');

    $incident = Incident::query()->where('title', 'Sprained ankle')->firstOrFail();

    expect($incident->type->value)->toBe('injury')
        ->and($incident->severity->value)->toBe('medium')
        ->and($incident->facility_id)->toBe($facility->id)
        ->and($incident->reported_by)->toBe($staff->id);
});

test('incidents require a title and a description', function () {
    Livewire::actingAs(User::factory()->jskkStaff()->create())
        ->test('pages::admin.incidents.index')
        ->call('log')
        ->assertHasErrors(['title', 'description']);
});
