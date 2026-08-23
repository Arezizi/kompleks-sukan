<?php

namespace App\Console\Commands;

use App\Mail\DueSoonMail;
use App\Mail\OverduePenaltyMail;
use App\Models\Loan;
use App\Services\PenaltyService;
use App\Services\ReservationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ProcessBorrowing extends Command
{
    protected $signature = 'borrowing:process';

    protected $description = 'Process stale reservations, due-soon loan reminders, and overdue loan penalties';

    public function handle(ReservationService $reservations, PenaltyService $penalties): int
    {
        $expired = $reservations->expireStale();

        $dueSoonCount = 0;
        Loan::query()
            ->whereNull('returned_at')
            ->whereNull('due_soon_notified_at')
            ->where('due_at', '>', now())
            ->where('due_at', '<=', now()->addMinutes(30))
            ->with('user')
            ->each(function (Loan $loan) use (&$dueSoonCount): void {
                if ($loan->user !== null) {
                    Mail::to($loan->user->email)->queue(new DueSoonMail($loan));
                    $loan->forceFill(['due_soon_notified_at' => now()])->save();
                    $dueSoonCount++;
                }
            });

        $overdueCount = 0;
        Loan::query()
            ->overdue()
            ->with(['user', 'penalties'])
            ->each(function (Loan $loan) use ($penalties, &$overdueCount): void {
                if ($loan->user !== null && ! $loan->penalties()->exists()) {
                    $penalty = $penalties->imposeOverdue($loan);
                    if ($penalty !== null) {
                        Mail::to($loan->user->email)->queue(new OverduePenaltyMail($loan, $penalty));
                        $overdueCount++;
                    }
                }
            });

        $this->info("Processed: {$expired} expired reservations, {$dueSoonCount} due-soon alerts, {$overdueCount} overdue penalties.");

        return self::SUCCESS;
    }
}
