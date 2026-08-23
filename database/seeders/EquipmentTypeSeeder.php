<?php

namespace Database\Seeders;

use App\Models\EquipmentType;
use App\Models\Facility;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EquipmentTypeSeeder extends Seeder
{
    /**
     * Core borrowable gear: name => [quantity, description].
     *
     * @var array<string, array{0: int, 1: string}>
     */
    protected array $gear = [
        'Netball' => [20, 'Size 5 match netball.'],
        'Volleyball' => [20, 'Indoor/outdoor match volleyball.'],
        'Football' => [15, 'Size 5 football for full-field play.'],
        'Futsal Ball' => [15, 'Low-bounce size 4 futsal ball.'],
        'Rugby Ball' => [12, 'Size 5 rugby ball.'],
        'Handball' => [12, 'Size 3 handball.'],
        'Hockey Stick' => [24, 'Field hockey stick in various lengths.'],
        'Badminton Racket' => [40, 'Aluminium-frame badminton racket.'],
    ];

    /**
     * Accessory-only items suggested during checkout.
     *
     * @var array<string, array{0: int, 1: string}>
     */
    protected array $accessories = [
        'Shuttlecocks (Tube of 6)' => [30, 'Nylon shuttlecock tube — one tube per court per session.'],
    ];

    /**
     * Cross-prompts shown when the key gear is borrowed:
     * gear slug => [[suggested slug, prompt]].
     *
     * @var array<string, array<int, array{0: string, 1: string}>>
     */
    protected array $suggestions = [
        'badminton-racket' => [
            ['shuttlecocks-tube-of-6', 'Add a tube of shuttlecocks for your session?'],
        ],
    ];

    /**
     * Gear shown on facility detail pages: facility type slug prefix => gear slugs.
     *
     * @var array<string, array<int, string>>
     */
    protected array $facilityLinks = [
        'badminton-court' => ['badminton-racket', 'shuttlecocks-tube-of-6'],
        'multipurpose-court' => ['volleyball', 'netball'],
        'volleyball-court' => ['volleyball'],
        'netball-court' => ['netball'],
        'football-field' => ['football'],
        'futsal-court' => ['futsal-ball'],
        'rugby-field' => ['rugby-ball'],
        'handball-court' => ['handball'],
        'hockey-field' => ['hockey-stick'],
    ];

    /**
     * Seed equipment types, accessory cross-prompts and facility links.
     */
    public function run(): void
    {
        foreach ($this->gear + $this->accessories as $name => [$quantity, $description]) {
            EquipmentType::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => $description,
                    'total_quantity' => $quantity,
                    'is_active' => true,
                ],
            );
        }

        foreach ($this->suggestions as $gearSlug => $suggestions) {
            $gear = EquipmentType::query()->where('slug', $gearSlug)->firstOrFail();

            foreach ($suggestions as [$suggestedSlug, $prompt]) {
                $suggested = EquipmentType::query()->where('slug', $suggestedSlug)->firstOrFail();

                $gear->suggestedAccessories()->syncWithoutDetaching([
                    $suggested->id => ['prompt' => $prompt],
                ]);
            }
        }

        foreach ($this->facilityLinks as $typePrefix => $gearSlugs) {
            $gearIds = EquipmentType::query()->whereIn('slug', $gearSlugs)->pluck('id');

            Facility::query()
                ->where('slug', 'like', $typePrefix.'%')
                ->get()
                ->each(fn (Facility $facility) => $facility->equipmentTypes()->sync($gearIds));
        }
    }
}
