<?php

namespace App\Models;

use App\Enums\EnrolmentStatus;
use Database\Factories\CourseEnrolmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CourseEnrolment extends Model
{
    /** @use HasFactory<CourseEnrolmentFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'student_id',
        'course_id',
        'course_stage_id',
        'curriculum_id',
        'academic_session_id',
        'certification_level_id',
        'enrolment_date',
        'status',
        'remarks',
        'created_by',
        'updated_by',
    ];

    /**
     * The model's default attribute values, mirroring the migration.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'ENROLLED',
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enrolment_date' => 'date',
            'status' => EnrolmentStatus::class,
        ];
    }

    /**
     * The student this enrolment belongs to.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    /**
     * The course the student enrolled in.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * The progression stage this registration was accepted for, when the
     * enrolment predates stage tracking this is null.
     */
    public function courseStage(): BelongsTo
    {
        return $this->belongsTo(CourseStage::class);
    }

    /**
     * The exam-body curriculum the enrolment follows.
     */
    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    /**
     * The academic session (term) the enrolment belongs to, if any.
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * The certification level the student entered at.
     */
    public function certificationLevel(): BelongsTo
    {
        return $this->belongsTo(CertificationLevel::class);
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
