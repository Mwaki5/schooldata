<?php

use App\Models\AcademicSession;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\StudentProfile;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function invoicesAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('invoice endpoints require an access token', function () {
    $this->getJson('/api/invoices')->assertUnauthorized();
    $this->postJson('/api/invoices', [])->assertUnauthorized();
});

test('only invoice holders may work the billing desk', function () {
    $trainer = invoicesAccessTokenFor(User::factory()->trainer()->create());

    $this->withToken($trainer)->getJson('/api/invoices')->assertForbidden();
    $this->withToken($trainer)->postJson('/api/invoices', [])->assertForbidden();

    $student = invoicesAccessTokenFor(User::factory()->student()->create());

    $this->withToken($student)->getJson('/api/invoices')->assertForbidden();

    // The finance bundle carries every invoice verb: the list opens,
    // and store answers validation rather than the permission gate.
    $finance = invoicesAccessTokenFor(User::factory()->finance()->create());

    $this->withToken($finance)->getJson('/api/invoices')->assertOk();
    $this->withToken($finance)
        ->postJson('/api/invoices', [])
        ->assertUnprocessable();
});

test('a finance user raises an invoice with typed lines under a sequential number', function () {
    $finance = User::factory()->finance()->create();
    $student = StudentProfile::factory()->create();
    $session = AcademicSession::factory()->create();

    $response = $this->withToken(invoicesAccessTokenFor($finance))
        ->postJson('/api/invoices', [
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
            'description' => 'Term 2 charges',
            'items' => [
                ['type' => 'fee', 'description' => 'Tuition term 2', 'amount' => 45000],
                ['type' => 'hostel', 'description' => 'Block A bed', 'amount' => 12500.5],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('invoice.invoice_number', 'INV-'.now()->format('Y').'-0001')
        ->assertJsonPath('invoice.status', 'pending')
        ->assertJsonPath('invoice.student_id', $student->id)
        ->assertJsonPath(
            'invoice.student_name',
            User::composeName($student->first_name, $student->middle_name, $student->last_name),
        )
        ->assertJsonPath('invoice.academic_session_id', $session->id)
        ->assertJsonPath('invoice.description', 'Term 2 charges')
        ->assertJsonPath('invoice.total', 57500.5)
        ->assertJsonCount(2, 'invoice.items')
        ->assertJsonPath('invoice.items.0.type', 'fee')
        ->assertJsonPath('invoice.items.1.amount', 12500.5)
        ->assertJsonPath('invoice.created_by', $finance->id)
        ->assertJsonPath('invoice.updated_by', $finance->id);

    $this->assertDatabaseHas('invoices', [
        'id' => $response->json('invoice.id'),
        'student_id' => $student->id,
        'created_by' => $finance->id,
        'updated_by' => $finance->id,
    ]);

    $this->assertDatabaseHas('invoice_items', [
        'invoice_id' => $response->json('invoice.id'),
        'type' => 'hostel',
        'description' => 'Block A bed',
        'created_by' => $finance->id,
    ]);
});

test('invoice numbers advance and a deleted invoice never frees its number', function () {
    $token = invoicesAccessTokenFor(User::factory()->finance()->create());

    $payload = [
        'student_id' => StudentProfile::factory()->create()->id,
        'academic_session_id' => AcademicSession::factory()->create()->id,
        'items' => [['type' => 'fee', 'description' => 'Tuition', 'amount' => 1000]],
    ];

    $first = $this->withToken($token)
        ->postJson('/api/invoices', $payload)
        ->assertCreated();

    $this->withToken($token)
        ->deleteJson('/api/invoices/'.$first->json('invoice.id'))
        ->assertNoContent();

    $second = $this->withToken($token)
        ->postJson('/api/invoices', $payload)
        ->assertCreated();

    expect($first->json('invoice.invoice_number'))->toBe('INV-'.now()->format('Y').'-0001')
        ->and($second->json('invoice.invoice_number'))->toBe('INV-'.now()->format('Y').'-0002');
});

test('raising an invoice requires a student, a session and at least one valid line', function () {
    $token = invoicesAccessTokenFor(User::factory()->finance()->create());

    $this->withToken($token)
        ->postJson('/api/invoices', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['student_id', 'academic_session_id', 'items']);

    $header = [
        'student_id' => StudentProfile::factory()->create()->id,
        'academic_session_id' => AcademicSession::factory()->create()->id,
    ];

    $this->withToken($token)
        ->postJson('/api/invoices', [
            ...$header,
            'items' => [['type' => 'discount', 'description' => 'Bogus kind', 'amount' => 100]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.type']);

    $this->withToken($token)
        ->postJson('/api/invoices', [
            ...$header,
            'items' => [['type' => 'fee', 'description' => 'Tuition', 'amount' => -5]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.amount']);

    $this->withToken($token)
        ->postJson('/api/invoices', [...$header, 'items' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items']);
});

test('the invoice list reads newest first, finds students by name and filters by status', function () {
    $token = invoicesAccessTokenFor(User::factory()->finance()->create());

    $session = AcademicSession::factory()->create();
    $wanjiku = StudentProfile::factory()->create([
        'first_name' => 'Wanjiku',
        'middle_name' => null,
        'last_name' => 'Otieno',
    ]);
    $other = StudentProfile::factory()->create();

    $older = Invoice::factory()->create([
        'student_id' => $other->id,
        'academic_session_id' => $session->id,
        'status' => 'pending',
        'created_at' => now()->subDay(),
    ]);
    InvoiceItem::factory()->for($older)->create(['amount' => 2000]);

    $newer = Invoice::factory()->create([
        'student_id' => $wanjiku->id,
        'academic_session_id' => $session->id,
        'status' => 'paid',
        'created_at' => now(),
    ]);
    InvoiceItem::factory()->for($newer)->create(['amount' => 3500]);

    $response = $this->withToken($token)
        ->getJson('/api/invoices')
        ->assertOk()
        ->assertJsonCount(2, 'invoices')
        ->assertJsonPath('invoices.0.id', $newer->id)
        ->assertJsonPath('invoices.0.student_name', 'Wanjiku Otieno')
        ->assertJsonPath('invoices.0.status', 'paid')
        ->assertJsonPath('invoices.0.academic_session_name', $session->name);

    expect((float) $response->json('invoices.0.total'))->toBe(3500.0);

    $this->withToken($token)
        ->getJson('/api/invoices?search=Wanjiku')
        ->assertOk()
        ->assertJsonCount(1, 'invoices')
        ->assertJsonPath('invoices.0.id', $newer->id);

    $this->withToken($token)
        ->getJson('/api/invoices?status=pending')
        ->assertOk()
        ->assertJsonCount(1, 'invoices')
        ->assertJsonPath('invoices.0.id', $older->id);

    // An unknown status is a validation error, not a silent wildcard.
    $this->withToken($token)
        ->getJson('/api/invoices?status=writing_off')
        ->assertUnprocessable();
});

test('one invoice reads with its lines, and a missing one is a 404', function () {
    $token = invoicesAccessTokenFor(User::factory()->finance()->create());

    $invoice = Invoice::factory()->create();
    InvoiceItem::factory()->for($invoice)->create([
        'type' => 'penalty',
        'description' => 'Late submission',
        'amount' => 500,
    ]);

    $this->withToken($token)
        ->getJson('/api/invoices/'.$invoice->id)
        ->assertOk()
        ->assertJsonPath('invoice.invoice_number', $invoice->invoice_number)
        ->assertJsonCount(1, 'invoice.items')
        ->assertJsonPath('invoice.items.0.type', 'penalty')
        ->assertJsonPath('invoice.items.0.description', 'Late submission')
        ->assertJsonPath('invoice.items.0.amount', 500);

    $this->withToken($token)->getJson('/api/invoices/999999')->assertNotFound();
});

test('an invoice can be re-statused and its lines replaced, never re-anchored', function () {
    $updater = User::factory()->finance()->create();
    $invoice = Invoice::factory()->create(['status' => 'pending']);
    $oldItem = InvoiceItem::factory()->for($invoice)->create([
        'type' => 'fee',
        'description' => 'Tuition',
        'amount' => 45000,
    ]);

    $response = $this->withToken(invoicesAccessTokenFor($updater))
        ->putJson('/api/invoices/'.$invoice->id, [
            'status' => 'paid',
            'description' => 'Settled at the counter',
            'items' => [['type' => 'penalty', 'description' => 'Late fee', 'amount' => 500]],
            // A different billed student must be ignored: the anchor
            // never changes through the edit endpoint.
            'student_id' => StudentProfile::factory()->create()->id,
        ])
        ->assertOk()
        ->assertJsonPath('invoice.status', 'paid')
        ->assertJsonPath('invoice.description', 'Settled at the counter')
        ->assertJsonPath('invoice.student_id', $invoice->student_id)
        ->assertJsonCount(1, 'invoice.items')
        ->assertJsonPath('invoice.items.0.type', 'penalty')
        ->assertJsonPath('invoice.updated_by', $updater->id);

    expect((float) $response->json('invoice.total'))->toBe(500.0);

    // The replaced line keeps its history instead of vanishing.
    $this->assertSoftDeleted('invoice_items', ['id' => $oldItem->id]);
});

test('updating an invoice validates its status and keeps at least one line', function () {
    $token = invoicesAccessTokenFor(User::factory()->finance()->create());
    $invoice = Invoice::factory()->create();

    $this->withToken($token)
        ->putJson('/api/invoices/'.$invoice->id, [
            'status' => 'written_off',
            'items' => [['type' => 'fee', 'description' => 'Tuition', 'amount' => 100]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);

    $this->withToken($token)
        ->putJson('/api/invoices/'.$invoice->id, [
            'status' => 'void',
            'items' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items']);
});

test('an invoice is soft deleted and its number stays taken', function () {
    $token = invoicesAccessTokenFor(User::factory()->finance()->create());
    $invoice = Invoice::factory()->create();
    $item = InvoiceItem::factory()->for($invoice)->create();

    $this->withToken($token)
        ->deleteJson('/api/invoices/'.$invoice->id)
        ->assertNoContent();

    $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);

    // The lines stay on record under the trashed invoice.
    $this->assertDatabaseHas('invoice_items', [
        'id' => $item->id,
        'deleted_at' => null,
    ]);

    $this->withToken($token)->getJson('/api/invoices/'.$invoice->id)->assertNotFound();

    $this->withToken($token)
        ->getJson('/api/invoices')
        ->assertOk()
        ->assertJsonMissing(['id' => $invoice->id]);
});

test('the invoice form can search students without the student roster permission', function () {
    $finance = User::factory()->finance()->create();
    $token = invoicesAccessTokenFor($finance);

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

    $this->withToken(invoicesAccessTokenFor($viewer))
        ->getJson('/api/invoices/students')
        ->assertForbidden();
});
