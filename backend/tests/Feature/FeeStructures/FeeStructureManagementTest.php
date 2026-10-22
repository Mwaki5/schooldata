<?php

use App\Enums\Role;
use App\Models\FeeStructure;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function feeStructuresAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('fee structure endpoints require an access token', function () {
    $this->getJson('/api/fee-structures')->assertUnauthorized();
    $this->postJson('/api/fee-structures', ['name' => 'Term 1 Fees'])
        ->assertUnauthorized();
});

test('only admins may manage fee structures', function () {
    $token = feeStructuresAccessTokenFor(User::factory()->create(['role' => Role::Trainer]));

    $this->withToken($token)->getJson('/api/fee-structures')->assertForbidden();
    $this->withToken($token)->postJson('/api/fee-structures', [])->assertForbidden();

    $studentToken = feeStructuresAccessTokenFor(User::factory()->create(['role' => Role::Student]));

    $this->withToken($studentToken)->getJson('/api/fee-structures')->assertForbidden();
});

test('fee structures can be listed ordered by name', function () {
    FeeStructure::factory()->create(['name' => 'Year 2 Semester 2 Fees']);
    FeeStructure::factory()->create(['name' => 'Year 1 Semester 1 Fees']);

    $this->withToken(feeStructuresAccessTokenFor(User::factory()->create()))
        ->getJson('/api/fee-structures')
        ->assertOk()
        ->assertJsonStructure([
            'fee_structures' => [[
                'id', 'name', 'is_active', 'created_by', 'updated_by',
            ]],
        ])
        ->assertJsonPath('fee_structures.0.name', 'Year 1 Semester 1 Fees')
        ->assertJsonPath('fee_structures.1.name', 'Year 2 Semester 2 Fees');
});

test('the fee structure list supports status filters, sorting and pagination', function () {
    FeeStructure::factory()->create(['name' => 'Active Plan', 'is_active' => true]);
    FeeStructure::factory()->create(['name' => 'Retired Plan', 'is_active' => false]);

    $token = feeStructuresAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->getJson('/api/fee-structures?is_active=1')
        ->assertOk()
        ->assertJsonCount(1, 'fee_structures')
        ->assertJsonPath('fee_structures.0.name', 'Active Plan');

    $sorted = $this->withToken($token)
        ->getJson('/api/fee-structures?sort=name&order=desc')
        ->assertOk();

    expect(collect($sorted->json('fee_structures'))->pluck('name')->all())
        ->toBe(['Retired Plan', 'Active Plan'])
        ->and($sorted->json('meta.total'))->toBe(2);
});

test('a fee structure can be created with audit stamps', function () {
    $creator = User::factory()->create();

    $this->withToken(feeStructuresAccessTokenFor($creator))
        ->postJson('/api/fee-structures', [
            'name' => 'Year 1 Semester 1 Fees',
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('fee_structure.name', 'Year 1 Semester 1 Fees')
        ->assertJsonPath('fee_structure.is_active', true)
        ->assertJsonPath('fee_structure.created_by', $creator->id)
        ->assertJsonPath('fee_structure.updated_by', $creator->id);

    $this->assertDatabaseHas('fee_structures', [
        'name' => 'Year 1 Semester 1 Fees',
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ]);
});

test('a fee structure only needs a name', function () {
    $user = User::factory()->create();

    $this->withToken(feeStructuresAccessTokenFor($user))
        ->postJson('/api/fee-structures', ['name' => 'CAIT Fees'])
        ->assertCreated()
        ->assertJsonPath('fee_structure.is_active', true)
        ->assertJsonPath('fee_structure.created_by', $user->id);
});

test('creating a fee structure requires a name', function () {
    $this->withToken(feeStructuresAccessTokenFor(User::factory()->create()))
        ->postJson('/api/fee-structures', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('fee structure names must be unique among live records', function () {
    FeeStructure::factory()->create(['name' => 'Year 1 Semester 1 Fees']);

    $this->withToken(feeStructuresAccessTokenFor(User::factory()->create()))
        ->postJson('/api/fee-structures', ['name' => 'Year 1 Semester 1 Fees'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('fee structure names are capped at 150 characters', function () {
    $this->withToken(feeStructuresAccessTokenFor(User::factory()->create()))
        ->postJson('/api/fee-structures', ['name' => str_repeat('a', 151)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('a fee structure can be renamed and toggled, stamping the updating user', function () {
    $creator = User::factory()->create();
    $updater = User::factory()->create();
    $feeStructure = FeeStructure::factory()->create([
        'name' => 'Year 1 Semester 1 Fees',
        'is_active' => true,
        'created_by' => $creator->id,
    ]);

    $this->withToken(feeStructuresAccessTokenFor($updater))
        ->putJson('/api/fee-structures/'.$feeStructure->id, [
            'name' => 'Year 1 Tuition Plan',
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('fee_structure.name', 'Year 1 Tuition Plan')
        ->assertJsonPath('fee_structure.is_active', false)
        ->assertJsonPath('fee_structure.created_by', $creator->id)
        ->assertJsonPath('fee_structure.updated_by', $updater->id);

    $this->assertDatabaseHas('fee_structures', [
        'id' => $feeStructure->id,
        'name' => 'Year 1 Tuition Plan',
        'is_active' => 0,
        'created_by' => $creator->id,
        'updated_by' => $updater->id,
    ]);
});

test('updating a fee structure validates its name excluding itself', function () {
    $feeStructure = FeeStructure::factory()->create(['name' => 'Year 1 Semester 1 Fees']);
    FeeStructure::factory()->create(['name' => 'Year 2 Semester 1 Fees']);

    $this->withToken(feeStructuresAccessTokenFor(User::factory()->create()))
        ->putJson('/api/fee-structures/'.$feeStructure->id, [
            'name' => 'Year 2 Semester 1 Fees',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    $this->withToken(feeStructuresAccessTokenFor(User::factory()->create()))
        ->putJson('/api/fee-structures/'.$feeStructure->id, [
            'name' => 'Year 1 Semester 1 Fees',
        ])
        ->assertOk();
});

test('a fee structure is soft deleted and disappears from the api', function () {
    $user = User::factory()->create();
    $feeStructure = FeeStructure::factory()->create(['name' => 'Year 1 Semester 1 Fees']);

    $this->withToken(feeStructuresAccessTokenFor($user))
        ->deleteJson('/api/fee-structures/'.$feeStructure->id)
        ->assertNoContent();

    $this->assertSoftDeleted('fee_structures', ['id' => $feeStructure->id]);

    $this->withToken(feeStructuresAccessTokenFor($user))
        ->getJson('/api/fee-structures/'.$feeStructure->id)
        ->assertNotFound();

    $this->withToken(feeStructuresAccessTokenFor($user))
        ->getJson('/api/fee-structures')
        ->assertOk()
        ->assertJsonMissing(['id' => $feeStructure->id]);
});

test('a soft deleted fee structure frees its name for reuse', function () {
    $user = User::factory()->create();
    FeeStructure::factory()->create(['name' => 'Year 1 Semester 1 Fees'])->delete();

    $this->withToken(feeStructuresAccessTokenFor($user))
        ->postJson('/api/fee-structures', ['name' => 'Year 1 Semester 1 Fees'])
        ->assertCreated()
        ->assertJsonPath('fee_structure.name', 'Year 1 Semester 1 Fees');
});

test('fee structures can be searched by name', function () {
    FeeStructure::factory()->create(['name' => 'Year 1 Semester 1 Fees']);
    FeeStructure::factory()->create(['name' => 'Industrial Attachment Fees']);

    $this->withToken(feeStructuresAccessTokenFor(User::factory()->create()))
        ->getJson('/api/fee-structures?search=attachment')
        ->assertOk()
        ->assertJsonCount(1, 'fee_structures')
        ->assertJsonPath('fee_structures.0.name', 'Industrial Attachment Fees');
});

test('a search with no matches returns an empty list', function () {
    FeeStructure::factory()->create(['name' => 'Year 1 Semester 1 Fees']);

    $this->withToken(feeStructuresAccessTokenFor(User::factory()->create()))
        ->getJson('/api/fee-structures?search=zzzz')
        ->assertOk()
        ->assertJson(['fee_structures' => []]);
});

test('a missing fee structure returns 404', function () {
    $this->withToken(feeStructuresAccessTokenFor(User::factory()->create()))
        ->getJson('/api/fee-structures/999')
        ->assertNotFound();
});
