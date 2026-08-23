<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Database\Factories\EquipmentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $total_quantity
 * @property string|null $photo_path
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'description', 'total_quantity', 'photo_path', 'is_active'])]
class EquipmentType extends Model
{
    /** @use HasFactory<EquipmentTypeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Accessories suggested when this equipment type is borrowed.
     *
     * @return BelongsToMany<self, $this>
     */
    public function suggestedAccessories(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'accessory_suggestions',
            'equipment_type_id',
            'suggested_equipment_type_id',
        )->withPivot('prompt');
    }

    /**
     * Facilities where this gear is typically used.
     *
     * @return BelongsToMany<Facility, $this>
     */
    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class);
    }

    /**
     * Borrowed items of this type.
     *
     * @return HasMany<LoanItem, $this>
     */
    public function loanItems(): HasMany
    {
        return $this->hasMany(LoanItem::class);
    }

    /**
     * Reserved items of this type.
     *
     * @return HasMany<ReservationItem, $this>
     */
    public function reservationItems(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    /**
     * Quantity currently checked out on active loans.
     */
    public function onLoanQuantity(): int
    {
        return (int) LoanItem::query()
            ->where($this->getForeignKey(), $this->getKey())
            ->whereHas('loan', fn ($query) => $query->whereNull('returned_at'))
            ->sum('quantity');
    }

    /**
     * Quantity held by pending or approved reservations.
     */
    public function reservedQuantity(?int $exceptReservationId = null): int
    {
        return (int) ReservationItem::query()
            ->where($this->getForeignKey(), $this->getKey())
            ->whereHas('reservation', fn ($query) => $query
                ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Approved])
                ->when($exceptReservationId !== null, fn ($query) => $query->whereKeyNot($exceptReservationId))
            )
            ->sum('quantity');
    }

    /**
     * Quantity a student may still request right now.
     */
    public function availableQuantity(?int $exceptReservationId = null): int
    {
        return max(
            0,
            $this->total_quantity - $this->onLoanQuantity() - $this->reservedQuantity($exceptReservationId),
        );
    }
}
