<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\EquipmentType;
use App\Models\Loan;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        SiteSetting::current();

        User::query()->updateOrCreate(
            ['email' => 'admin@pms.example'],
            [
                'name' => 'JSKK Administrator',
                'password' => 'password',
                'role' => UserRole::Admin,
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'jskk@pms.example'],
            [
                'name' => 'JSKK Counter Staff',
                'password' => 'password',
                'role' => UserRole::JskkStaff,
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'clinic@pms.example'],
            [
                'name' => 'Clinic Staff',
                'password' => 'password',
                'role' => UserRole::ClinicStaff,
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'student@pms.example'],
            [
                'name' => 'Siti Aminah',
                'matric_no' => 'PMS100001',
                'programme' => 'Diploma Kejuruteraan Awam',
                'phone' => '012-3456789',
                'password' => 'password',
                'role' => UserRole::Student,
                'email_verified_at' => now(),
            ],
        );

        $this->call([
            FacilitySeeder::class,
            EquipmentTypeSeeder::class,
        ]);

        // Demo data for prototype testing
        $student = User::query()->where('email', 'student@pms.example')->first();
        $staff = User::query()->where('email', 'jskk@pms.example')->first();
        $racket = EquipmentType::query()->where('slug', 'badminton-racket')->first();

        if ($student !== null && $staff !== null && $racket !== null) {
            Loan::factory()
                ->withItem($racket, 1)
                ->for($student)
                ->state(['issued_by' => $staff->id])
                ->create();
        }
    }
}
