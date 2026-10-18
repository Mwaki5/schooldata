<?php

namespace App\Models;

use Database\Factories\ExamBodyGradeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ExamBodyGrade extends Model
{
    /** @use HasFactory<ExamBodyGradeFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'exam_body_id',
        'grade',
        'grade_start',
        'grade_end',
        'remark',
        'is_active',
        'sequence',
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
            'grade_start' => 'decimal:2',
            'grade_end' => 'decimal:2',
            'sequence' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The exam body whose grade scale this band belongs to.
     */
    public function examBody(): BelongsTo
    {
        return $this->belongsTo(ExamBody::class);
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
