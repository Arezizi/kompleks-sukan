<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Penalty;
use App\Models\SiteSetting;
use App\Models\User;

/**
 * Creates and resolves borrowing penalties.
 */
final class PenaltyService
{
    /**
     * The user's currently active penalty, if any.
     */
    public function activePenaltyFor(User $user): ?Penalty
    {
        return Penalty::query()
            ->where('user_id', $user->getKey())
            ->whereNull('lifted_at')
            ->where('ends_at', '>', now())
            ->first();
    }

    /**
     * Whether the user is blocked from borrowing right now.
     */
    public function blocks(User $user): bool
    {
        return $this->activePenaltyFor($user) !== null;
    }

    /**
     * Penalise a loan that came back after its due time.
     */
    public function imposeLateReturn(Loan $loan): Penalty
    {
        $days = SiteSetting::current()->penalty_days;
        $startsAt = $loan->returned_at ?? now();

        /** @var Penalty $penalty */
        $penalty = $loan->penalties()->create([
            'user_id' => $loan->user_id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDays($days),
            'reason' => __('Late return of borrowed equipment.'),
            'imposed_automatically' => true,
        ]);

        return $penalty;
    }

    /**
     * Penalise an overdue unreturned loan.
     */
    public function imposeOverdue(Loan $loan): ?Penalty
    {
        if ($loan->penalties()->exists()) {
            return null;
        }

        $days = SiteSetting::current()->penalty_days;
        $startsAt = now();

        /** @var Penalty $penalty */
        $penalty = $loan->penalties()->create([
            'user_id' => $loan->user_id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDays($days),
            'reason' => __('Loan overdue past due time without return.'),
            'imposed_automatically' => true,
        ]);

        return $penalty;
    }
}
