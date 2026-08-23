<?php

namespace Database\Factories;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentType;
use App\Models\Facility;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reported_by' => User::factory(),
            'facility_id' => Facility::factory(),
            'type' => fake()->randomElement(IncidentType::cases()),
            'severity' => IncidentSeverity::Low,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'action_taken' => null,
            'occurred_at' => Carbon::now()->subMinutes(fake()->numberBetween(5, 600)),
            'resolved_at' => null,
        ];
    }
}
