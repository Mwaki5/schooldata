<?php

use App\Models\AcademicSession;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\Invoice;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('the ledger endpoints require an access token', function () {
    $this->getJson('/api/invoices')->assertUnauthorized();
    $this->postJson('/api/invoices/fee', [])->assertUnauthorized();
    $this->postJson('/api/invoices/other', [])->assertUnauthorized();
    $this->postJson('/api/payments', [])->assertUnauthorized();
    $this->postJson('/api/refunds', [])->assertUnauthorized();
});

test('only finance holders may work the desks', function () {
    financeActiveSession();
    $trainer = financeAccessTokenFor(User::factory()->trainer()->create());

    $this->withToken($trainer)->getJson('/api/invoices')->assertForbidden();
    $this->withToken($trainer)->postJson('/api/invoices/fee', [])->assertForbidden();
    $this->withToken($trainer)->postJson('/api/invoices/other', [])->assertForbidden();
    $this->withToken($trainer)->postJson('/api/payments', [])->assertForbidden();

    $finance = financeAccessTokenFor(User::factory()->finance()->create());

    // The finance bundle carries the desk verbs: the list opens, and
    // store answers validation rather than the permission gate.
    $this->withToken($finance)->getJson('/api/invoices')->assertOk();
    $this->withToken($finance)->postJson('/api/invoices/fee', [])->assertUnprocessable();
    $this->withToken($finance)->postJson('/api/invoices/other', [])->assertUnprocessable();

    // A viewer holding only invoices.view reads but never writes, and
    // never reverses.
    $viewer = User::factory()->create();
    $viewer->syncRoles([]);
    $viewer->givePermissionTo('invoices.view');
    $viewerToken = financeAccessTokenFor($viewer);

    $this->withToken($viewerToken)->getJson('/api/invoices')->assertOk();
    $this->withToken($viewerToken)->postJson('/api/invoices/other', [])->assertForbidden();

    $invoice = Invoice::factory()->create();

    $this->withToken($viewerToken)
        ->postJson('/api/invoices/'.$invoice->id.'/reverse', [])
        ->assertForbidden();

    // The reversal desk is its own permission, and passing it finds
    // the invoice missing rather than refusing the user.
    $reverser = User::factory()->create();
    $reverser->syncRoles([]);
    $reverser->givePermissionTo('invoices.reverse');

    $this->withToken(financeAccessTokenFor($reverser))
        ->postJson('/api/invoices/'.Invoice::factory()->create()->id.'/reverse', [
            'reason' => 'ok',
            'idempotency_key' => 'abcdefgh',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

test('a fee invoice takes its amount from the structure and stamps the session server-side', function () {
    $finance = User::factory()->finance()->create();
    $session = financeActiveSession();
    [$student, $feeStructure] = financeBillableStudent($session, 45000);

    $otherSession = AcademicSession::factory()->create(['is_active' => false]);

    $response = $this->withToken(financeAccessTokenFor($finance))
        ->postJson('/api/invoices/fee', [
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'description' => 'Term 1 tuition',
            // Tampering attempts, all ignored: a client-sent amount
            // and a client-picked session are never read.
            'amount' => 1,
            'academic_session_id' => $otherSession->id,
            'idempotency_key' => 'fee-invoice-000001',
        ])
        ->assertCreated()
        ->assertJsonPath('invoice.invoice_number', 'INV-'.now()->format('Y').'-0001')
        ->assertJsonPath('invoice.invoice_type', 'FEE_INVOICE')
        ->assertJsonPath('invoice.fee_structure_id', $feeStructure->id)
        ->assertJsonPath('invoice.amount', 45000)
        ->assertJsonPath('invoice.status', 'unpaid')
        ->assertJsonPath('invoice.academic_session_id', $session->id)
        ->assertJsonPath('invoice.description', 'Term 1 tuition')
        ->assertJsonPath('invoice.entry_kind', 'INVOICE')
        ->assertJsonPath('invoice.created_by', $finance->id);

    $this->assertDatabaseHas('invoices', [
        'id' => $response->json('invoice.id'),
        'amount' => 45000,
        'academic_session_id' => $session->id,
        'entry_kind' => 'INVOICE',
    ]);
});

test('a fee invoice requires the structure to apply to the student', function () {
    $token = financeAccessTokenFor(User::factory()->finance()->create());
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    // A structure nobody assigned to the student's course cannot bill
    // them.
    $stranger = FeeStructure::factory()->create(['is_active' => true]);
    FeeStructureItem::factory()->for($stranger)->create(['amount' => 1000]);

    $this->withToken($token)
        ->postJson('/api/invoices/fee', [
            'student_id' => $student->id,
            'fee_structure_id' => $stranger->id,
            'idempotency_key' => 'fee-invoice-000002',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['fee_structure_id']);

    // An inactive structure stops being offered to new invoices.
    [, $feeStructure] = financeBillableStudent($session);
    $feeStructure->update(['is_active' => false]);

    $this->withToken($token)
        ->postJson('/api/invoices/fee', [
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'idempotency_key' => 'fee-invoice-000003',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['fee_structure_id']);
});

test('a second fee invoice for the same structure warns by default and blocks under config', function () {
    $token = financeAccessTokenFor(User::factory()->finance()->create());
    $session = financeActiveSession();
    [$student, $feeStructure] = financeBillableStudent($session);

    $payload = [
        'student_id' => $student->id,
        'fee_structure_id' => $feeStructure->id,
        'idempotency_key' => 'fee-invoice-000004',
    ];

    $this->withToken($token)->postJson('/api/invoices/fee', $payload)->assertCreated();

    // Warn mode: the duplicate is refused until the client confirms.
    $this->withToken($token)
        ->postJson('/api/invoices/fee', [...$payload, 'idempotency_key' => 'fee-invoice-000005'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['fee_structure_id']);

    $this->withToken($token)
        ->postJson('/api/invoices/fee', [
            ...$payload,
            'idempotency_key' => 'fee-invoice-000006',
            'confirm_duplicate' => true,
        ])
        ->assertCreated();

    // Block mode: even the confirmation cannot raise it.
    config(['finance.duplicate_fee_invoice' => 'block']);

    $this->withToken($token)
        ->postJson('/api/invoices/fee', [
            ...$payload,
            'idempotency_key' => 'fee-invoice-000007',
            'confirm_duplicate' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['fee_structure_id']);

    // Reversing the originals clears the duplicate state.
    config(['finance.duplicate_fee_invoice' => 'warn']);

    Invoice::query()
        ->where('student_id', $student->id)
        ->where('entry_kind', 'INVOICE')
        ->get()
        ->each(fn (Invoice $invoice) => $this->withToken($token)
            ->postJson('/api/invoices/'.$invoice->id.'/reverse', [
                'reason' => 'Raised in error',
                'idempotency_key' => 'reverse-'.$invoice->id,
            ])
            ->assertCreated());

    $this->withToken($token)
        ->postJson('/api/invoices/fee', [...$payload, 'idempotency_key' => 'fee-invoice-000008'])
        ->assertCreated();
});

test('an other invoice validates its type and amount', function () {
    $token = financeAccessTokenFor(User::factory()->finance()->create());
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    $base = [
        'student_id' => $student->id,
        'invoice_type' => 'HOSTEL',
        'description' => 'Hostel Block A, bed 12',
        'amount' => 12500.50,
        'idempotency_key' => 'other-invoice-00001',
    ];

    // The structured fee invoice is not offerable here.
    $this->withToken($token)
        ->postJson('/api/invoices/other', [...$base, 'invoice_type' => 'FEE_INVOICE'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['invoice_type']);

    // At most two decimals, never zero or negative.
    $this->withToken($token)
        ->postJson('/api/invoices/other', [...$base, 'amount' => 10.999, 'idempotency_key' => 'other-invoice-00002'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);

    $this->withToken($token)
        ->postJson('/api/invoices/other', [...$base, 'amount' => 0, 'idempotency_key' => 'other-invoice-00003'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);

    $this->withToken($token)
        ->postJson('/api/invoices/other', [...$base, 'description' => '', 'idempotency_key' => 'other-invoice-00004'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['description']);

    $this->withToken($token)
        ->postJson('/api/invoices/other', $base)
        ->assertCreated()
        ->assertJsonPath('invoice.invoice_type', 'HOSTEL')
        ->assertJsonPath('invoice.amount', 12500.5)
        ->assertJsonPath('invoice.academic_session_id', $session->id);
});

test('an invoice can be reversed exactly once, never edited and never deleted', function () {
    financeActiveSession();
    $token = financeAccessTokenFor(User::factory()->finance()->create());
    $invoice = Invoice::factory()->create(['amount' => 500]);

    $reversal = $this->withToken($token)
        ->postJson('/api/invoices/'.$invoice->id.'/reverse', [
            'reason' => 'Raised against the wrong student',
            'idempotency_key' => 'reverse-000001',
        ])
        ->assertCreated()
        ->assertJsonPath('invoice.invoice_type', $invoice->invoice_type->value)
        ->assertJsonPath('invoice.amount', -500)
        ->assertJsonPath('invoice.entry_kind', 'REVERSAL')
        ->assertJsonPath('invoice.reverses_invoice_id', $invoice->id)
        ->assertJsonPath('invoice.reversal_reason', 'Raised against the wrong student');

    // The original is untouched — same amount, same everything.
    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'amount' => 500,
        'entry_kind' => 'INVOICE',
    ]);

    // A second reversal is refused through the API…
    $this->withToken($token)
        ->postJson('/api/invoices/'.$invoice->id.'/reverse', [
            'reason' => 'Second thoughts',
            'idempotency_key' => 'reverse-000002',
        ])
        ->assertStatus(409);

    // …and a reversal row cannot be reversed at all.
    $this->withToken($token)
        ->postJson('/api/invoices/'.$reversal->json('invoice.id').'/reverse', [
            'reason' => 'Reversing the reversal',
            'idempotency_key' => 'reverse-000003',
        ])
        ->assertStatus(409);

    // Under concurrency the API's lock step is backed by the unique
    // constraint itself: a second reversal row cannot exist, even
    // written straight into the database.
    $this->expectException(PDOException::class);

    DB::transaction(fn () => Invoice::factory()->reversed($invoice)->create());
});

test('reversing a paid invoice turns the excess payment into student credit', function () {
    $token = financeAccessTokenFor(User::factory()->finance()->create());
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    $invoice = Invoice::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 45000,
        'created_at' => now()->subDay(),
    ]);

    $this->withToken($token)
        ->postJson('/api/payments', [
            'student_id' => $student->id,
            'payment_method' => 'BANK',
            'amount' => 45000,
            'idempotency_key' => 'payment-000001',
        ])
        ->assertCreated()
        ->assertJsonPath('balance.balance', 0);

    // The preview warns what the reversal will do before it happens.
    $this->withToken($token)
        ->getJson('/api/invoices/'.$invoice->id.'/reversal-preview')
        ->assertOk()
        ->assertJsonPath('balance_before', 0)
        ->assertJsonPath('balance_after', -45000)
        ->assertJsonPath('credit_after', 45000);

    $this->withToken($token)
        ->postJson('/api/invoices/'.$invoice->id.'/reverse', [
            'reason' => 'Invoice was raised in error',
            'idempotency_key' => 'reverse-000010',
        ])
        ->assertCreated();

    // Nothing was un-applied: the payment now over-holds, and the
    // ledger says so as credit.
    $this->withToken($token)
        ->getJson('/api/students/'.$student->id.'/balance')
        ->assertOk()
        ->assertJsonPath('balance', -45000)
        ->assertJsonPath('credit', 45000)
        ->assertJsonPath('arrears', 0)
        ->assertJsonPath('total_invoiced', 0)
        ->assertJsonPath('net_payments', 45000);
});

test('the reversal requires a reason', function () {
    $token = financeAccessTokenFor(User::factory()->finance()->create());
    $invoice = Invoice::factory()->create();

    $this->withToken($token)
        ->postJson('/api/invoices/'.$invoice->id.'/reverse', [
            'idempotency_key' => 'reverse-000020',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);

    $this->withToken($token)
        ->postJson('/api/invoices/'.$invoice->id.'/reverse', [
            'reason' => 'ok',
            'idempotency_key' => 'reverse-000021',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

test('the invoice list derives paid, outstanding and status from the ledger', function () {
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
            'idempotency_key' => 'payment-000002',
        ])
        ->assertCreated();

    // Oldest first: the 1,000 invoice settles fully, the 2,500 one
    // takes the remaining 1,000 and stays partly open.
    $response = $this->withToken($token)
        ->getJson('/api/invoices')
        ->assertOk()
        ->assertJsonCount(2, 'invoices')
        ->assertJsonPath('invoices.0.id', $newer->id)
        ->assertJsonPath('invoices.0.status', 'partially_paid')
        ->assertJsonPath('invoices.0.paid', 1000)
        ->assertJsonPath('invoices.0.outstanding', 1500)
        ->assertJsonPath('invoices.1.id', $older->id)
        ->assertJsonPath('invoices.1.status', 'paid')
        ->assertJsonPath('invoices.1.paid', 1000)
        ->assertJsonPath('invoices.1.outstanding', 0);

    $this->withToken($token)
        ->getJson('/api/invoices?status=paid')
        ->assertOk()
        ->assertJsonCount(1, 'invoices')
        ->assertJsonPath('invoices.0.id', $older->id);

    $this->withToken($token)
        ->getJson('/api/invoices?status=unpaid')
        ->assertOk()
        ->assertJsonCount(0, 'invoices');

    // An unknown status is a validation error, not a silent wildcard.
    $this->withToken($token)->getJson('/api/invoices?status=writing_off')->assertUnprocessable();

    // A reversed original reads with the reversed status and zeroed
    // figures.
    $this->withToken($token)
        ->postJson('/api/invoices/'.$older->id.'/reverse', [
            'reason' => 'Duplicated charge',
            'idempotency_key' => 'reverse-000030',
        ])
        ->assertCreated();

    $this->withToken($token)
        ->getJson('/api/invoices?search=')
        ->assertOk()
        ->assertJsonFragment(['id' => $older->id, 'status' => 'reversed', 'paid' => 0.0, 'outstanding' => 0.0]);

    // The reversal row itself also reads as reversed, with its
    // negative amount on show.
    $this->withToken($token)
        ->getJson('/api/invoices?status=reversed')
        ->assertOk()
        ->assertJsonCount(2, 'invoices');
});

test('the invoice list derives every student\'s figures in a fixed number of queries', function () {
    $token = financeAccessTokenFor(User::factory()->finance()->create());
    $session = financeActiveSession();

    // Three students, each billed twice and partly paid: the list must
    // derive all of their figures in ONE query per ledger table, not
    // three queries per student.
    foreach (range(1, 3) as $i) {
        [$student] = financeBillableStudent($session);

        Invoice::factory()->count(2)->create([
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
        ]);
    }

    $this->expectsDatabaseQueryCount(12);

    $this->withToken($token)
        ->getJson('/api/invoices?per_page=100')
        ->assertOk()
        ->assertJsonCount(6, 'invoices');
});

test('the finance ledger has no edit and no delete anywhere', function () {
    $token = financeAccessTokenFor(User::factory()->finance()->create());
    $invoice = Invoice::factory()->create();

    $this->withToken($token)->putJson('/api/invoices/'.$invoice->id, [])->assertMethodNotAllowed();
    $this->withToken($token)->patchJson('/api/invoices/'.$invoice->id, [])->assertMethodNotAllowed();
    $this->withToken($token)->deleteJson('/api/invoices/'.$invoice->id)->assertMethodNotAllowed();

    // Payments and refunds have no route for these verbs at all —
    // there is nothing to be allowed.
    $this->withToken($token)->putJson('/api/payments/1', [])->assertNotFound();
    $this->withToken($token)->deleteJson('/api/payments/1')->assertNotFound();
    $this->withToken($token)->deleteJson('/api/refunds/1')->assertNotFound();
});

test('retrying the same idempotency key raises no second invoice', function () {
    $token = financeAccessTokenFor(User::factory()->finance()->create());
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    $payload = [
        'student_id' => $student->id,
        'invoice_type' => 'PENALTY',
        'description' => 'Late registration penalty',
        'amount' => 500,
        'idempotency_key' => 'retry-key-000001',
    ];

    $first = $this->withToken($token)->postJson('/api/invoices/other', $payload)->assertCreated();
    $second = $this->withToken($token)->postJson('/api/invoices/other', $payload)->assertCreated();

    expect($first->json('invoice.id'))->toBe($second->json('invoice.id'))
        ->and($first->json('invoice.invoice_number'))->toBe($second->json('invoice.invoice_number'))
        ->and(Invoice::query()->where('student_id', $student->id)->count())->toBe(1);
});

test('every finance write is refused while no session is active', function () {
    $token = financeAccessTokenFor(User::factory()->finance()->create());
    [$student] = financeBillableStudent(financeActiveSession());

    AcademicSession::query()->update(['is_active' => false]);

    $this->withToken($token)
        ->postJson('/api/invoices/other', [
            'student_id' => $student->id,
            'invoice_type' => 'OTHER',
            'description' => 'Charged into the void',
            'amount' => 100,
            'idempotency_key' => 'no-session-00001',
        ])
        ->assertStatus(409)
        ->assertJsonPath('message', 'No active academic session is configured. Activate a session before recording finance entries.');

    $this->withToken($token)
        ->postJson('/api/payments', [
            'student_id' => $student->id,
            'payment_method' => 'CASH',
            'amount' => 100,
            'idempotency_key' => 'no-session-00002',
        ])
        ->assertStatus(409);
});

test('the fee-structure picker lists what applies and flags what is already invoiced', function () {
    $token = financeAccessTokenFor(User::factory()->finance()->create());
    $session = financeActiveSession();
    [$student, $feeStructure] = financeBillableStudent($session, 45000);

    $this->withToken($token)
        ->getJson('/api/invoices/fee-structures?student_id='.$student->id)
        ->assertOk()
        ->assertJsonPath('academic_session.id', $session->id)
        ->assertJsonCount(1, 'fee_structures')
        ->assertJsonPath('fee_structures.0.id', $feeStructure->id)
        ->assertJsonPath('fee_structures.0.total', 45000)
        ->assertJsonPath('fee_structures.0.already_invoiced', false);

    $this->withToken($token)
        ->postJson('/api/invoices/fee', [
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'idempotency_key' => 'picker-invoice-01',
        ])
        ->assertCreated();

    $this->withToken($token)
        ->getJson('/api/invoices/fee-structures?student_id='.$student->id)
        ->assertOk()
        ->assertJsonPath('fee_structures.0.already_invoiced', true);
});

test('one invoice reads with its derived figures and reversal links, and a missing one is a 404', function () {
    $token = financeAccessTokenFor(User::factory()->finance()->create());

    $invoice = Invoice::factory()->create(['amount' => 750, 'description' => 'Exam re-mark']);

    $this->withToken($token)
        ->getJson('/api/invoices/'.$invoice->id)
        ->assertOk()
        ->assertJsonPath('invoice.invoice_number', $invoice->invoice_number)
        ->assertJsonPath('invoice.amount', 750)
        ->assertJsonPath('invoice.status', 'unpaid')
        ->assertJsonPath('invoice.paid', 0)
        ->assertJsonPath('invoice.outstanding', 750)
        ->assertJsonPath('invoice.reversed_by', null);

    $this->withToken($token)->getJson('/api/invoices/999999')->assertNotFound();
});

test('the billing screens can search students without the roster permission', function () {
    $finance = User::factory()->finance()->create();
    $token = financeAccessTokenFor($finance);

    $student = StudentProfile::factory()->create([
        'first_name' => 'Wanjiku',
        'middle_name' => null,
        'last_name' => 'Otieno',
    ]);
    $student->user->update(['login_id' => 'ADM-2026-0042']);

    $this->withToken($token)
        ->getJson('/api/invoices/students?search=Wanjiku')
        ->assertOk()
        ->assertJsonCount(1, 'students')
        ->assertJsonPath('students.0.id', $student->id)
        ->assertJsonPath('students.0.name', 'Wanjiku Otieno')
        ->assertJsonPath('students.0.login_id', 'ADM-2026-0042');

    $this->withToken($token)
        ->getJson('/api/invoices/students?search=ADM-2026-0042')
        ->assertOk()
        ->assertJsonCount(1, 'students');

    // Finance bills students without ever holding the roster itself.
    $this->withToken($token)->getJson('/api/students')->assertForbidden();

    // The picker rides invoices.create, not invoices.view.
    $viewer = User::factory()->create();
    $viewer->syncRoles([]);
    $viewer->givePermissionTo('invoices.view');

    $this->withToken(financeAccessTokenFor($viewer))
        ->getJson('/api/invoices/students')
        ->assertForbidden();
});

test('a student’s invoices read with derived figures and reversal links behind their own permission', function () {
    $token = financeAccessTokenFor(User::factory()->finance()->create());
    $session = financeActiveSession();
    [$student] = financeBillableStudent($session);

    $active = Invoice::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 500,
        'created_at' => now()->subDay(),
    ]);

    $reversed = Invoice::factory()->create([
        'student_id' => $student->id,
        'academic_session_id' => $session->id,
        'amount' => 300,
        'created_at' => now()->subDays(2),
    ]);

    $reversal = Invoice::factory()->reversed($reversed)->create([
        'created_at' => now(),
    ]);

    // A different student's invoices never leak into this ledger.
    Invoice::factory()->create(['amount' => 999]);

    $this->withToken($token)
        ->getJson('/api/students/'.$student->id.'/invoices')
        ->assertOk()
        ->assertJsonCount(3, 'invoices')
        // Newest first: the reversal row, then the active charge, then
        // the reversed original. The active charge keeps its figures,
        // while the reversed original and the reversal row alike settle
        // to the reversed status with zeroed figures.
        ->assertJsonPath('invoices.0.id', $reversal->id)
        ->assertJsonPath('invoices.0.status', 'reversed')
        ->assertJsonPath('invoices.0.entry_kind', 'REVERSAL')
        ->assertJsonPath('invoices.1.id', $active->id)
        ->assertJsonPath('invoices.1.status', 'unpaid')
        ->assertJsonPath('invoices.1.outstanding', 500)
        ->assertJsonFragment(['id' => $reversed->id, 'status' => 'reversed', 'paid' => 0.0, 'outstanding' => 0.0]);

    // The read rides either the viewer or the reverser — both desk
    // roles work the reverse screen.
    $viewer = User::factory()->create();
    $viewer->syncRoles([]);
    $viewer->givePermissionTo('invoices.view');

    $this->withToken(financeAccessTokenFor($viewer))
        ->getJson('/api/students/'.$student->id.'/invoices')
        ->assertOk();

    $reverser = User::factory()->create();
    $reverser->syncRoles([]);
    $reverser->givePermissionTo('invoices.reverse');

    $this->withToken(financeAccessTokenFor($reverser))
        ->getJson('/api/students/'.$student->id.'/invoices')
        ->assertOk();

    $outsider = User::factory()->trainer()->create();

    $this->withToken(financeAccessTokenFor($outsider))
        ->getJson('/api/students/'.$student->id.'/invoices')
        ->assertForbidden();

    $this->withToken($token)->getJson('/api/students/999999/invoices')->assertNotFound();
});
