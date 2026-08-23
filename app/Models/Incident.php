<?php

namespace App\Models;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentType;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $reported_by
 * @property int|null $facility_id
 * @property int|null $loan_id
 * @property IncidentType $type
 * @property IncidentSeverity $severity
 * @property string $title
 * @property string $description
 * @property string|null $action_taken
 * @property Carbon $occurred_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['reported_by', 'facility_id', 'loan_id', 'type', 'severity', 'title', 'description', 'action_taken', 'occurred_at', 'resolved_at'])]
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => IncidentType::class,
            'severity' => IncidentSeverity::class,
            'occurred_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * The staff member who filed this report.
     *
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * The facility where the incident happened, if tied to one.
     *
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * The loan involved, if any (e.g., damaged borrowed gear).
     *
     * @return BelongsTo<Loan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
