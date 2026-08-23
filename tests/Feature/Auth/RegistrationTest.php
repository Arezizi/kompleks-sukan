<?php

use App\Models\User;

test('registration pages are disabled', function () {
    $this->get('/register')->assertNotFound();
});

test('registration endpoint is disabled', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();
});

test('no account can be created through the register route', function () {
    $this->post('/register', [
        'name' => 'Sneaky User',
        'email' => 'sneaky@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::query()->where('email', 'sneaky@example.com')->exists())->toBeFalse()
        ->and(User::query()->count())->toBe(0);
});
