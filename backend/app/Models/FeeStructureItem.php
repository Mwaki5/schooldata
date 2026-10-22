<?php

namespace App\Models;

use Database\Factories\FeeStructureItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One named charge inside a fee structure (e.g. Tuition, Examination
 * Fees). The live items' amounts sum into the structure's total, so a
 * repriced or removed item changes what the structure charges.
 */
class FeeStructureItem extends Model
{
    /** @use HasFactory<FeeStructureItemFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'fee_structure_id',
        'name',
        'amount',
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
            'amount' => 'decimal:2',
        ];
    }

    /**
     * The fee structure this charge belongs to.
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
