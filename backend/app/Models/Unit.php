<?php

namespace App\Models;

use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A unit of study as it is delivered inside one course + curriculum
 * pair: its own record, so two courses (or two curricula of the same
 * course) may use the same unit code for different units, and a
 * curriculum change starts a fresh record. The code is unique within
 * the (course, curriculum) pair among live rows only.
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
        'course_id',
        'curriculum_id',
        'code',
        'name',
        'description',
        'modules_taught',
        'taught_hours',
        'credit_factor',
        'core',
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
            'modules_taught' => 'integer',
            'taught_hours' => 'integer',
            'credit_factor' => 'decimal:2',
            'core' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The course this unit is delivered under.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * The curriculum this unit is delivered under.
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
