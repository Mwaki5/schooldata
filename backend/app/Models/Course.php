<?php

namespace App\Models;

use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'initials',
        'name',
        'duration_months',
        'description',
        'is_active',
        'certification_level_id',
        'department_id',
        'created_by',
        'updated_by',
    ];

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
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
            'duration_months' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The certification level this course leads to, if any.
     */
    public function certificationLevel(): BelongsTo
    {
        return $this->belongsTo(CertificationLevel::class);
    }

    /**
     * The department that offers this course, if any.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The unit assignments delivered under this course, one per
     * curriculum. The master units themselves live on the units table.
     */
    public function assignments(): HasMany
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
