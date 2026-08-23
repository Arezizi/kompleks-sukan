<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('guests see the activation code form', function () {
    $response = $this->get('/activate');

    $response->assertOk();
    $response->assertSee('Activation code');
    $response->assertSee('Continue');
});

test('a valid pending token reveals the password setup form', function () {
    $user = User::factory()->create([
        'setup_token' => User::hashSetupToken($code = 'activation-code-123'),
        'setup_completed_at' => null,
        'password' => 'random-junk-password',
    ]);

    $response = $this->get("/activate?token={$code}");

    $response->assertOk();
    $response->assertSee($user->name);
    $response->assertSee('Set password & log in');
});

test('an unknown token keeps the code form without revealing accounts', function () {
    User::factory()->create([
        'setup_token' => User::hashSetupToken('real-code'),
        'setup_completed_at' => null,
    ]);

    $response = $this->get('/activate?token=wrong-code');

    $response->assertOk();
    $response->assertDontSee('Set password & log in');
});

test('an already activated account is reported as such', function () {
    User::factory()->create([
        'setup_token' => User::hashSetupToken('spent-code'),
        'setup_completed_at' => now(),
    ]);

    Livewire::withQueryParams(['token' => 'spent-code'])
        ->test('pages::auth.activate')
        ->assertOk()
        ->assertSee('already activated');
});

test('activating sets the password, clears the token and logs the student in', function () {
    $user = User::factory()->create([
        'setup_token' => User::hashSetupToken($code = 'activation-code-123'),
        'setup_completed_at' => null,
        'password' => Hash::make('temporary-password'),
    ]);

    Livewire::withQueryParams(['token' => $code])
        ->test('pages::auth.activate')
        ->set('password', 'new-Secret-Passw0rd!')
        ->set('password_confirmation', 'new-Secret-Passw0rd!')
        ->call('activate')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $fresh = $user->fresh();

    expect(Hash::check('new-Secret-Passw0rd!', $fresh->password))->toBeTrue()
        ->and($fresh->setup_token)->toBeNull()
        ->and($fresh->setup_completed_at)->not->toBeNull()
        ->and(auth()->user()?->is($fresh))->toBeTrue();
});

test('activation rejects mismatched password confirmation', function () {
    $user = User::factory()->create([
        'setup_token' => User::hashSetupToken($code = 'activation-code-123'),
        'setup_completed_at' => null,
    ]);

    Livewire::withQueryParams(['token' => $code])
        ->test('pages::auth.activate')
        ->set('password', 'new-Secret-Passw0rd!')
        ->set('password_confirmation', 'different-Secret-Passw0rd!')
        ->call('activate')
        ->assertHasErrors(['password']);

    $fresh = $user->fresh();

    expect($fresh->setup_token)->not->toBeNull()
        ->and(auth()->check())->toBeFalse();
});

test('activation fails once the token has been consumed', function () {
    $code = 'one-time-code';

    $user = User::factory()->create([
        'setup_token' => User::hashSetupToken($code),
        'setup_completed_at' => null,
    ]);

    Livewire::withQueryParams(['token' => $code])
        ->test('pages::auth.activate')
        ->set('password', 'new-Secret-Passw0rd!')
        ->set('password_confirmation', 'new-Secret-Passw0rd!')
        ->call('activate');

    $this->post(route('logout'));

    Livewire::withQueryParams(['token' => $code])
        ->test('pages::auth.activate')
        ->set('password', 'another-NewPassw0rd!')
        ->set('password_confirmation', 'another-NewPassw0rd!')
        ->call('activate')
        ->assertHasErrors(['token']);

    expect(Hash::check('new-Secret-Passw0rd!', $user->fresh()->password))->toBeTrue();
});
