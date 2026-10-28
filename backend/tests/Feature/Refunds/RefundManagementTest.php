<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;

test('refunds are refused without their own restricted permission', function () {
    $finance = financeAccessTokenFor(User::factory()->finance()->create());

    // The finance bundle deliberately carries no refunds: paying
    // credit back out is the more restricted verb.
    $this->withToken($finance)->postJson('/api/refunds', [])->assertForbidden();

    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    $this->withToken($finance)
        ->postJson('/api/refunds', [
            'student_id' => $student->id,
            'payment_method' => 'BANK',
            'amount' => 100,
            'reason' => 'Overpayment returned',
            'idempotency_key' => 'refund-key-0010',
        ])
        ->assertForbidden();
});

test('a refund pays credit back out, never more than the credit held', function () {
    $admin = financeAccessTokenFor(User::factory()->admin()->create());
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    // No credit yet: nothing may be refunded.
    $this->withToken($admin)
        ->postJson('/api/refunds', [
            'student_id' => $student->id,
            'payment_method' => 'BANK',
            'amount' => 100,
            'reason' => 'Nothing to refund',
            'idempotency_key' => 'refund-key-0020',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);

    // Overpay into 500 of credit, then refund part of it.
    Invoice::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 500,
    ]);

    Payment::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 1000,
    ]);

    $this->withToken($admin)
        ->postJson('/api/refunds', [
            'student_id' => $student->id,
            'payment_method' => 'BANK',
            'reference' => 'RTGS-4411',
            'amount' => 300,
            'reason' => 'Excess paid in error',
            'idempotency_key' => 'refund-key-0021',
        ])
        ->assertCreated()
        ->assertJsonPath('refund.refund_number', 'RFD-'.now()->format('Y').'-0001')
        ->assertJsonPath('refund.amount', 300)
        ->assertJsonPath('refund.reason', 'Excess paid in error')
        ->assertJsonPath('balance.net_payments', 700)
        ->assertJsonPath('balance.credit', 200);

    // More than the remaining credit is refused, and the message says
    // how much is available.
    $this->withToken($admin)
        ->postJson('/api/refunds', [
            'student_id' => $student->id,
            'payment_method' => 'BANK',
            'amount' => 250,
            'reason' => 'Taking too much back',
            'idempotency_key' => 'refund-key-0022',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);

    expect(Refund::query()->count())->toBe(1);
});

test('retrying the same idempotency key records the refund once', function () {
    $admin = financeAccessTokenFor(User::factory()->admin()->create());
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    Invoice::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 500,
    ]);

    Payment::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 1000,
    ]);

    $payload = [
        'student_id' => $student->id,
        'payment_method' => 'CASH',
        'amount' => 500,
        'reason' => 'Credit returned to the student',
        'idempotency_key' => 'refund-key-0030',
    ];

    $first = $this->withToken($admin)->postJson('/api/refunds', $payload)->assertCreated();
    $second = $this->withToken($admin)->postJson('/api/refunds', $payload)->assertCreated();

    expect($first->json('refund.id'))->toBe($second->json('refund.id'))
        ->and(Refund::query()->count())->toBe(1)
        ->and((float) $second->json('balance.credit'))->toBe(0.0);
});

test('a refund requires a reason and a valid amount', function () {
    $admin = financeAccessTokenFor(User::factory()->admin()->create());
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    $this->withToken($admin)
        ->postJson('/api/refunds', [
            'student_id' => $student->id,
            'payment_method' => 'BANK',
            'amount' => 100,
            'idempotency_key' => 'refund-key-0040',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);

    $this->withToken($admin)
        ->postJson('/api/refunds', [
            'student_id' => $student->id,
            'payment_method' => 'CRYPTO',
            'amount' => 100,
            'reason' => 'Unknown method',
            'idempotency_key' => 'refund-key-0041',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['payment_method']);
});

test('reversing a refund restores the credit it had cancelled, leaving the original untouched', function () {
    $admin = financeAccessTokenFor(User::factory()->admin()->create());
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    Invoice::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 500,
    ]);

    Payment::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 1000,
    ]);

    $refund = $this->withToken($admin)
        ->postJson('/api/refunds', [
            'student_id' => $student->id,
            'payment_method' => 'BANK',
            'amount' => 300,
            'reason' => 'Excess paid in error',
            'idempotency_key' => 'refund-rev-0010',
        ])
        ->assertCreated()
        ->json('refund');

    // Credit drops to 200 once the refund pays 300 out.
    $this->withToken($admin)
        ->getJson('/api/students/'.$student->id.'/balance')
        ->assertOk()
        ->assertJsonPath('credit', 200);

    $reversal = $this->withToken($admin)
        ->postJson('/api/refunds/'.$refund['id'].'/reverse', [
            'reason' => 'Refunded the wrong student',
            'idempotency_key' => 'refund-rev-0011',
        ])
        ->assertCreated()
        ->assertJsonPath('refund.entry_kind', 'REFUND_REVERSAL')
        ->assertJsonPath('refund.reverses_refund_id', $refund['id'])
        ->assertJsonPath('refund.amount', -300)
        // The credit the refund had cancelled is back: 500 again.
        ->assertJsonPath('balance.credit', 500);

    // The original refund row is untouched — same amount, same kind.
    expect(Refund::query()->findOrFail($refund['id'])->amount)->toEqual('300.00')
        ->and(Refund::query()->findOrFail($refund['id'])->entry_kind->value)->toBe('REFUND')
        ->and(Refund::query()->count())->toBe(2);

    expect($reversal->json('refund.reverses'))->toBe($refund['refund_number']);
});

test('a refund reversal requires its own restricted permission', function () {
    $finance = financeAccessTokenFor(User::factory()->finance()->create());
    $admin = financeAccessTokenFor(User::factory()->admin()->create());
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    Invoice::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 500,
    ]);

    Payment::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 1000,
    ]);

    $refund = $this->withToken($admin)
        ->postJson('/api/refunds', [
            'student_id' => $student->id,
            'payment_method' => 'BANK',
            'amount' => 300,
            'reason' => 'Excess paid in error',
            'idempotency_key' => 'refund-perm-0010',
        ])
        ->assertCreated()
        ->json('refund');

    // The finance bundle carries no refunds verb, so it can neither
    // preview nor post a reversal.
    $this->withToken($finance)
        ->getJson('/api/refunds/'.$refund['id'].'/reversal-preview')
        ->assertForbidden();

    $this->withToken($finance)
        ->postJson('/api/refunds/'.$refund['id'].'/reverse', [
            'reason' => 'Not allowed to reverse',
            'idempotency_key' => 'refund-perm-0011',
        ])
        ->assertForbidden();
});

test('a refund cannot be reversed twice, and a reversal cannot itself be reversed', function () {
    $admin = financeAccessTokenFor(User::factory()->admin()->create());
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    Invoice::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 500,
    ]);

    Payment::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 1000,
    ]);

    $refund = $this->withToken($admin)
        ->postJson('/api/refunds', [
            'student_id' => $student->id,
            'payment_method' => 'BANK',
            'amount' => 300,
            'reason' => 'Excess paid in error',
            'idempotency_key' => 'refund-double-0010',
        ])
        ->assertCreated()
        ->json('refund');

    $reversal = $this->withToken($admin)
        ->postJson('/api/refunds/'.$refund['id'].'/reverse', [
            'reason' => 'Refunded the wrong student',
            'idempotency_key' => 'refund-double-0011',
        ])
        ->assertCreated()
        ->json('refund');

    // A second reversal of the same refund is a 409 through the
    // LedgerException handler — the unique pointer makes it impossible.
    $this->withToken($admin)
        ->postJson('/api/refunds/'.$refund['id'].'/reverse', [
            'reason' => 'Trying to reverse again',
            'idempotency_key' => 'refund-double-0012',
        ])
        ->assertStatus(409);

    // A reversal row cannot itself be reversed; the original is the
    // only correction target.
    $this->withToken($admin)
        ->postJson('/api/refunds/'.$reversal['id'].'/reverse', [
            'reason' => 'Reversing the reversal',
            'idempotency_key' => 'refund-double-0013',
        ])
        ->assertStatus(409);

    expect(Refund::query()->count())->toBe(2);
});

test('retrying the same idempotency key records the refund reversal once', function () {
    $admin = financeAccessTokenFor(User::factory()->admin()->create());
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    Invoice::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 500,
    ]);

    Payment::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 1000,
    ]);

    $refund = $this->withToken($admin)
        ->postJson('/api/refunds', [
            'student_id' => $student->id,
            'payment_method' => 'BANK',
            'amount' => 300,
            'reason' => 'Excess paid in error',
            'idempotency_key' => 'refund-idem-0010',
        ])
        ->assertCreated()
        ->json('refund');

    $payload = [
        'reason' => 'Refunded the wrong student',
        'idempotency_key' => 'refund-idem-0011',
    ];

    $first = $this->withToken($admin)
        ->postJson('/api/refunds/'.$refund['id'].'/reverse', $payload)
        ->assertCreated();

    $second = $this->withToken($admin)
        ->postJson('/api/refunds/'.$refund['id'].'/reverse', $payload)
        ->assertCreated();

    expect($first->json('refund.id'))->toBe($second->json('refund.id'))
        ->and(Refund::query()->where('entry_kind', 'REFUND_REVERSAL')->count())->toBe(1)
        ->and((float) $second->json('balance.credit'))->toBe(500.0);
});

test('reversing a refund unblocks reversing the payment it was leaning on', function () {
    $admin = financeAccessTokenFor(User::factory()->admin()->create());
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    $invoice = Invoice::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 500,
    ]);

    $payment = Payment::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 1000,
    ]);

    $refund = $this->withToken($admin)
        ->postJson('/api/refunds', [
            'student_id' => $student->id,
            'payment_method' => 'BANK',
            'amount' => 300,
            'reason' => 'Excess paid in error',
            'idempotency_key' => 'refund-unlock-0010',
        ])
        ->assertCreated()
        ->json('refund');

    // Reversing the payment now would push net payments negative — it
    // is blocked, and the message points at the refund.
    $this->withToken($admin)
        ->postJson('/api/payments/'.$payment->id.'/reverse', [
            'reason' => 'Bounced cheque',
            'idempotency_key' => 'refund-unlock-0011',
        ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'This payment has been refunded; reversing it would make net payments negative. Reverse the refund first, or contact an administrator.');

    // Reverse the refund first — the correction path the error names.
    $this->withToken($admin)
        ->postJson('/api/refunds/'.$refund['id'].'/reverse', [
            'reason' => 'Refunded the wrong student',
            'idempotency_key' => 'refund-unlock-0012',
        ])
        ->assertCreated();

    // Now the payment reversal goes through, and the invoice it had
    // over-settled re-opens with its full 500 outstanding.
    $this->withToken($admin)
        ->postJson('/api/payments/'.$payment->id.'/reverse', [
            'reason' => 'Bounced cheque',
            'idempotency_key' => 'refund-unlock-0013',
        ])
        ->assertCreated()
        ->assertJsonPath('balance.balance', 500);

    $this->withToken($admin)
        ->getJson('/api/students/'.$student->id.'/balance')
        ->assertOk()
        ->assertJsonPath('balance', 500)
        ->assertJsonPath('arrears', 500)
        ->assertJsonPath('credit', 0)
        ->assertJsonPath('outstanding_invoices.0.id', $invoice->id)
        ->assertJsonPath('outstanding_invoices.0.status', 'unpaid');

    // Every coin is now on the ledger and traceable: the charge, the
    // over-payment, the refund out, the refund back, and the payment
    // reversal — five rows, none edited, none deleted.
    expect(Payment::query()->where('student_id', $student->id)->count())->toBe(2)
        ->and(Refund::query()->where('student_id', $student->id)->count())->toBe(2)
        ->and(Invoice::query()->where('student_id', $student->id)->count())->toBe(1);
});

test('the reversal preview shows the credit the correction would restore', function () {
    $admin = financeAccessTokenFor(User::factory()->admin()->create());
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    Invoice::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 500,
    ]);

    Payment::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 1000,
    ]);

    $refund = $this->withToken($admin)
        ->postJson('/api/refunds', [
            'student_id' => $student->id,
            'payment_method' => 'BANK',
            'amount' => 300,
            'reason' => 'Excess paid in error',
            'idempotency_key' => 'refund-prev-0010',
        ])
        ->assertCreated()
        ->json('refund');

    $this->withToken($admin)
        ->getJson('/api/refunds/'.$refund['id'].'/reversal-preview')
        ->assertOk()
        ->assertJsonPath('refund_number', $refund['refund_number'])
        ->assertJsonPath('amount', 300)
        ->assertJsonPath('credit_before', 200)
        ->assertJsonPath('credit_after', 500)
        ->assertJsonPath('balance_before', -200)
        ->assertJsonPath('balance_after', -500);

    // Nothing hypothetical was persisted by the preview.
    expect(Refund::query()->count())->toBe(1);
});

test('a refund reversal requires a reason', function () {
    $admin = financeAccessTokenFor(User::factory()->admin()->create());
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    Invoice::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 500,
    ]);

    Payment::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 1000,
    ]);

    $refund = $this->withToken($admin)
        ->postJson('/api/refunds', [
            'student_id' => $student->id,
            'payment_method' => 'BANK',
            'amount' => 300,
            'reason' => 'Excess paid in error',
            'idempotency_key' => 'refund-reason-0010',
        ])
        ->assertCreated()
        ->json('refund');

    $this->withToken($admin)
        ->postJson('/api/refunds/'.$refund['id'].'/reverse', [
            'idempotency_key' => 'refund-reason-0011',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

test('a refunds holder can list a student’s refunds newest first, each flagging a reversal', function () {
    $admin = financeAccessTokenFor(User::factory()->admin()->create());
    $finance = financeAccessTokenFor(User::factory()->finance()->create());
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    Invoice::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 500,
    ]);

    Payment::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 1000,
    ]);

    $first = $this->withToken($admin)
        ->postJson('/api/refunds', [
            'student_id' => $student->id,
            'payment_method' => 'BANK',
            'amount' => 200,
            'reason' => 'Excess paid in error',
            'idempotency_key' => 'refund-list-0010',
        ])
        ->assertCreated()
        ->json('refund');

    $second = $this->withToken($admin)
        ->postJson('/api/refunds', [
            'student_id' => $student->id,
            'payment_method' => 'CASH',
            'amount' => 100,
            'reason' => 'Second excess',
            'idempotency_key' => 'refund-list-0011',
        ])
        ->assertCreated()
        ->json('refund');

    // Reverse the first one so the list must flag it as corrected.
    $reversal = $this->withToken($admin)
        ->postJson('/api/refunds/'.$first['id'].'/reverse', [
            'reason' => 'Refunded the wrong student',
            'idempotency_key' => 'refund-list-0012',
        ])
        ->assertCreated()
        ->json('refund');

    $response = $this->withToken($admin)
        ->getJson('/api/students/'.$student->id.'/refunds')
        ->assertOk();

    // Three rows, newest first: the reversal row, the second refund,
    // then the first refund it corrected.
    expect($response->json('refunds'))->toHaveCount(3)
        ->and($response->json('refunds.0.id'))->toBe($reversal['id'])
        ->and($response->json('refunds.0.entry_kind'))->toBe('REFUND_REVERSAL')
        ->and($response->json('refunds.0.reverses_refund_id'))->toBe($first['id'])
        ->and($response->json('refunds.1.id'))->toBe($second['id'])
        ->and($response->json('refunds.1.entry_kind'))->toBe('REFUND')
        ->and($response->json('refunds.1.reversed_by'))->toBeNull()
        ->and($response->json('refunds.2.id'))->toBe($first['id'])
        ->and($response->json('refunds.2.reversed_by'))->not->toBeNull();

    // The finance bundle carries no refunds verb, so it cannot list.
    $this->withToken($finance)
        ->getJson('/api/students/'.$student->id.'/refunds')
        ->assertForbidden();

    $this->withToken($admin)
        ->getJson('/api/students/'.($student->id + 999).'/refunds')
        ->assertNotFound();
});
