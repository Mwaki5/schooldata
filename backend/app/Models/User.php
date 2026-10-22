<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * Clear live references when a user is removed.
     */
    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            if ($user->isForceDeleting()) {
                return;
            }

            // A soft-deleted user must not remain the head of a
            // department; hard deletes are cleared by the foreign key.
            Department::query()
                ->where('hod_id', $user->id)
                ->get()
                ->each(fn (Department $department) => $department->update(['hod_id' => null]));
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'login_id',
        'password',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'date_of_birth',
        'nationality',
        'county',
        'place_of_birth',
        'religion',
        'address',
        'city',
        'postal_code',
        'profile_picture',
        'alternative_phone',
        'is_pwd',
        'disability_type',
        'disability_description',
        'must_reset_password',
        'password_changed_at',
        'failed_login_attempts',
        'last_failed_login_at',
        'locked_until',
        'two_factor_enabled',
        'status',
        'last_login_at',
        'last_login_ip',
        'last_login_user_agent',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'date_of_birth' => 'date',
            'is_pwd' => 'boolean',
            'must_reset_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'failed_login_attempts' => 'integer',
            'last_failed_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Compose the display name from the split name parts, e.g.
     * "Jane Ann Doe".
     */
    public static function composeName(?string $firstName, ?string $middleName, ?string $lastName): string
    {
        return trim(implode(' ', array_filter([
            trim((string) $firstName),
            trim((string) $middleName),
            trim((string) $lastName),
        ], fn (string $part) => $part !== '')));
    }

    /**
     * The auth guard Spatie resolves for this user's roles and permissions.
     *
     * Laravel's Authenticate middleware re-points the default guard at
     * `api` as soon as a request authenticates, so without this pin the
     * same user could resolve `web` before a request and `api` after one
     * — missing the catalog rows either way. The catalog is seeded for
     * `web`, and RolePermissionSeeder reads this method so the two can
     * never drift.
     */
    public function guardName(): string
    {
        return 'web';
    }

    /**
     * The student profile backing this account, when it belongs to a student.
     */
    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Return a key value array containing any custom claims to add to the JWT.
     *
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return [];
    }
}
