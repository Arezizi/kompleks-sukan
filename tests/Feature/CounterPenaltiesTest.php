<?php

use App\Models\Penalty;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('students cannot open the penalties page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/counter/penalties')
        ->assertForbidden();
});

test('staff see penalty records with their state', function () {
    $student = User::factory()->has(Penalty::factory())->create();

    Livewire::actingAs(User::factory()->jskkStaff()->create())
        ->test('pages::counter.penalties')
        ->assertOk()
        ->assertSee($student->name)
        ->assertSee('Active');
});

test('staff lift an active penalty early', function () {
    $staff = User::factory()->jskkStaff()->create();
    $student = User::factory()->has(Penalty::factory())->create();
    $penalty = Penalty::query()->where('user_id', $student->id)->firstOrFail();

    Livewire::actingAs($staff)
        ->test('pages::counter.penalties')
        ->call('lift', $penalty->id)
        ->assertSee('Penalty lifted.');

    expect($penalty->refresh()->lifted_at)->not->toBeNull()
        ->and($penalty->lifted_by)->toBe($staff->id);
});

test('already lifted penalties cannot be lifted twice', function () {
    $student = User::factory()->has(Penalty::factory(['lifted_at' => now(), 'lifted_by' => 1]))->create();
    $penalty = Penalty::query()->where('user_id', $student->id)->firstOrFail();
    $originalLiftedAt = $penalty->lifted_at;

    $component = Livewire::actingAs(User::factory()->jskkStaff()->create())
        ->test('pages::counter.penalties');

    expect(fn () => $component->call('lift', $penalty->id))
        ->toThrow(ModelNotFoundException::class);

    expect($penalty->refresh()->lifted_at->equalTo($originalLiftedAt))->toBeTrue();
});
