<?php

use App\Http\Controllers\ExportController;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::home.index')->name('home');

Route::livewire('facilities/{slug}', 'pages::facilities.show')->name('facilities.show');
Route::livewire('gear', 'pages::gear.index')->name('gear.index');
Route::livewire('rules', 'pages::rules.index')->name('rules.index');
Route::livewire('activate', 'pages::auth.activate')->name('activate')->middleware('guest');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('dashboard', 'pages::dashboard.index')->name('dashboard');

    Route::livewire('reservations', 'pages::reservations.index')->name('reservations.index');

    Route::middleware('role:jskk_staff,clinic_staff,admin')->group(function (): void {
        Route::livewire('incidents', 'pages::admin.incidents.index')->name('admin.incidents.index');
    });

    Route::middleware('role:jskk_staff,admin')->group(function (): void {
        Route::livewire('admin/roster', 'pages::admin.roster.index')->name('admin.roster.index');
        Route::livewire('admin/inventory', 'pages::admin.inventory.index')->name('admin.inventory.index');
        Route::livewire('admin/facilities', 'pages::admin.facilities.index')->name('admin.facilities.index');
        Route::livewire('counter', 'pages::counter.dashboard')->name('counter.dashboard');
        Route::livewire('counter/checkout', 'pages::counter.checkout')->name('counter.checkout');
        Route::livewire('counter/returns', 'pages::counter.returns')->name('counter.returns');
        Route::livewire('counter/penalties', 'pages::counter.penalties')->name('counter.penalties');
        Route::get('admin/export/{type}', ExportController::class)->name('admin.export');
    });
});

require __DIR__.'/settings.php';
