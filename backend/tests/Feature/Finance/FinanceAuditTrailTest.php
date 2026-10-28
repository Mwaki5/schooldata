<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Carbon\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * "Every coin in and out." The immutable ledger rows are the primary
 * record, but the activity log is the who-did-what-when layer on top.
 * These tests prove that each way money moves — a charge, a payment
 * in, a refund out, and every reversal that corrects one — leaves an
 * audit row naming the acting user and carrying the document number
 * and amount, so an auditor can reconstruct the full money trail
 * without ever reading the ledger tables directly.
 */
test('every finance write leaves an audit row naming the actor, document and amount', function () {
    $admin = User::factory()->admin()->create();
    $token = financeAccessTokenFor($admin);
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    // 1. Raise an other-invoice charge.
    $invoice = $this->withToken($token)
        ->postJson('/api/invoices/other', [
            'student_id' => $student->id,
            'invoice_type' => 'HOSTEL',
            'amount' => 2500,
            'description' => 'Hostel bed, term 1',
            'idempotency_key' => 'audit-inv-0010',
        ])
        ->assertCreated()
        ->json('invoice');

    // 2. Overpay into credit, then 3. reverse the payment.
    $payment = $this->withToken($token)
        ->postJson('/api/payments', [
            'student_id' => $student->id,
            'payment_method' => 'CASH',
            'amount' => 4000,
            'idempotency_key' => 'audit-pay-0010',
        ])
        ->assertCreated()
        ->json('payment');

    $this->withToken($token)
        ->postJson('/api/payments/'.$payment['id'].'/reverse', [
            'reason' => 'Bounced cheque',
            'idempotency_key' => 'audit-payrev-0010',
        ])
        ->assertCreated();

    // 4. Re-pay, 5. refund the credit out, 6. reverse the refund.
    $paymentTwo = $this->withToken($token)
        ->postJson('/api/payments', [
            'student_id' => $student->id,
            'payment_method' => 'BANK',
            'reference' => 'SLIP-9001',
            'amount' => 4000,
            'idempotency_key' => 'audit-pay-0011',
        ])
        ->assertCreated()
        ->json('payment');

    $refund = $this->withToken($token)
        ->postJson('/api/refunds', [
            'student_id' => $student->id,
            'payment_method' => 'BANK',
            'amount' => 1500,
            'reason' => 'Excess paid in error',
            'idempotency_key' => 'audit-ref-0010',
        ])
        ->assertCreated()
        ->json('refund');

    $this->withToken($token)
        ->postJson('/api/refunds/'.$refund['id'].'/reverse', [
            'reason' => 'Refunded the wrong student',
            'idempotency_key' => 'audit-refrev-0010',
        ])
        ->assertCreated();

    // 7. Reverse the invoice, closing the loop on the charge.
    $this->withToken($token)
        ->postJson('/api/invoices/'.$invoice['id'].'/reverse', [
            'reason' => 'Charge raised in error',
            'idempotency_key' => 'audit-invrev-0010',
        ])
        ->assertCreated();

    // Each of the seven writes left an audit row caused by the admin,
    // carrying the document number and the amount that moved. The
    // attributes are logged under a nested `attributes` key.
    $trail = Activity::query()
        ->where('causer_id', $admin->id)
        ->whereIn('subject_type', [Invoice::class, Payment::class, Refund::class])
        ->get();

    expect($trail->pluck('subject_type')->unique()->sort()->values()->all())
        ->toEqual([Invoice::class, Payment::class, Refund::class]);

    // The invoice charge and its reversal.
    $invoiceRows = $trail->where('subject_type', Invoice::class);
    expect($invoiceRows->count())->toBe(2)
        ->and($invoiceRows->pluck('properties.attributes.invoice_number')->filter()->all())
        ->toContain($invoice['invoice_number']);

    // The payment, its reversal, and the second payment.
    $paymentRows = $trail->where('subject_type', Payment::class);
    expect($paymentRows->count())->toBe(3)
        ->and($paymentRows->pluck('properties.attributes.receipt_number')->filter()->all())
        ->toContain($payment['receipt_number'], $paymentTwo['receipt_number']);

    // The refund and its reversal.
    $refundRows = $trail->where('subject_type', Refund::class);
    expect($refundRows->count())->toBe(2)
        ->and($refundRows->pluck('properties.attributes.refund_number')->filter()->all())
        ->toContain($refund['refund_number']);

    // Every row is an unambiguous "created" (nothing is ever updated or
    // deleted) and every amount that moved is on the record — the refund
    // out at +1500 and its reversal bringing the same coin back at -1500.
    expect($trail->pluck('event')->unique()->all())->toBe(['created'])
        ->and($refundRows->pluck('properties.attributes.amount')
            ->map(fn ($a) => round((float) $a, 2))
            ->sort()
            ->values()
            ->all())
        ->toEqual([-1500.0, 1500.0]);
});

test('the audit trail records a backdated payment with its effective date', function () {
    $admin = User::factory()->admin()->create();
    $token = financeAccessTokenFor($admin);
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    $this->withToken($token)
        ->postJson('/api/payments', [
            'student_id' => $student->id,
            'payment_method' => 'CASH',
            'amount' => 500,
            'date' => now()->subDays(7)->toDateString(),
            'idempotency_key' => 'audit-back-0010',
        ])
        ->assertCreated();

    $activity = Activity::query()
        ->where('causer_id', $admin->id)
        ->where('subject_type', Payment::class)
        ->first();

    expect($activity)->not->toBeNull()
        // The effective (backdated) date rides the logged attributes, so
        // an auditor can compare it against when the row was written.
        ->and(Carbon::parse($activity->properties['attributes']['payment_date'])->toDateString())
        ->toBe(now()->subDays(7)->toDateString());
});
