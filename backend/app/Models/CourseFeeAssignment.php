<?php

namespace App\Models;

use App\Enums\FeeSpan;
use Database\Factories\CourseFeeAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * The fee a course + curriculum pair charges: one live assignment per
 * pair, pointing at the fee structure whose items add up to the charge
 * and the span it repeats on. The pair is what an enrolment carries, so
 * invoicing locates a student's fee from their enrolment alone.
 */
class CourseFeeAssignment extends Model
{
    /** @use HasFactory<CourseFeeAssignmentFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'course_id',
        'curriculum_id',
        'fee_structure_id',
        'span',
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
            'span' => FeeSpan::class,
        ];
    }

    /**
     * The course this fee is assigned to.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * The curriculum the course charges it under.
     */
    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    /**
     * The fee structure whose items make up the charge.
     */
    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
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
