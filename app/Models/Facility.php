<?php

namespace App\Models;

use App\Enums\FacilityCategory;
use App\Enums\FacilityStatus;
use Database\Factories\FacilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property FacilityCategory $category
 * @property string|null $location
 * @property string|null $description
 * @property string|null $rules
 * @property string|null $image_path
 * @property int|null $capacity
 * @property FacilityStatus $status
 * @property string|null $status_note
 * @property int|null $status_changed_by
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'category', 'location', 'description', 'rules', 'image_path', 'capacity', 'status', 'status_note', 'status_changed_by', 'sort_order', 'is_active'])]
class Facility extends Model
{
    /** @use HasFactory<FacilityFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => FacilityCategory::class,
            'status' => FacilityStatus::class,
        ];
    }

    /**
     * Get the status change history for this facility.
     *
     * @return HasMany<FacilityStatusLog, $this>
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(FacilityStatusLog::class);
    }

    /**
     * The staff member who last changed the status.
     *
     * @return BelongsTo<User, $this>
     */
    public function statusChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }

    /**
     * Incidents recorded against this facility.
     *
     * @return HasMany<Incident, $this>
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    /**
     * Borrowable gear issued at the counter for use in this facility.
     *
     * @return BelongsToMany<EquipmentType, $this>
     */
    public function equipmentTypes(): BelongsToMany
    {
        return $this->belongsToMany(EquipmentType::class)->orderBy('name');
    }

    /**
     * The shared facility type derived from the name, e.g.
     * "Badminton Court 3" -> "Badminton Court".
     */
    public function typeName(): string
    {
        return (string) preg_replace('/\s+\d+$/D', '', $this->name);
    }

    /**
     * URL-safe identifier of the derived facility type.
     */
    public function typeSlug(): string
    {
        return Str::slug($this->typeName());
    }

    /**
     * Scope to publicly visible facilities.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function visible(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope ordered for public display.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function ordered(Builder $query): Builder
    {
        return $query->orderBy('category')->orderBy('sort_order')->orderBy('name');
    }
}
