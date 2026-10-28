<?php

namespace App\Models;

use App\Enums\InvoiceEntryKind;
use App\Enums\InvoiceType;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One immutable ledger entry billing a student for an academic session:
 * a unique invoice number, the billed student and session, the charge
 * type, and the amount.
 *
 * An invoice is never edited and never deleted. Corrections are made by
 * inserting a linked reversal row — a matching negative amount, a
 * mandatory reason, and a unique pointer back at the original — so the
 * full history of what was billed, and what was undone, stays on
 * record. Paid / outstanding / balance are never stored here: they are
 * derived from the whole ledger by the StudentLedgerService.
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
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
        'invoice_number',
        'student_id',
        'academic_session_id',
        'invoice_type',
        'fee_structure_id',
        'description',
        'amount',
        'entry_kind',
        'reverses_invoice_id',
        'reversal_reason',
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
            'invoice_type' => InvoiceType::class,
            'entry_kind' => InvoiceEntryKind::class,
            'amount' => 'decimal:2',
        ];
    }

    /**
     * The student this invoice bills.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    /**
     * The academic session the invoice belongs to, stamped by the
     * backend when the entry was raised.
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * The fee structure a fee invoice was raised from, when it was one.
     */
    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }

    /**
     * The reversal entry that cancels this invoice, when one exists.
     */
    public function reversedBy(): HasMany
    {
        return $this->hasMany(self::class, 'reverses_invoice_id');
    }

    /**
     * The original invoice this entry reverses, when it is a reversal.
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_invoice_id');
    }

    /**
     * Whether this entry is a reversal row rather than a charge.
     */
    public function isReversal(): bool
    {
        return $this->entry_kind === InvoiceEntryKind::Reversal;
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
