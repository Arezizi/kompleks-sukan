<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $matric_no
 * @property string|null $programme
 * @property string|null $phone
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property UserRole $role
 * @property string $password
 * @property string|null $setup_token
 * @property Carbon|null $setup_completed_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'matric_no', 'programme', 'phone'])]
#[Hidden(['password', 'setup_token', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'setup_completed_at' => 'datetime',
        ];
    }

    /**
     * Determine whether the user still owes first-login password setup.
     */
    public function isPendingSetup(): bool
    {
        return filled($this->setup_token) && $this->setup_completed_at === null;
    }

    /**
     * Hash an activation code for at-rest comparison against setup_token.
     */
    public static function hashSetupToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Determine whether the user is a campus administrator.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Determine whether the user may operate the JSKK counter area.
     */
    public function isJskkArea(): bool
    {
        return $this->role->isJskkArea();
    }

    /**
     * Determine whether the user may file and view incident reports.
     */
    public function canLogIncidents(): bool
    {
        return $this->role->canLogIncidents();
    }

    /**
     * Reservations made by this user.
     *
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Equipment loans taken out by this user.
     *
     * @return HasMany<Loan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * Borrowing penalties imposed on this user.
     *
     * @return HasMany<Penalty, $this>
     */
    public function penalties(): HasMany
    {
        return $this->hasMany(Penalty::class);
    }

    /**
     * Incident reports filed by this user.
     *
     * @return HasMany<Incident, $this>
     */
    public function incidentsReported(): HasMany
    {
        return $this->hasMany(Incident::class, 'reported_by');
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
