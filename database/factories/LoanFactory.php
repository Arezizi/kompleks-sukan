<?php

namespace Database\Factories;

use App\Models\EquipmentType;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token' => Str::random(40),
            'user_id' => User::factory(),
            'issued_by' => User::factory(),
            'checked_out_at' => Carbon::now()->subHour(),
            'due_at' => Carbon::now()->addHour(),
            'returned_at' => null,
            'card_collected' => true,
            'card_returned' => false,
            'condition_note' => null,
        ];
    }

    /**
     * A loan carrying one gear line of the given equipment type.
     */
    public function withItem(EquipmentType $type, int $quantity = 1): static
    {
        return $this->has(
            LoanItem::factory()->state([
                'equipment_type_id' => $type->id,
                'quantity' => $quantity,
            ]),
            'items',
        );
    }

    /**
     * A loan that has been returned on time.
     */
    public function returned(): static
    {
        return $this->state(fn (array $attributes) => [
            'returned_at' => $attributes['due_at'],
            'card_returned' => true,
            'returned_to' => User::factory(),
        ]);
    }

    /**
     * A loan returned after its due time (triggers a penalty in the engine).
     */
    public function returnedLate(): static
    {
        return $this->state(fn (array $attributes) => [
            'returned_at' => Carbon::parse($attributes['due_at'])->addMinutes(30),
            'card_returned' => true,
            'returned_to' => User::factory(),
        ]);
    }

    /**
     * A loan whose due time has passed without a return.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'checked_out_at' => Carbon::now()->subHours(3),
            'due_at' => Carbon::now()->subHour(),
        ]);
    }
}
