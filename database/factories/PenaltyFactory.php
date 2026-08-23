<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\Penalty;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Penalty>
 */
class PenaltyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'loan_id' => null,
            'starts_at' => Carbon::now()->subDay(),
            'ends_at' => Carbon::now()->addDays(14),
            'reason' => 'Late return of borrowed equipment.',
            'imposed_automatically' => true,
            'lifted_at' => null,
        ];
    }

    /**
     * A penalty tied to a specific late loan.
     */
    public function forLoan(Loan $loan): static
    {
        return $this->state(fn (array $attributes) => [
            'loan_id' => $loan->id,
        ]);
    }

    /**
     * A penalty lifted early by staff.
     */
    public function lifted(): static
    {
        return $this->state(fn (array $attributes) => [
            'lifted_at' => Carbon::now(),
            'lifted_by' => User::factory(),
        ]);
    }
}
