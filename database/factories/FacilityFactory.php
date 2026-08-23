<?php

namespace Database\Factories;

use App\Enums\FacilityCategory;
use App\Enums\FacilityStatus;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Facility>
 */
class FacilityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->word().' '.fake()->word()).' Court';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'category' => fake()->randomElement(FacilityCategory::cases()),
            'location' => 'Sports Complex',
            'description' => fake()->sentence(),
            'rules' => null,
            'capacity' => fake()->numberBetween(4, 40),
            'status' => FacilityStatus::Open,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * An indoor facility.
     */
    public function indoor(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => FacilityCategory::Indoor,
        ]);
    }

    /**
     * An outdoor facility.
     */
    public function outdoor(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => FacilityCategory::Outdoor,
        ]);
    }

    /**
     * A facility currently under maintenance.
     */
    public function underMaintenance(string $note = 'Scheduled maintenance'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FacilityStatus::Maintenance,
            'status_note' => $note,
        ]);
    }

    /**
     * A facility closed to the public.
     */
    public function closed(string $note = 'Closed for private event'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FacilityStatus::Closed,
            'status_note' => $note,
        ]);
    }
}
