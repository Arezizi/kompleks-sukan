<?php

namespace Database\Factories;

use App\Enums\FacilityStatus;
use App\Models\Facility;
use App\Models\FacilityStatusLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FacilityStatusLog>
 */
class FacilityStatusLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'status' => fake()->randomElement(FacilityStatus::cases()),
            'note' => fake()->optional()->sentence(),
            'changed_by' => User::factory(),
        ];
    }
}
