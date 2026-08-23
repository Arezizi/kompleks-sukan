<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

class ExpireStaleReservations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire approved reservations whose pickup window has lapsed, releasing their stock';

    /**
     * Execute the console command.
     */
    public function handle(ReservationService $reservations): int
    {
        $expired = $reservations->expireStale();

        if ($expired > 0) {
            $this->info("Expired {$expired} reservation(s).");
        } else {
            $this->comment('Nothing to expire.');
        }

        return self::SUCCESS;
    }
}
