<?php

namespace App\Models;

use Database\Factories\UnitRegistrationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One course unit a student registered for in one academic session,
 * after reporting for that session. The registration points at the
 * course-unit link, not the bare catalogue unit, so it commits to a
 * specific course, curriculum and teaching load. `approved_by` stays
 * null until a staff member signs the registration off.
 */
class UnitRegistration extends Model
{
    /** @use HasFactory<UnitRegistrationFactory> */
    use HasFactory, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'academic_session_id',
        'course_unit_id',
        'student_id',
        'approved_by',
        'created_by',
        'updated_by',
    ];

    /**
     * The academic session the unit is registered for.
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * The course-unit link the student registered: through it live the
     * unit itself and the course + curriculum delivering it.
     */
    public function courseUnit(): BelongsTo
    {
        return $this->belongsTo(CourseUnit::class);
    }

    /**
     * The student who registered the unit.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
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
