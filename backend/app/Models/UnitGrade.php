<?php

namespace App\Models;

use App\Enums\UnitExamType;
use Database\Factories\UnitGradeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One mark awarded to a student for one course unit: a single theory or
 * practical paper (exam_no 1–3) in one academic session. Three of each
 * type fully grade the unit, but fewer are allowed. The mark hangs off
 * the course-unit link the student registered, not the bare unit.
 */
class UnitGrade extends Model
{
    /** @use HasFactory<UnitGradeFactory> */
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
        'exam_type',
        'exam_no',
        'mark',
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
            'exam_type' => UnitExamType::class,
            'exam_no' => 'integer',
            'mark' => 'decimal:2',
        ];
    }

    /**
     * The academic session the mark was awarded in.
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * The course-unit link that was graded.
     */
    public function courseUnit(): BelongsTo
    {
        return $this->belongsTo(CourseUnit::class);
    }

    /**
     * The graded student.
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
