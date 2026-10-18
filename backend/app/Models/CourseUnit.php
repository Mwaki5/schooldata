<?php

namespace App\Models;

use Database\Factories\CourseUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A unit's delivery inside one course under one curriculum: where the
 * reusable master unit is taught, when it is taken ("Year 1 Term 1"),
 * whether it is core or elective, and the teaching load that applies to
 * that specific delivery. The unit's global code lives on the master.
 */
class CourseUnit extends Model
{
    /** @use HasFactory<CourseUnitFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'course_units';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'unit_id',
        'course_id',
        'curriculum_id',
        'core',
        'time_offered',
        'taught_hours',
        'credit_factor',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'core' => true,
        'is_active' => true,
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'core' => 'boolean',
            'taught_hours' => 'integer',
            'credit_factor' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The master unit this assignment delivers.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * The course the unit is assigned to.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * The curriculum version the unit is taught under.
     */
    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
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
