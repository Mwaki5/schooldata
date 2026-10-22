<?php

use App\Enums\Role;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function feeStructureItemsAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('fee structure item endpoints require an access token', function () {
    $structure = FeeStructure::factory()->create();

    $this->getJson('/api/fee-structures/'.$structure->id.'/items')->assertUnauthorized();
    $this->postJson('/api/fee-structures/'.$structure->id.'/items', [
        'name' => 'Tuition',
        'amount' => 45000,
    ])->assertUnauthorized();
});

test('only admins manage fee structure items', function () {
    $structure = FeeStructure::factory()->create();
    $token = feeStructureItemsAccessTokenFor(User::factory()->create(['role' => Role::Trainer]));

    $this->withToken($token)->getJson('/api/fee-structures/'.$structure->id.'/items')->assertForbidden();
    $this->withToken($token)->postJson('/api/fee-structures/'.$structure->id.'/items', [])->assertForbidden();
});

test('items are listed for their structure ordered by name', function () {
    $structure = FeeStructure::factory()->create();
    $other = FeeStructure::factory()->create();

    FeeStructureItem::factory()->create([
        'fee_structure_id' => $structure->id,
        'name' => 'Tuition',
        'amount' => 45000,
    ]);
    FeeStructureItem::factory()->create([
        'fee_structure_id' => $structure->id,
        'name' => 'Examination Fees',
        'amount' => 5000,
    ]);
    // Another structure's charge must never appear on this one's list.
    FeeStructureItem::factory()->create([
        'fee_structure_id' => $other->id,
        'name' => 'Library Levy',
        'amount' => 1000,
    ]);

    $this->withToken(feeStructureItemsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/fee-structures/'.$structure->id.'/items')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [[
                'id', 'fee_structure_id', 'name', 'amount', 'created_by', 'updated_by',
            ]],
            'total',
            'meta',
        ])
        ->assertJsonCount(2, 'items')
        ->assertJsonPath('items.0.name', 'Examination Fees')
        ->assertJsonPath('items.1.name', 'Tuition')
        ->assertJsonPath('items.1.fee_structure_id', $structure->id);
});

test('the items list reports the total of the live amounts', function () {
    $structure = FeeStructure::factory()->create();
    FeeStructureItem::factory()->create([
        'fee_structure_id' => $structure->id,
        'name' => 'Tuition',
        'amount' => 45000,
    ]);
    FeeStructureItem::factory()->create([
        'fee_structure_id' => $structure->id,
        'name' => 'Sports Levy',
        'amount' => 5000,
    ]);
    FeeStructureItem::factory()->create([
        'fee_structure_id' => $structure->id,
        'name' => 'Retired Levy',
        'amount' => 999,
    ])->delete();

    // Only the two live charges are listed, and their amounts are the
    // total the structure charges.
    $this->withToken(feeStructureItemsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/fee-structures/'.$structure->id.'/items')
        ->assertOk()
        ->assertJsonCount(2, 'items')
        ->assertJsonPath('items.0.name', 'Sports Levy')
        ->assertJsonPath('items.1.name', 'Tuition')
        ->assertJsonPath('total', 50000)
        ->assertJsonPath('meta.total', 2);

    // Deleting every item drops the total to zero.
    FeeStructureItem::query()->where('fee_structure_id', $structure->id)->delete();

    $this->withToken(feeStructureItemsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/fee-structures/'.$structure->id.'/items')
        ->assertOk()
        ->assertJsonPath('total', 0);
});

test('an item can be created with audit stamps', function () {
    $structure = FeeStructure::factory()->create();
    $creator = User::factory()->create();

    $this->withToken(feeStructureItemsAccessTokenFor($creator))
        ->postJson('/api/fee-structures/'.$structure->id.'/items', [
            'name' => 'Tuition',
            'amount' => 45000.50,
        ])
        ->assertCreated()
        ->assertJsonPath('item.fee_structure_id', $structure->id)
        ->assertJsonPath('item.name', 'Tuition')
        ->assertJsonPath('item.amount', 45000.5)
        ->assertJsonPath('item.created_by', $creator->id)
        ->assertJsonPath('item.updated_by', $creator->id);

    $this->assertDatabaseHas('fee_structure_items', [
        'fee_structure_id' => $structure->id,
        'name' => 'Tuition',
        'amount' => 45000.50,
        'created_by' => $creator->id,
    ]);
});

test('creating an item requires a name and amount', function () {
    $structure = FeeStructure::factory()->create();

    $this->withToken(feeStructureItemsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/fee-structures/'.$structure->id.'/items', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'amount']);
});

test('an item amount must be a non-negative number', function () {
    $structure = FeeStructure::factory()->create();
    $token = feeStructureItemsAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->postJson('/api/fee-structures/'.$structure->id.'/items', [
            'name' => 'Tuition',
            'amount' => -5,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);

    $this->withToken($token)
        ->postJson('/api/fee-structures/'.$structure->id.'/items', [
            'name' => 'Tuition',
            'amount' => 'free',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

test('item names are unique within their structure but reusable in another', function () {
    $structure = FeeStructure::factory()->create();
    $other = FeeStructure::factory()->create();
    $token = feeStructureItemsAccessTokenFor(User::factory()->create());

    FeeStructureItem::factory()->create([
        'fee_structure_id' => $structure->id,
        'name' => 'Tuition',
    ]);

    $this->withToken($token)
        ->postJson('/api/fee-structures/'.$structure->id.'/items', [
            'name' => 'Tuition',
            'amount' => 1000,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    // The same name in a different structure is a different charge.
    $this->withToken($token)
        ->postJson('/api/fee-structures/'.$other->id.'/items', [
            'name' => 'Tuition',
            'amount' => 1000,
        ])
        ->assertCreated();
});

test('an item can be renamed and repriced, stamping the updating user', function () {
    $structure = FeeStructure::factory()->create();
    $creator = User::factory()->create();
    $updater = User::factory()->create();
    $item = FeeStructureItem::factory()->create([
        'fee_structure_id' => $structure->id,
        'name' => 'Tuition',
        'amount' => 45000,
        'created_by' => $creator->id,
    ]);

    $this->withToken(feeStructureItemsAccessTokenFor($updater))
        ->putJson('/api/fee-structures/'.$structure->id.'/items/'.$item->id, [
            'name' => 'Tuition (Revised)',
            'amount' => 48000,
        ])
        ->assertOk()
        ->assertJsonPath('item.name', 'Tuition (Revised)')
        ->assertJsonPath('item.amount', 48000)
        ->assertJsonPath('item.created_by', $creator->id)
        ->assertJsonPath('item.updated_by', $updater->id);

    $this->assertDatabaseHas('fee_structure_items', [
        'id' => $item->id,
        'name' => 'Tuition (Revised)',
        'amount' => 48000,
        'updated_by' => $updater->id,
    ]);
});

test('updating an item validates its name excluding itself', function () {
    $structure = FeeStructure::factory()->create();
    $item = FeeStructureItem::factory()->create([
        'fee_structure_id' => $structure->id,
        'name' => 'Tuition',
    ]);
    FeeStructureItem::factory()->create([
        'fee_structure_id' => $structure->id,
        'name' => 'Library Levy',
    ]);

    $this->withToken(feeStructureItemsAccessTokenFor(User::factory()->create()))
        ->putJson('/api/fee-structures/'.$structure->id.'/items/'.$item->id, [
            'name' => 'Library Levy',
            'amount' => 1000,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    $this->withToken(feeStructureItemsAccessTokenFor(User::factory()->create()))
        ->putJson('/api/fee-structures/'.$structure->id.'/items/'.$item->id, [
            'name' => 'Tuition',
            'amount' => 1000,
        ])
        ->assertOk();
});

test('an item is soft deleted, disappears and frees its name', function () {
    $structure = FeeStructure::factory()->create();
    $token = feeStructureItemsAccessTokenFor(User::factory()->create());
    $item = FeeStructureItem::factory()->create([
        'fee_structure_id' => $structure->id,
        'name' => 'Tuition',
    ]);

    $this->withToken($token)
        ->deleteJson('/api/fee-structures/'.$structure->id.'/items/'.$item->id)
        ->assertNoContent();

    $this->assertSoftDeleted('fee_structure_items', ['id' => $item->id]);

    $this->withToken($token)
        ->getJson('/api/fee-structures/'.$structure->id.'/items')
        ->assertOk()
        ->assertJsonCount(0, 'items')
        ->assertJsonPath('total', 0);

    $this->withToken($token)
        ->postJson('/api/fee-structures/'.$structure->id.'/items', [
            'name' => 'Tuition',
            'amount' => 46000,
        ])
        ->assertCreated();
});

test('an item of another structure is unreachable', function () {
    $structure = FeeStructure::factory()->create();
    $other = FeeStructure::factory()->create();
    $foreign = FeeStructureItem::factory()->create([
        'fee_structure_id' => $other->id,
    ]);
    $token = feeStructureItemsAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->putJson('/api/fee-structures/'.$structure->id.'/items/'.$foreign->id, [
            'name' => 'Hijacked',
            'amount' => 1,
        ])
        ->assertNotFound();

    $this->withToken($token)
        ->deleteJson('/api/fee-structures/'.$structure->id.'/items/'.$foreign->id)
        ->assertNotFound();

    $this->assertDatabaseHas('fee_structure_items', [
        'id' => $foreign->id,
        'name' => $foreign->name,
    ]);
});

test('a missing item or structure returns 404', function () {
    $structure = FeeStructure::factory()->create();
    $token = feeStructureItemsAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->getJson('/api/fee-structures/999/items')
        ->assertNotFound();

    $this->withToken($token)
        ->putJson('/api/fee-structures/'.$structure->id.'/items/999', [
            'name' => 'Ghost',
            'amount' => 1,
        ])
        ->assertNotFound();
});

test('fee structure payloads carry the summed item total', function () {
    // Pinned names: the list is ordered by name, so the assertion must
    // not depend on the factory's random draw.
    $structure = FeeStructure::factory()->create(['name' => 'A Total Plan']);
    FeeStructureItem::factory()->create([
        'fee_structure_id' => $structure->id,
        'amount' => 45000,
    ]);
    FeeStructureItem::factory()->create([
        'fee_structure_id' => $structure->id,
        'amount' => 5000,
    ]);
    $empty = FeeStructure::factory()->create(['name' => 'B Total Plan']);
    $token = feeStructureItemsAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->getJson('/api/fee-structures')
        ->assertOk()
        ->assertJsonPath('fee_structures.0.id', $structure->id)
        ->assertJsonPath('fee_structures.0.total', 50000)
        ->assertJsonPath('fee_structures.1.id', $empty->id)
        ->assertJsonPath('fee_structures.1.total', 0);

    $this->withToken($token)
        ->getJson('/api/fee-structures/'.$structure->id)
        ->assertOk()
        ->assertJsonPath('fee_structure.total', 50000);
});
