<?php

use App\Models\EquipmentType;
use App\Models\Loan;
use App\Models\Penalty;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

test('reservation approval queues a mail notification', function () {
    Queue::fake();

    $staff = User::factory()->jskkStaff()->create();
    $student = User::factory()->create();
    $type = EquipmentType::factory()->create(['total_quantity' => 5]);

    $reservation = app(ReservationService::class)->create($student, [$type->id => 1]);
    app(ReservationService::class)->decide($reservation, $staff, true);

    Queue::assertPushed(SendQueuedMailable::class, function ($job) use ($student) {
        return str_contains(get_class($job->mailable), 'ReservationApprovedMail') && $job->mailable->hasTo($student->email);
    });
});

test('process borrowing scheduler fires due-soon and overdue mails and imposes penalties', function () {
    Queue::fake();
    Carbon::setTestNow('2026-08-23 17:00:00');

    $student = User::factory()->create();
    $type = EquipmentType::factory()->create();

    // Due in 20 minutes (due soon)
    $dueSoonLoan = Loan::factory()
        ->withItem($type, 1)
        ->for($student)
        ->state(['due_at' => now()->addMinutes(20)])
        ->create();

    // Overdue by 1 hour
    $overdueLoan = Loan::factory()
        ->withItem($type, 1)
        ->for($student)
        ->state(['checked_out_at' => now()->subHours(3), 'due_at' => now()->subHour()])
        ->create();

    Artisan::call('borrowing:process');

    Queue::assertPushed(SendQueuedMailable::class, function ($job) {
        return str_contains(get_class($job->mailable), 'DueSoonMail');
    });
    Queue::assertPushed(SendQueuedMailable::class, function ($job) {
        return str_contains(get_class($job->mailable), 'OverduePenaltyMail');
    });

    expect(Penalty::query()->where('user_id', $student->id)->exists())->toBeTrue();

    Carbon::setTestNow();
});

test('student dashboard shows active loans, countdowns and penalty banner', function () {
    $student = User::factory()->has(Penalty::factory())->create();
    $type = EquipmentType::factory()->create(['name' => 'Badminton Racket']);
    Loan::factory()->withItem($type, 1)->for($student)->create();

    $this->actingAs($student)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Badminton Racket')
        ->assertSee('Active borrowing ban');
});
