<?php

namespace App\Models;

use Database\Factories\PenaltyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $loan_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $reason
 * @property bool $imposed_automatically
 * @property Carbon|null $lifted_at
 * @property int|null $lifted_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'loan_id', 'starts_at', 'ends_at', 'reason', 'imposed_automatically', 'lifted_at', 'lifted_by'])]
class Penalty extends Model
{
    /** @use HasFactory<PenaltyFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'imposed_automatically' => 'boolean',
            'lifted_at' => 'datetime',
        ];
    }

    /**
     * The penalised student.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The loan that triggered this penalty, if any.
     *
     * @return BelongsTo<Loan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * The staff member who lifted the penalty early.
     *
     * @return BelongsTo<User, $this>
     */
    public function liftedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }

    /**
     * Penalties currently blocking the student from borrowing.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->whereNull('lifted_at')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now());
    }
}
