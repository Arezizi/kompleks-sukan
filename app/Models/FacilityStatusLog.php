<?php

namespace App\Models;

use App\Enums\FacilityStatus;
use Database\Factories\FacilityStatusLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Audit trail entry for facility status changes.
 *
 * @property int $id
 * @property int $facility_id
 * @property FacilityStatus $status
 * @property string|null $note
 * @property int|null $changed_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['facility_id', 'status', 'note', 'changed_by'])]
class FacilityStatusLog extends Model
{
    /** @use HasFactory<FacilityStatusLogFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FacilityStatus::class,
        ];
    }

    /**
     * The facility whose status changed.
     *
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * The staff member who made the change.
     *
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
