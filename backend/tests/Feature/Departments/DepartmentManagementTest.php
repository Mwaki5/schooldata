<?php

use App\Models\Department;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function departmentsAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('department endpoints require an access token', function () {
    $this->getJson('/api/departments')->assertUnauthorized();
    $this->postJson('/api/departments', ['name' => 'Science'])->assertUnauthorized();
});

test('departments can be listed ordered by name', function () {
    Department::factory()->create(['name' => 'Zoology']);
    Department::factory()->create(['name' => 'Biology']);

    $this->withToken(departmentsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/departments')
        ->assertOk()
        ->assertJsonStructure(['departments' => [['id', 'name', 'hod_id', 'hod_name', 'is_active']]])
        ->assertJsonPath('departments.0.name', 'Biology')
        ->assertJsonPath('departments.1.name', 'Zoology');
});

test('departments can be searched by name', function () {
    Department::factory()->create(['name' => 'Computing and Informatics']);
    Department::factory()->create(['name' => 'Finance and Accounts']);

    $this->withToken(departmentsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/departments?search=inform')
        ->assertOk()
        ->assertJsonCount(1, 'departments')
        ->assertJsonPath('departments.0.name', 'Computing and Informatics');
});

test('a department can be created with a hod user reference', function () {
    $hod = User::factory()->create(['name' => 'Dr. Mensah']);

    $response = $this->withToken(departmentsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/departments', [
            'name' => 'Mathematics',
            'hod_id' => $hod->id,
            'is_active' => true,
        ]);

    $response->assertCreated()
        ->assertJsonStructure(['department' => ['id', 'name', 'hod_id', 'hod_name', 'is_active']])
        ->assertJsonPath('department.name', 'Mathematics')
        ->assertJsonPath('department.hod_id', $hod->id)
        ->assertJsonPath('department.hod_name', 'Dr. Mensah')
        ->assertJsonPath('department.is_active', true);

    $this->assertDatabaseHas('departments', [
        'name' => 'Mathematics',
        'hod_id' => $hod->id,
        'is_active' => 1,
    ]);
});

test('the hod is optional and departments default to active', function () {
    $this->withToken(departmentsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/departments', ['name' => 'History'])
        ->assertCreated()
        ->assertJsonPath('department.hod_id', null)
        ->assertJsonPath('department.hod_name', null)
        ->assertJsonPath('department.is_active', true);
});

test('the hod must reference an existing user', function () {
    $this->withToken(departmentsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/departments', [
            'name' => 'Geography',
            'hod_id' => 999,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['hod_id']);
});

test('creating a department requires a name', function () {
    $this->withToken(departmentsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/departments', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('department names must be unique', function () {
    Department::factory()->create(['name' => 'Science']);

    $this->withToken(departmentsAccessTokenFor(User::factory()->create()))
        ->postJson('/api/departments', ['name' => 'Science'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('a department can be updated including hod and active flag', function () {
    $department = Department::factory()->create([
        'name' => 'Physics',
        'hod_id' => null,
        'is_active' => true,
    ]);
    $newHod = User::factory()->create(['name' => 'Dr. Boateng']);

    $this->withToken(departmentsAccessTokenFor(User::factory()->create()))
        ->putJson('/api/departments/'.$department->id, [
            'name' => 'Applied Physics',
            'hod_id' => $newHod->id,
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('department.name', 'Applied Physics')
        ->assertJsonPath('department.hod_id', $newHod->id)
        ->assertJsonPath('department.hod_name', 'Dr. Boateng')
        ->assertJsonPath('department.is_active', false);

    $this->assertDatabaseHas('departments', [
        'id' => $department->id,
        'name' => 'Applied Physics',
        'hod_id' => $newHod->id,
        'is_active' => 0,
    ]);
});

test('the hod can be cleared by sending null', function () {
    $department = Department::factory()->create([
        'name' => 'Art',
        'hod_id' => User::factory()->create()->id,
    ]);

    $this->withToken(departmentsAccessTokenFor(User::factory()->create()))
        ->putJson('/api/departments/'.$department->id, [
            'name' => 'Art',
            'hod_id' => null,
        ])
        ->assertOk()
        ->assertJsonPath('department.hod_id', null)
        ->assertJsonPath('department.hod_name', null);

    $this->assertDatabaseHas('departments', ['id' => $department->id, 'hod_id' => null]);
});

test('deleting the hod user clears the reference', function () {
    $hod = User::factory()->create();
    $department = Department::factory()->create(['hod_id' => $hod->id]);

    $hod->delete();

    $this->assertDatabaseHas('departments', ['id' => $department->id, 'hod_id' => null]);
});

test('updating a department validates unique names excluding itself', function () {
    $department = Department::factory()->create(['name' => 'Chemistry']);
    Department::factory()->create(['name' => 'Biology']);

    $this->withToken(departmentsAccessTokenFor(User::factory()->create()))
        ->putJson('/api/departments/'.$department->id, ['name' => 'Biology'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    $this->withToken(departmentsAccessTokenFor(User::factory()->create()))
        ->putJson('/api/departments/'.$department->id, ['name' => 'Chemistry'])
        ->assertOk();
});

test('a missing department returns 404', function () {
    $this->withToken(departmentsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/departments/999')
        ->assertNotFound();
});

test('a department can be deleted', function () {
    $department = Department::factory()->create();

    $this->withToken(departmentsAccessTokenFor(User::factory()->create()))
        ->deleteJson('/api/departments/'.$department->id)
        ->assertNoContent();

    $this->assertDatabaseMissing('departments', ['id' => $department->id]);
});
