<?php

namespace App\Models;

use App\Enums\PaymentEntryKind;
use App\Enums\PaymentMethod;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One immutable ledger entry recording money received from a student:
 * a unique receipt number, the student and session, the method and
 * optional transaction reference, and the amount.
 *
 * A payment is never edited and never deleted. A bounced cheque, a
 * wrong student or a duplicate entry is corrected by inserting a linked
 * reversal row — a matching negative amount, a mandatory reason, and a
 * unique pointer back at the original. How the payment settles the
 * student's invoices is never stored here: it is derived from the whole
 * ledger by the StudentLedgerService, oldest invoice first.
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
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
        'receipt_number',
        'student_id',
        'academic_session_id',
        'payment_method',
        'reference',
        'payment_date',
        'amount',
        'entry_kind',
        'reverses_payment_id',
        'reversal_reason',
        'notes',
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
            'entry_kind' => PaymentEntryKind::class,
            'payment_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * The student the money was received from.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    /**
     * The academic session the payment belongs to, stamped by the
     * backend when the entry was received.
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * The reversal entry that cancels this payment, when one exists.
     */
    public function reversedBy(): HasMany
    {
        return $this->hasMany(self::class, 'reverses_payment_id');
    }

    /**
     * The original payment this entry reverses, when it is a reversal.
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_payment_id');
    }

    /**
     * Whether this entry is a reversal row rather than money in.
     */
    public function isReversal(): bool
    {
        return $this->entry_kind === PaymentEntryKind::PaymentReversal;
    }

    /**
     * Get the activity log configuration for the model. Ledger rows
     * are write-once, so this only ever fires on creation — including
     * a backdated payment_date, which the log records with it.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->dontSubmitEmptyLogs();
    }
}
