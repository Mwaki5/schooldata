<?php

namespace App\Models;

use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Staff extends Model
{
    /** @use HasFactory<StaffFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'employment_no',
        'name',
        'work_email',
        'work_phone',
        'national_id',
        'kra_pin',
        'nhif_number',
        'nssf_number',
        'position',
        'department_id',
        'employment_type',
        'employment_status',
        'role',
        'date_joined',
        'confirmation_date',
        'contract_end_date',
        'termination_date',
        'termination_reason',
        'basic_salary',
        'highest_qualification',
        'specialization',
        'created_by',
        'updated_by',
    ];

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'employment_status' => 'active',
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_joined' => 'date',
            'confirmation_date' => 'date',
            'contract_end_date' => 'date',
            'termination_date' => 'date',
            'basic_salary' => 'decimal:2',
        ];
    }

    /**
     * The department the staff member belongs to, if assigned.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The provisioned auth account for the staff member.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
