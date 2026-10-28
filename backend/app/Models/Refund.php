<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\RefundEntryKind;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One immutable ledger entry recording money held for a student going
 * back out: a unique refund number, the student and session, the
 * method and optional reference, a mandatory reason, and an amount.
 *
 * A refund is never edited and never deleted. A refund paid in error —
 * the wrong student, the wrong amount, or twice — is corrected by
 * inserting a linked reversal row: a matching negative amount, a
 * mandatory reason, and a unique pointer back at the original. Every
 * coin that leaves the ledger therefore has a traceable path back in.
 */
class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory, LogsActivity;

    /**
     * Ledger rows are immutable, so nothing is ever updated and the
     * table carries no updated_at column.
     */
    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'refund_number',
        'student_id',
        'academic_session_id',
        'payment_method',
        'reference',
        'amount',
        'entry_kind',
        'reverses_refund_id',
        'reason',
        'idempotency_key',
        'created_by',
    ];

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'entry_kind' => RefundEntryKind::class,
            'amount' => 'decimal:2',
        ];
    }

    /**
     * The student the credit is paid back to.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    /**
     * The academic session the refund belongs to, stamped by the
     * backend when the entry was made.
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * The reversal entry that cancels this refund, when one exists.
     */
    public function reversedBy(): HasMany
    {
        return $this->hasMany(self::class, 'reverses_refund_id');
    }

    /**
     * The original refund this entry reverses, when it is a reversal.
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_refund_id');
    }

    /**
     * Whether this entry is a reversal row rather than money out.
     */
    public function isReversal(): bool
    {
        return $this->entry_kind === RefundEntryKind::RefundReversal;
    }

    /**
     * Get the activity log configuration for the model. Ledger rows
     * are write-once, so this only ever fires on creation.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->dontSubmitEmptyLogs();
    }
}
