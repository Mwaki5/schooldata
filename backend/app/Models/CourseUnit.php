<?php

namespace App\Models;

use App\Enums\UnitType;
use Database\Factories\CourseUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One unit delivered inside one course + curriculum pair: the teaching
 * load (module, allocated hours, credit factor) and the unit's role
 * (basic / core / common, active) belong to this delivery, not to the
 * unit — the same unit may be linked into several courses differently.
 */
class CourseUnit extends Model
{
    /** @use HasFactory<CourseUnitFactory> */
    use HasFactory, LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'unit_id',
        'course_id',
        'curriculum_id',
        'modules_taught',
        'taught_hours',
        'credit_factor',
        'unit_type',
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
        'unit_type' => 'core',
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
            'unit_type' => UnitType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * The linked catalogue unit.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * The course the unit is delivered under.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * The curriculum the unit is delivered under.
     */
    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    /**
     * The student registrations against this link: the reason it may not
     * be unlinked while students are committed to it.
     */
    public function unitRegistrations(): HasMany
    {
        return $this->hasMany(UnitRegistration::class);
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
