<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

test('guests are rejected by the role middleware', function () {
    Route::get('/probe-role', fn () => 'ok')->middleware('role:'.UserRole::JskkStaff->value.','.UserRole::Admin->value);

    $this->get('/probe-role')->assertForbidden();
});

test('students cannot enter staff-only areas', function () {
    Route::get('/probe-role', fn () => 'ok')->middleware('role:'.UserRole::JskkStaff->value.','.UserRole::Admin->value);

    $this->actingAs(User::factory()->create())
        ->get('/probe-role')
        ->assertForbidden();
});

test('clinic staff cannot enter jskk areas without explicit grant', function () {
    Route::get('/probe-role', fn () => 'ok')->middleware('role:'.UserRole::JskkStaff->value);

    $this->actingAs(User::factory()->clinicStaff()->create())
        ->get('/probe-role')
        ->assertForbidden();
});

test('jskk staff pass the role middleware', function () {
    Route::get('/probe-role', fn () => 'ok')->middleware('role:'.UserRole::JskkStaff->value);

    $this->actingAs(User::factory()->jskkStaff()->create())
        ->get('/probe-role')
        ->assertSuccessful()
        ->assertSee('ok');
});

test('admins pass any role gate', function () {
    Route::get('/probe-role', fn () => 'ok')->middleware('role:'.UserRole::JskkStaff->value.','.UserRole::Admin->value);
    Route::get('/probe-clinic', fn () => 'ok')->middleware('role:'.UserRole::ClinicStaff->value);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/probe-role')->assertSuccessful();
    $this->actingAs($admin)->get('/probe-clinic')->assertForbidden();
});
