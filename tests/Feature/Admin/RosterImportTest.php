<?php

use App\Mail\RosterInvitation;
use App\Models\User;
use App\Services\RosterImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function rosterCsv(string ...$rows): UploadedFile
{
    $lines = ['matric_no,name,email,programme,phone', ...$rows];

    return UploadedFile::fake()->createWithContent('roster.csv', implode("\n", $lines));
}

test('guests cannot open the roster import page', function () {
    $this->get('/admin/roster')->assertRedirect(route('login', absolute: false));
});

test('students cannot open the roster import page', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/roster')->assertForbidden();
});

test('staff can view the roster import page', function () {
    $this->actingAs(User::factory()->jskkStaff()->create());

    $response = $this->get('/admin/roster');

    $response->assertOk();
    $response->assertSee('Roster import');
});

test('importing creates pending accounts and emails activation links', function () {
    Mail::fake();

    $this->actingAs(User::factory()->jskkStaff()->create());

    Livewire::test('pages::admin.roster.index')
        ->set('file', rosterCsv(
            'PMS100001,Ali bin Abu,ali@example.edu,Diploma Sains Sukan,0123456789',
            'PMS100002,Sara binti Lim,sara@example.edu,Diploma Pengurusan Sukan,',
        ))
        ->call('import')
        ->assertHasNoErrors()
        ->assertSee('Import report');

    expect(User::query()->where('matric_no', 'PMS100001')->first())
        ->not->toBeNull()
        ->and(User::query()->where('matric_no', 'PMS100002')->first())->not->toBeNull();

    $ali = User::query()->where('email', 'ali@example.edu')->first();

    expect($ali->isPendingSetup())->toBeTrue()
        ->and($ali->role->value)->toBe('student')
        ->and($ali->email_verified_at)->not->toBeNull()
        ->and($ali->programme)->toBe('Diploma Sains Sukan');

    Mail::assertQueued(RosterInvitation::class, 2);

    Mail::assertQueued(RosterInvitation::class, fn (RosterInvitation $mail): bool => $mail->activationCode !== '');
});

test('the emailed activation code works end to end', function () {
    Mail::fake();

    $this->actingAs(User::factory()->jskkStaff()->create());

    Livewire::test('pages::admin.roster.index')
        ->set('file', rosterCsv('PMS200001,Ali bin Abu,ali@example.edu,,'))
        ->call('import')
        ->assertHasNoErrors();

    $code = null;

    Mail::assertQueued(RosterInvitation::class, function (RosterInvitation $mail) use (&$code): bool {
        $code = $mail->activationCode;

        return true;
    });

    expect($code)->not->toBeNull();

    // The stored token is a hash; only the emailed code resolves it.
    expect(User::query()->where('setup_token', $code)->exists())->toBeFalse()
        ->and(User::query()->where('setup_token', User::hashSetupToken((string) $code))->exists())->toBeTrue();

    auth()->logout();

    $response = $this->get("/activate?token={$code}");
    $response->assertOk();
    $response->assertSee('Ali bin Abu');
});

test('existing matric numbers are updated without a second invitation', function () {
    Mail::fake();

    $existing = User::factory()->create([
        'matric_no' => 'PMS300001',
        'name' => 'Old Name',
        'programme' => null,
        'setup_completed_at' => now(),
        'setup_token' => null,
    ]);

    $this->actingAs(User::factory()->jskkStaff()->create());

    Livewire::test('pages::admin.roster.index')
        ->set('file', rosterCsv('PMS300001,New Name,new@example.edu,Diploma Sains Sukan,0111111111'))
        ->call('import')
        ->assertHasNoErrors();

    $fresh = $existing->fresh();

    expect($fresh->name)->toBe('New Name')
        ->and($fresh->email)->toBe('new@example.edu')
        ->and($fresh->programme)->toBe('Diploma Sains Sukan')
        ->and($fresh->isPendingSetup())->toBeFalse();

    Mail::assertNothingQueued();
});

test('invalid rows are rejected with row numbers while valid ones still import', function () {
    Mail::fake();

    $this->actingAs(User::factory()->jskkStaff()->create());

    $component = Livewire::test('pages::admin.roster.index')
        ->set('file', rosterCsv(
            'PMS400001,Ali bin Abu,not-an-email,,',
            ',Missing Everything,,,',
            'PMS400003,Sara binti Lim,sara@example.edu,,',
        ))
        ->call('import');

    $report = $component->get('report');

    expect($report['created'])->toBe(['PMS400003'])
        ->and(count($report['failedRows']))->toBe(2)
        ->and($report['failedRows'][0]['row'])->toBe(2)
        ->and($report['failedRows'][1]['row'])->toBe(3);

    expect(User::query()->where('email', 'sara@example.edu')->exists())->toBeTrue()
        ->and(User::query()->where('matric_no', 'PMS400001')->exists())->toBeFalse();
});

test('duplicate matric numbers within one file are rejected', function () {
    Mail::fake();

    $this->actingAs(User::factory()->jskkStaff()->create());

    $component = Livewire::test('pages::admin.roster.index')
        ->set('file', rosterCsv(
            'PMS500001,Duplicate Dan,dan@example.edu,,',
            'PMS500001,Duplicate Again,again@example.edu,,',
        ))
        ->call('import');

    $report = $component->get('report');

    expect(count($report['failedRows']))->toBe(1)
        ->and(implode('', $report['failedRows'][0]['messages']))->toContain('twice');
});

test('emails already used by another account are rejected', function () {
    User::factory()->create(['email' => 'taken@example.edu', 'matric_no' => 'PMS000999']);

    $this->actingAs(User::factory()->jskkStaff()->create());

    $component = Livewire::test('pages::admin.roster.index')
        ->set('file', rosterCsv('PMS600001,Clash Casey,taken@example.edu,,'))
        ->call('import');

    $report = $component->get('report');

    expect(count($report['failedRows']))->toBe(1)
        ->and(implode('', $report['failedRows'][0]['messages']))->toContain('already used');
});

test('files with more than the maximum row count are refused', function () {
    Mail::fake();

    $this->actingAs(User::factory()->jskkStaff()->create());

    $rows = [];

    foreach (range(1, RosterImporter::MAX_ROWS + 1) as $i) {
        $rows[] = sprintf('PMS9%05d,Bulk Bob,bulk%d@example.edu,,', $i, $i);
    }

    Livewire::test('pages::admin.roster.index')
        ->set('file', rosterCsv(...$rows))
        ->call('import')
        ->assertHasErrors(['file']);

    expect(User::count())->toBe(1); // Only the staff member.
    Mail::assertNothingQueued();
});

test('empty files are refused', function () {
    $this->actingAs(User::factory()->jskkStaff()->create());

    Livewire::test('pages::admin.roster.index')
        ->set('file', rosterCsv())
        ->call('import')
        ->assertHasErrors(['file']);
});
