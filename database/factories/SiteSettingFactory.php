<?php

namespace Database\Factories;

use App\Models\SiteSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteSetting>
 */
class SiteSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'weather_banner_active' => false,
            'weather_banner_message' => null,
            'day_use_note' => 'Day use only — all areas close at night.',
            'opening_time' => '17:00:00',
            'closing_time' => '18:30:00',
            'max_loan_minutes' => 120,
            'max_active_loans' => 2,
            'penalty_days' => 14,
        ];
    }
}
