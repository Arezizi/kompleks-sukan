<?php

namespace Database\Seeders;

use App\Enums\FacilityCategory;
use App\Models\Facility;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FacilitySeeder extends Seeder
{
    /**
     * The full PMS sports complex catalog: name => [category, location, capacity, description].
     *
     * @var array<string, array{0: FacilityCategory, 1: string, 2: ?int, 3: string}>
     */
    protected array $catalog = [
        'Badminton Court 1' => [FacilityCategory::Indoor, 'Sports Complex Hall', 8, 'Standard badminton court with professional lighting.'],
        'Badminton Court 2' => [FacilityCategory::Indoor, 'Sports Complex Hall', 8, 'Standard badminton court with professional lighting.'],
        'Badminton Court 3' => [FacilityCategory::Indoor, 'Sports Complex Hall', 8, 'Standard badminton court with professional lighting.'],
        'Badminton Court 4' => [FacilityCategory::Indoor, 'Sports Complex Hall', 8, 'Standard badminton court with professional lighting.'],
        'Multipurpose Court' => [FacilityCategory::Indoor, 'Sports Complex Hall', 20, 'Convertible court for volleyball or netball — nets set on request.'],
        'Ping Pong Table 1' => [FacilityCategory::Indoor, 'Sports Complex Hall', 4, 'Table tennis table with paddles available at the counter.'],
        'Ping Pong Table 2' => [FacilityCategory::Indoor, 'Sports Complex Hall', 4, 'Table tennis table with paddles available at the counter.'],
        'Gym (Fitness Centre)' => [FacilityCategory::Indoor, 'Sports Complex Level 1', 25, 'Weight and cardio equipment. Gender-specific schedules apply — check the timetable at the entrance.'],
        'Football Field' => [FacilityCategory::Outdoor, 'Main Field', 40, 'Full-size football field with natural grass.'],
        'Athletics Track' => [FacilityCategory::Outdoor, 'Main Field', 60, 'Eight-lane running track surrounding the football field.'],
        'Archery Range' => [FacilityCategory::Outdoor, 'Field East Wing', 16, 'Outdoor archery range. Bows are issued only under supervisor presence.'],
        'Volleyball Court 1' => [FacilityCategory::Outdoor, 'Court Block A', 14, 'Outdoor sand-court volleyball.'],
        'Volleyball Court 2' => [FacilityCategory::Outdoor, 'Court Block A', 14, 'Outdoor sand-court volleyball.'],
        'Volleyball Court 3' => [FacilityCategory::Outdoor, 'Court Block A', 14, 'Outdoor sand-court volleyball.'],
        'Futsal Court' => [FacilityCategory::Outdoor, 'Court Block B', 14, 'Outdoor futsal court with synthetic turf.'],
        'Handball Court' => [FacilityCategory::Outdoor, 'Court Block B', 16, 'Outdoor handball court.'],
        'Sepak Takraw Court 1' => [FacilityCategory::Outdoor, 'Court Block C', 6, 'Regulation takraw court.'],
        'Sepak Takraw Court 2' => [FacilityCategory::Outdoor, 'Court Block C', 6, 'Regulation takraw court.'],
        'Sepak Takraw Court 3' => [FacilityCategory::Outdoor, 'Court Block C', 6, 'Regulation takraw court.'],
        'Netball Court' => [FacilityCategory::Outdoor, 'Court Block C', 14, 'Standard outdoor netball court.'],
        'Basketball Court 1' => [FacilityCategory::Outdoor, 'Court Block D', 20, 'Half-court basketball with hoop sets.'],
        'Basketball Court 2' => [FacilityCategory::Outdoor, 'Court Block D', 20, 'Half-court basketball with hoop sets.'],
        'Rugby Field' => [FacilityCategory::Outdoor, 'Main Field', 40, 'Full-size rugby field shared with football scheduling.'],
        'Hockey Field' => [FacilityCategory::Outdoor, 'Field West Wing', 30, 'Field hockey pitch with goals.'],
        'Petanque Court 1' => [FacilityCategory::Outdoor, 'Court Block D', 8, 'Gravel petanque lane. Boules available at the counter.'],
        'Petanque Court 2' => [FacilityCategory::Outdoor, 'Court Block D', 8, 'Gravel petanque lane. Boules available at the counter.'],
    ];

    /**
     * Seed the facility catalog.
     */
    public function run(): void
    {
        $sort = 0;

        foreach ($this->catalog as $name => [$category, $location, $capacity, $description]) {
            Facility::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'category' => $category,
                    'location' => $location,
                    'capacity' => $capacity,
                    'description' => $description,
                    'rules' => 'Day use only — all areas close at night.',
                    'is_active' => true,
                    'sort_order' => $sort++,
                ],
            );
        }
    }
}
