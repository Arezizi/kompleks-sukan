<?php

namespace App\Models;

use Database\Factories\ReservationItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $reservation_id
 * @property int $equipment_type_id
 * @property int $quantity
 */
#[Fillable(['reservation_id', 'equipment_type_id', 'quantity'])]
class ReservationItem extends Model
{
    /** @use HasFactory<ReservationItemFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * The reservation this line belongs to.
     *
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * The equipment type being reserved.
     *
     * @return BelongsTo<EquipmentType, $this>
     */
    public function equipmentType(): BelongsTo
    {
        return $this->belongsTo(EquipmentType::class);
    }
}
