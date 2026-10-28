<?php

use App\Models\AcademicSession;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('the cash desk endpoints require finance permissions', function () {
    $trainer = financeAccessTokenFor(User::factory()->trainer()->create());

    $this->withToken($trainer)->postJson('/api/payments', [])->assertForbidden();
    $this->withToken($trainer)->getJson('/api/payments/students')->assertForbidden();

    $finance = financeAccessTokenFor(User::factory()->finance()->create());

    $this->withToken($finance)->postJson('/api/payments', [])->assertUnprocessable();
    $this->withToken($finance)->postJson('/api/refunds', [])->assertForbidden();

    // The reversal desk is its own permission: the finance bundle
    // holds it, but a receive-only cashier does not.
    $cashier = User::factory()->create();
    $cashier->syncRoles([]);
    $cashier->givePermissionTo('payments.receive');

    $cashierToken = financeAccessTokenFor($cashier);

    $this->withToken($cashierToken)->postJson('/api/payments', [])->assertUnprocessable();

    $payment = Payment::factory()->create();

    $this->withToken($cashierToken)
        ->postJson('/api/payments/'.$payment->id.'/reverse', [])
        ->assertForbidden();

    $this->withToken($cashierToken)->getJson('/api/payments/students')->assertOk();
});

test('a payment is recorded once with a sequential receipt and the server-stamped session', function () {
    $finance = User::factory()->finance()->create();
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    $otherSession = AcademicSession::factory()->create(['is_active' => false]);

    $this->withToken(financeAccessTokenFor($finance))
        ->postJson('/api/payments', [
            'student_id' => $student->id,
            'payment_method' => 'MOBILE_MONEY',
            'reference' => 'MP261028.1430.A12345',
            'amount' => 2000,
            'notes' => 'Paid at the counter',
            // A client-picked session is never read.
            'academic_session_id' => $otherSession->id,
            'idempotency_key' => 'payment-key-0001',
        ])
        ->assertCreated()
        ->assertJsonPath('payment.receipt_number', 'RCP-'.now()->format('Y').'-0001')
        ->assertJsonPath('payment.payment_method', 'MOBILE_MONEY')
        ->assertJsonPath('payment.payment_method_label', 'Mobile Money')
        ->assertJsonPath('payment.reference', 'MP261028.1430.A12345')
        ->assertJsonPath('payment.payment_date', now()->toDateString())
        ->assertJsonPath('payment.amount', 2000)
        ->assertJsonPath('payment.entry_kind', 'PAYMENT')
        ->assertJsonPath('payment.academic_session_id', $session->id)
        ->assertJsonPath('payment.created_by', $finance->id)
        // No invoices yet: the payment lands as credit held for the
        // student, and the balance block says exactly that.
        ->assertJsonPath('balance.balance', -2000)
        ->assertJsonPath('balance.arrears', 0)
        ->assertJsonPath('balance.credit', 2000);

    // The receipt number advances.
    $this->withToken(financeAccessTokenFor($finance))
        ->postJson('/api/payments', [
            'student_id' => $student->id,
            'payment_method' => 'CASH',
            'amount' => 500,
            'idempotency_key' => 'payment-key-0002',
        ])
        ->assertCreated()
        ->assertJsonPath('payment.receipt_number', 'RCP-'.now()->format('Y').'-0002');
});

test('a payment settles invoices oldest first and the balance endpoint agrees', function () {
    $token = financeAccessTokenFor(User::factory()->finance()->create());
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    $older = Invoice::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 1000,
        'created_at' => now()->subDay(),
    ]);
    $newer = Invoice::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 2500,
        'created_at' => now(),
    ]);

    $this->withToken($token)
        ->postJson('/api/payments', [
            'student_id' => $student->id,
            'payment_method' => 'CASH',
            'amount' => 2000,
            'idempotency_key' => 'payment-key-0010',
        ])
        ->assertCreated();

    $this->withToken($token)
        ->getJson('/api/students/'.$student->id.'/balance')
        ->assertOk()
        ->assertJsonPath('total_invoiced', 3500)
        ->assertJsonPath('net_payments', 2000)
        ->assertJsonPath('balance', 1500)
        ->assertJsonCount(1, 'outstanding_invoices')
        ->assertJsonPath('outstanding_invoices.0.id', $newer->id)
        ->assertJsonPath('outstanding_invoices.0.paid', 1000)
        ->assertJsonPath('outstanding_invoices.0.outstanding', 1500)
        ->assertJsonPath('outstanding_invoices.0.status', 'partially_paid');

    // The preview of a larger payment shows exactly how it would
    // apply, and persists nothing.
    $this->withToken($token)
        ->getJson('/api/students/'.$student->id.'/balance?preview_amount=2000')
        ->assertOk()
        ->assertJsonPath('preview.amount', 2000)
        ->assertJsonCount(1, 'preview.applied')
        ->assertJsonPath('preview.applied.0.invoice_number', $newer->invoice_number)
        ->assertJsonPath('preview.applied.0.amount', 1500)
        // The preview stacks onto the payments already recorded: 2000
        // more against 3500 owed leaves 500 held as credit.
        ->assertJsonPath('preview.credit_after', 500)
        ->assertJsonPath('balance', 1500);

    // An over-payment preview shows the excess as the credit it would
    // become.
    $this->withToken($token)
        ->getJson('/api/students/'.$student->id.'/balance?preview_amount=5000')
        ->assertOk()
        ->assertJsonPath('preview.applied.0.amount', 1500)
        ->assertJsonPath('preview.credit_after', 3500);

    expect(Payment::query()->count())->toBe(1)
        ->and($older->refresh()->id)->toBe($older->id);
});

test('a payment can be reversed exactly once and its invoices re-open', function () {
    $token = financeAccessTokenFor(User::factory()->finance()->create());
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    $invoice = Invoice::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 3000,
    ]);

    $payment = Payment::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 3000,
    ]);

    // Fully settled, then the bank bounces the cheque.
    $this->withToken($token)
        ->getJson('/api/students/'.$student->id.'/balance')
        ->assertOk()
        ->assertJsonPath('balance', 0);

    $this->withToken($token)
        ->getJson('/api/payments/'.$payment->id.'/reversal-preview')
        ->assertOk()
        ->assertJsonPath('balance_before', 0)
        ->assertJsonPath('balance_after', 3000)
        ->assertJsonPath('reopened_invoices.0', $invoice->invoice_number);

    $this->withToken($token)
        ->postJson('/api/payments/'.$payment->id.'/reverse', [
            'reason' => 'Cheque bounced',
            'idempotency_key' => 'payment-rev-0001',
        ])
        ->assertCreated()
        ->assertJsonPath('payment.amount', -3000)
        ->assertJsonPath('payment.entry_kind', 'PAYMENT_REVERSAL')
        ->assertJsonPath('payment.reverses_payment_id', $payment->id)
        ->assertJsonPath('payment.reversal_reason', 'Cheque bounced')
        ->assertJsonPath('payment.reference', 'REV: '.($payment->reference ?? $payment->receipt_number))
        ->assertJsonPath('balance.balance', 3000);

    // The original payment row is untouched; the invoice re-derives
    // as unpaid again with nothing un-applied anywhere.
    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'amount' => 3000,
        'entry_kind' => 'PAYMENT',
    ]);

    $this->withToken($token)
        ->getJson('/api/invoices?search='.$invoice->invoice_number)
        ->assertOk()
        ->assertJsonPath('invoices.0.status', 'unpaid')
        ->assertJsonPath('invoices.0.paid', 0)
        ->assertJsonPath('invoices.0.outstanding', 3000);

    // A second reversal is refused through the API…
    $this->withToken($token)
        ->postJson('/api/payments/'.$payment->id.'/reverse', [
            'reason' => 'Second thoughts',
            'idempotency_key' => 'payment-rev-0002',
        ])
        ->assertStatus(409);

    // …and a reversal row cannot be reversed at all.
    $reversal = Payment::query()->where('reverses_payment_id', $payment->id)->firstOrFail();

    $this->withToken($token)
        ->postJson('/api/payments/'.$reversal->id.'/reverse', [
            'reason' => 'Reversing the reversal',
            'idempotency_key' => 'payment-rev-0003',
        ])
        ->assertStatus(409);

    // Under concurrency the API's lock step is backed by the unique
    // constraint itself: a second reversal row cannot exist, even
    // written straight into the database.
    $this->expectException(PDOException::class);

    DB::transaction(fn () => Payment::factory()->reversed($payment)->create());
});

test('reversing a payment requires a reason', function () {
    $token = financeAccessTokenFor(User::factory()->finance()->create());
    $payment = Payment::factory()->create();

    $this->withToken($token)
        ->postJson('/api/payments/'.$payment->id.'/reverse', ['idempotency_key' => 'payment-rev-0010'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

test('a refunded payment cannot be reversed into a negative position', function () {
    $admin = financeAccessTokenFor(User::factory()->admin()->create());
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    // Overpay into credit, then pay the credit back out.
    Invoice::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 500,
    ]);

    $payment = Payment::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 1000,
    ]);

    $this->withToken($admin)
        ->postJson('/api/refunds', [
            'student_id' => $student->id,
            'payment_method' => 'BANK',
            'amount' => 500,
            'reason' => 'Student withdrew, credit paid back',
            'idempotency_key' => 'refund-key-0001',
        ])
        ->assertCreated()
        ->assertJsonPath('refund.refund_number', 'RFD-'.now()->format('Y').'-0001')
        ->assertJsonPath('balance.balance', 0)
        ->assertJsonPath('balance.credit', 0);

    // Reversing the payment now would leave net payments negative —
    // the refund would sit on money that was never received.
    $this->withToken($admin)
        ->postJson('/api/payments/'.$payment->id.'/reverse', [
            'reason' => 'Entered against the wrong student',
            'idempotency_key' => 'payment-rev-0020',
        ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'This payment has been refunded; reversing it would make net payments negative. Reverse the refund first, or contact an administrator.');

    expect(Refund::query()->count())->toBe(1)
        ->and(Payment::query()->where('entry_kind', 'PAYMENT_REVERSAL')->count())->toBe(0);
});

test('a duplicate reference within a method is refused', function () {
    $token = financeAccessTokenFor(User::factory()->finance()->create());
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    $payload = [
        'student_id' => $student->id,
        'payment_method' => 'BANK',
        'reference' => 'SLIP-7788',
        'amount' => 1000,
        'idempotency_key' => 'payment-key-0020',
    ];

    $this->withToken($token)->postJson('/api/payments', $payload)->assertCreated();

    $this->withToken($token)
        ->postJson('/api/payments', [...$payload, 'idempotency_key' => 'payment-key-0021'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reference']);

    // The same slip under a different method is a different paper
    // trail, and cash needs no slip at all.
    $this->withToken($token)
        ->postJson('/api/payments', [...$payload, 'payment_method' => 'MOBILE_MONEY', 'idempotency_key' => 'payment-key-0022'])
        ->assertCreated();

    $this->withToken($token)
        ->postJson('/api/payments', [
            'student_id' => $student->id,
            'payment_method' => 'CASH',
            'amount' => 100,
            'idempotency_key' => 'payment-key-0023',
        ])
        ->assertCreated();

    $this->withToken($token)
        ->postJson('/api/payments', [
            'student_id' => $student->id,
            'payment_method' => 'CASH',
            'amount' => 100,
            'idempotency_key' => 'payment-key-0024',
        ])
        ->assertCreated();
});

test('backdating a payment requires its own permission and is stamped when granted', function () {
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    $finance = financeAccessTokenFor(User::factory()->finance()->create());

    // The finance bundle holds no backdate: the date is refused, not
    // silently ignored.
    $this->withToken($finance)
        ->postJson('/api/payments', [
            'student_id' => $student->id,
            'payment_method' => 'CASH',
            'amount' => 100,
            'date' => now()->subDays(3)->toDateString(),
            'idempotency_key' => 'backdate-key-0001',
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Backdating a payment requires the payments.backdate permission.');

    // A holder of the permission dates it back, and a future date is
    // refused outright.
    $admin = User::factory()->admin()->create();
    $adminToken = financeAccessTokenFor($admin);

    $this->withToken($adminToken)
        ->postJson('/api/payments', [
            'student_id' => $student->id,
            'payment_method' => 'CASH',
            'amount' => 100,
            'date' => now()->subDays(3)->toDateString(),
            'idempotency_key' => 'backdate-key-0002',
        ])
        ->assertCreated()
        ->assertJsonPath('payment.payment_date', now()->subDays(3)->toDateString());

    $this->withToken($adminToken)
        ->postJson('/api/payments', [
            'student_id' => $student->id,
            'payment_method' => 'CASH',
            'amount' => 100,
            'date' => now()->addDay()->toDateString(),
            'idempotency_key' => 'backdate-key-0003',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);

    // The backdated payment is on the activity log with its acting
    // user — the forensic record of the exception.
    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'default',
        'causer_id' => $admin->id,
        'subject_type' => Payment::class,
    ]);
});

test('payment amounts must be positive with at most two decimals', function () {
    $token = financeAccessTokenFor(User::factory()->finance()->create());
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    $base = [
        'student_id' => $student->id,
        'payment_method' => 'CASH',
        'idempotency_key' => 'payment-key-0030',
    ];

    $this->withToken($token)
        ->postJson('/api/payments', [...$base, 'amount' => 10.999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);

    $this->withToken($token)
        ->postJson('/api/payments', [...$base, 'amount' => -5])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);

    $this->withToken($token)
        ->postJson('/api/payments', [...$base, 'amount' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

test('retrying the same idempotency key records the payment once', function () {
    $token = financeAccessTokenFor(User::factory()->finance()->create());
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    $payload = [
        'student_id' => $student->id,
        'payment_method' => 'CASH',
        'amount' => 750,
        'idempotency_key' => 'payment-key-0040',
    ];

    $first = $this->withToken($token)->postJson('/api/payments', $payload)->assertCreated();
    $second = $this->withToken($token)->postJson('/api/payments', $payload)->assertCreated();

    expect($first->json('payment.id'))->toBe($second->json('payment.id'))
        ->and(Payment::query()->count())->toBe(1);

    // Money is never recorded twice — the balance reads one payment.
    $this->withToken($token)
        ->getJson('/api/students/'.$student->id.'/balance')
        ->assertOk()
        ->assertJsonPath('net_payments', 750);
});

test('the balance read is its own permission and answers for either holder', function () {
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    $finance = financeAccessTokenFor(User::factory()->finance()->create());

    $this->withToken($finance)->getJson('/api/students/'.$student->id.'/balance')->assertOk();

    $statementReader = User::factory()->create();
    $statementReader->syncRoles([]);
    $statementReader->givePermissionTo('statements.view');

    $this->withToken(financeAccessTokenFor($statementReader))
        ->getJson('/api/students/'.$student->id.'/balance')
        ->assertOk();

    $outsider = User::factory()->trainer()->create();

    $this->withToken(financeAccessTokenFor($outsider))
        ->getJson('/api/students/'.$student->id.'/balance')
        ->assertForbidden();
});

test('a student’s payments read with reversal links behind their own permission', function () {
    $token = financeAccessTokenFor(User::factory()->finance()->create());
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    $payment = Payment::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 1500,
    ]);

    $reversal = Payment::factory()->reversed($payment)->create([
        'payment_date' => now()->addDay()->toDateString(),
    ]);

    // A different student's money never leaks into this ledger.
    Payment::factory()->create(['amount' => 999]);

    $this->withToken($token)
        ->getJson('/api/students/'.$student->id.'/payments')
        ->assertOk()
        ->assertJsonCount(2, 'payments')
        // Newest first; the reversal row names the original it cancels,
        // and the original carries the reversal that cancels it.
        ->assertJsonPath('payments.0.id', $reversal->id)
        ->assertJsonPath('payments.0.entry_kind', 'PAYMENT_REVERSAL')
        ->assertJsonPath('payments.0.reverses.id', $payment->id)
        ->assertJsonPath('payments.1.id', $payment->id)
        ->assertJsonPath('payments.1.entry_kind', 'PAYMENT')
        ->assertJsonPath('payments.1.reversed_by.id', $reversal->id)
        ->assertJsonPath('payments.1.reversed_by.receipt_number', $reversal->receipt_number);

    // The read rides either the receiver or the reverser — both desk
    // roles work the reverse screen.
    $cashier = User::factory()->create();
    $cashier->syncRoles([]);
    $cashier->givePermissionTo('payments.receive');

    $this->withToken(financeAccessTokenFor($cashier))
        ->getJson('/api/students/'.$student->id.'/payments')
        ->assertOk();

    $reverser = User::factory()->create();
    $reverser->syncRoles([]);
    $reverser->givePermissionTo('payments.reverse');

    $this->withToken(financeAccessTokenFor($reverser))
        ->getJson('/api/students/'.$student->id.'/payments')
        ->assertOk();

    $outsider = User::factory()->trainer()->create();

    $this->withToken(financeAccessTokenFor($outsider))
        ->getJson('/api/students/'.$student->id.'/payments')
        ->assertForbidden();

    $this->withToken($token)->getJson('/api/students/999999/payments')->assertNotFound();
});
