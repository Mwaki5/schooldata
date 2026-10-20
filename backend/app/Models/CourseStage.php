<?php

namespace App\Models;

use Database\Factories\CourseStageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One stage in a course's progression ladder (Y1S1, Y1S2, Y1S3, Y2S1…).
 *
 * A stage belongs to the course, not to a curriculum: it is the calendar
 * position a registration targets, while the curriculum decides which
 * units are taught at that position.
 */
class CourseStage extends Model
{
    /** @use HasFactory<CourseStageFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'course_id',
        'module_no',
        'year_no',
        'session_no',
        'code',
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
     * The course this stage belongs to.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Every session-offering row recorded for this stage.
     */
    public function offerings(): HasMany
    {
        return $this->hasMany(SessionStageOffering::class);
    }
}
