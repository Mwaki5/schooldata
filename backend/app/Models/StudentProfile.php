<?php

namespace App\Models;

use Database\Factories\StudentProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StudentProfile extends Model
{
    /** @use HasFactory<StudentProfileFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'national_id',
        'status',
        'first_name',
        'middle_name',
        'last_name',
        'phone',
        'gender',
        'date_of_birth',
        'county',
        'place_of_residence',
        'religion',
        'address',
        'profile_picture',
        'alternative_phone',
        'is_pwd',
        'disability_type',
        'disability_description',
        'sponsored',
        'sponsored_by',
        'index_no',
        'exam_year',
        'previous_school',
        'created_by',
        'updated_by',
    ];

    /**
     * The model's default attribute values, mirroring the migration.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'gender' => 'other',
        'is_pwd' => false,
        'sponsored' => false,
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'is_pwd' => 'boolean',
            'sponsored' => 'boolean',
        ];
    }

    /**
     * The auth account this profile signs in with.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The course enrolments recorded for this student.
     */
    public function enrolments(): HasMany
    {
        return $this->hasMany(CourseEnrolment::class, 'student_id');
    }

    /**
     * The student's current enrolment (highest id), loaded for the form
     * and rendered as the nested `enrolment` segment of the resource.
     */
    public function latestEnrolment(): HasOne
    {
        return $this->hasOne(CourseEnrolment::class, 'student_id')->latestOfMany();
    }

    /**
     * The "report session" registrations recorded for this student.
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(AcademicSessionRegistration::class, 'student_id');
    }

    /**
     * Get the activity log configuration for the model: log the fillable
     * attributes on every change, but skip no-op updates.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
