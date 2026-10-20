<?php

namespace App\Models;

use Database\Factories\AcademicSessionRegistrationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One "report session" action: a student reporting for one academic
 * session at a given module of their course ladder.
 *
 * A module is one session of study and three modules make one academic
 * year, so year_no / session_no are derivable from module_no but are
 * stored for direct querying.
 */
class AcademicSessionRegistration extends Model
{
    /** @use HasFactory<AcademicSessionRegistrationFactory> */
    use HasFactory, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'academic_session_id',
        'student_id',
        'course_stage_id',
        'module_no',
        'year_no',
        'session_no',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'module_no' => 'integer',
            'year_no' => 'integer',
            'session_no' => 'integer',
        ];
    }

    /**
     * The academic session this registration reports for.
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * The student who reported for the session.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    /**
     * The course stage this registration targeted, when recorded.
     */
    public function courseStage(): BelongsTo
    {
        return $this->belongsTo(CourseStage::class);
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
