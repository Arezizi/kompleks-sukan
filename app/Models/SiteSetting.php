<?php

namespace App\Models;

use Database\Factories\SiteSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Singleton row holding global operating configuration.
 *
 * @property int $id
 * @property bool $weather_banner_active
 * @property string|null $weather_banner_message
 * @property string|null $day_use_note
 * @property string $opening_time
 * @property string $closing_time
 * @property int $max_loan_minutes
 * @property int $max_active_loans
 * @property int $penalty_days
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['weather_banner_active', 'weather_banner_message', 'day_use_note', 'opening_time', 'closing_time', 'max_loan_minutes', 'max_active_loans', 'penalty_days'])]
class SiteSetting extends Model
{
    /** @use HasFactory<SiteSettingFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weather_banner_active' => 'boolean',
        ];
    }

    /**
     * Fetch the singleton settings row, creating it with defaults if absent.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'weather_banner_active' => false,
            'weather_banner_message' => null,
            'day_use_note' => null,
            'opening_time' => '17:00:00',
            'closing_time' => '18:30:00',
            'max_loan_minutes' => 120,
            'max_active_loans' => 2,
            'penalty_days' => 14,
        ]);
    }
}
