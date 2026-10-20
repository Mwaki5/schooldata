<?php

namespace App\Models;

use App\Enums\OfferingStatus;
use Database\Factories\SessionStageOfferingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An explicit decision to open (or withdraw) one course stage in one
 * academic session. No row at all means the stage is closed.
 */
class SessionStageOffering extends Model
{
    /** @use HasFactory<SessionStageOfferingFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'academic_session_id',
        'course_stage_id',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'closed',
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OfferingStatus::class,
        ];
    }

    /**
     * The session the stage is offered in.
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * The stage being offered.
     */
    public function courseStage(): BelongsTo
    {
        return $this->belongsTo(CourseStage::class);
    }

    /**
     * The user who first opened the row.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The user who last changed the row.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
