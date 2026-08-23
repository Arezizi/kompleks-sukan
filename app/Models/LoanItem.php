<?php

namespace App\Models;

use Database\Factories\LoanItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $loan_id
 * @property int $equipment_type_id
 * @property int $quantity
 * @property string|null $condition_on_return
 */
#[Fillable(['loan_id', 'equipment_type_id', 'quantity', 'condition_on_return'])]
class LoanItem extends Model
{
    /** @use HasFactory<LoanItemFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * The loan this line belongs to.
     *
     * @return BelongsTo<Loan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * The equipment type being borrowed.
     *
     * @return BelongsTo<EquipmentType, $this>
     */
    public function equipmentType(): BelongsTo
    {
        return $this->belongsTo(EquipmentType::class);
    }
}
