<?php

namespace App\Models;

use Database\Factories\LoanFactory;
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
 * @property string|null $token
 * @property int $user_id
 * @property int|null $reservation_id
 * @property int $issued_by
 * @property int|null $returned_to
 * @property Carbon $checked_out_at
 * @property Carbon $due_at
 * @property Carbon|null $returned_at
 * @property bool $card_collected
 * @property bool $card_returned
 * @property string|null $condition_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['token', 'user_id', 'reservation_id', 'issued_by', 'returned_to', 'checked_out_at', 'due_at', 'returned_at', 'card_collected', 'card_returned', 'condition_note', 'due_soon_notified_at'])]
class Loan extends Model
{
    /** @use HasFactory<LoanFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked_out_at' => 'datetime',
            'due_at' => 'datetime',
            'returned_at' => 'datetime',
            'card_collected' => 'boolean',
            'card_returned' => 'boolean',
        ];
    }

    /**
     * The student who borrowed the equipment.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The reservation this loan fulfilled, if any.
     *
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * The staff member who checked the gear out.
     *
     * @return BelongsTo<User, $this>
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * The staff member who accepted the return.
     *
     * @return BelongsTo<User, $this>
     */
    public function returnedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_to');
    }

    /**
     * Gear lines on this loan.
     *
     * @return HasMany<LoanItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(LoanItem::class);
    }

    /**
     * Penalties triggered by this loan.
     *
     * @return HasMany<Penalty, $this>
     */
    public function penalties(): HasMany
    {
        return $this->hasMany(Penalty::class);
    }

    /**
     * Loans that have not been returned yet.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->whereNull('returned_at');
    }

    /**
     * Loans past their due time without a return.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function overdue(Builder $query): Builder
    {
        return $query->active()->where('due_at', '<', now());
    }
}
