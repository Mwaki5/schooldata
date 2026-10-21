<?php

namespace App\Models;

use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A unit in the catalogue: its own identity (code, name, description),
 * independent of any course. How the unit is delivered inside a course —
 * module, training hours, credit factor, core / elective, active — lives
 * on the `course_units` links, so one unit may be offered by several
 * courses with different teaching loads.
 */
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'created_by',
        'updated_by',
    ];

    /**
     * The course + curriculum deliveries of this unit.
     */
    public function courseUnits(): HasMany
    {
        return $this->hasMany(CourseUnit::class);
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
