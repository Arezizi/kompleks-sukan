<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property ReservationStatus $status
 * @property string $token
 * @property Carbon|null $pickup_deadline
 * @property int|null $decided_by
 * @property string|null $decision_note
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'status', 'token', 'pickup_deadline', 'decision_note', 'note'])]
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'pickup_deadline' => 'datetime',
        ];
    }

    /**
     * The student who made this reservation.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The staff member who approved or rejected the reservation.
     *
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * Gear lines requested in this reservation.
     *
     * @return HasMany<ReservationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    /**
     * Reservations that still hold stock (pending or approved).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function holdingStock(Builder $query): Builder
    {
        return $query->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Approved]);
    }
}
