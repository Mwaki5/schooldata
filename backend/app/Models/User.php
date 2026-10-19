<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

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
        'role',
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
     * The model's default attribute values.
     *
     * New registrations omit `role`, so the default is applied at insert
     * time instead of relying on the database default alone.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'student',
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
            'role' => Role::class,
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
     * Whether the user holds one of the given roles.
     */
    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
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
