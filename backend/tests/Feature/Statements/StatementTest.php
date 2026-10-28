<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;

test('the statement is read-only behind its own permission', function () {
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    $finance = financeAccessTokenFor(User::factory()->finance()->create());

    $this->withToken($finance)
        ->getJson('/api/students/'.$student->id.'/statement')
        ->assertOk()
        ->assertJsonPath('student.id', $student->id);

    // A statements-only reader picks a student through the shared
    // finance picker too, without holding any payments verb.
    $reader = User::factory()->create();
    $reader->syncRoles([]);
    $reader->givePermissionTo('statements.view');

    $this->withToken(financeAccessTokenFor($reader))
        ->getJson('/api/payments/students')
        ->assertOk()
        ->assertJsonPath('students.0.id', $student->id);

    $trainer = financeAccessTokenFor(User::factory()->trainer()->create());

    $this->withToken($trainer)
        ->getJson('/api/students/'.$student->id.'/statement')
        ->assertForbidden();

    $studentUser = financeAccessTokenFor(User::factory()->student()->create());

    $this->withToken($studentUser)
        ->getJson('/api/students/'.$student->id.'/statement')
        ->assertForbidden();
});

test('statement entries run chronologically with a running balance that reconciles', function () {
    $token = financeAccessTokenFor(User::factory()->finance()->create());
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    // Two invoices, a partial payment, an invoice reversal and a
    // payment reversal: enough traffic to prove the arithmetic.
    $first = Invoice::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 1000,
        'created_at' => now()->subDays(3),
    ]);
    $second = Invoice::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'invoice_type' => 'HOSTEL',
        'description' => 'Block A bed',
        'amount' => 2500,
        'created_at' => now()->subDays(2),
    ]);
    Payment::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 1500,
        'payment_date' => now()->subDay()->toDateString(),
    ]);
    Invoice::factory()->reversed($first)->create(['created_at' => now()]);

    $response = $this->withToken($token)
        ->getJson('/api/students/'.$student->id.'/statement')
        ->assertOk()
        ->assertJsonPath('total_invoiced', 2500)
        ->assertJsonPath('net_payments', 1500)
        ->assertJsonPath('balance', 1000)
        ->assertJsonPath('closing_balance', 1000)
        ->assertJsonPath('arrears', 1000)
        ->assertJsonPath('credit', 0);

    $entries = $response->json('entries');

    // Per-invoice figures ride along from the same derivation: only the
    // active second invoice is figured (partially settled by the 1,500
    // payment), while the reversed first one settles to zero.
    expect($response->json('invoices'))->toHaveCount(1)
        ->and($response->json('invoices.0.id'))->toBe($second->id)
        ->and($response->json('invoices.0.status'))->toBe('partially_paid')
        ->and($response->json('invoices.0.paid'))->toEqual(1500.0)
        ->and($response->json('invoices.0.outstanding'))->toEqual(1000.0)
        ->and($response->json('reversed_invoices'))->toHaveCount(1)
        ->and($response->json('reversed_invoices.0.id'))->toBe($first->id)
        ->and($response->json('reversed_invoices.0.status'))->toBe('reversed');

    // Chronological: first invoice, second invoice, payment, reversal.
    // JSON whole numbers decode as ints, so compare loosely.
    expect($entries[0]['document_number'])->toBe($first->invoice_number)
        ->and($entries[0]['type'])->toBe('invoice')
        ->and($entries[0]['debit'])->toEqual(1000.0)
        ->and($entries[1]['type'])->toBe('invoice')
        ->and($entries[2]['type'])->toBe('payment')
        ->and($entries[2]['credit'])->toEqual(1500.0)
        ->and($entries[3]['type'])->toBe('invoice_reversal')
        ->and($entries[3]['credit'])->toEqual(1000.0);

    // Every running balance is the previous one plus this row, and
    // the closing balance equals the derived ledger balance exactly.
    $running = 0.0;

    foreach ($entries as $entry) {
        $running = round($running + $entry['debit'] - $entry['credit'], 2);

        expect($entry['running_balance'])->toEqual($running);
    }

    expect($running)->toEqual($response->json('balance'))
        ->and($running)->toEqual($response->json('closing_balance'));
});

test('a refund appears on the statement and raises the balance again', function () {
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

    $this->withToken($admin)
        ->postJson('/api/refunds', [
            'student_id' => $student->id,
            'payment_method' => 'BANK',
            'amount' => 500,
            'reason' => 'Excess returned to the student',
            'idempotency_key' => 'statement-refund-1',
        ])
        ->assertCreated();

    $response = $this->withToken($admin)
        ->getJson('/api/students/'.$student->id.'/statement')
        ->assertOk();

    $refundEntry = collect($response->json('entries'))->firstWhere('type', 'refund');

    expect($refundEntry['debit'])->toEqual(500.0)
        ->and($refundEntry['document_number'])->toBe(Refund::query()->sole()->refund_number)
        ->and($response->json('closing_balance'))->toEqual(0.0)
        ->and($response->json('balance'))->toEqual(0.0);
});
