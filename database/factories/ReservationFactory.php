<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
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
            'status' => ReservationStatus::Pending,
            'token' => Str::random(40),
            'pickup_deadline' => null,
            'decision_note' => null,
            'note' => null,
        ];
    }

    /**
     * A reservation approved by staff, awaiting counter pickup.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::Approved,
            'decided_by' => User::factory(),
            'pickup_deadline' => now()->addHours(24),
        ]);
    }

    /**
     * A reservation rejected by staff.
     */
    public function rejected(?string $note = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::Rejected,
            'decided_by' => User::factory(),
            'decision_note' => $note ?? fake()->sentence(),
        ]);
    }

    /**
     * A reservation cancelled by the student.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::Cancelled,
        ]);
    }

    /**
     * A reservation whose pickup window lapsed.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::Expired,
        ]);
    }

    /**
     * A reservation converted into a loan at the counter.
     */
    public function fulfilled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::Fulfilled,
            'decided_by' => User::factory(),
            'pickup_deadline' => now(),
        ]);
    }
}
