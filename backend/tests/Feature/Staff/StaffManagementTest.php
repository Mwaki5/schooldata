<?php

use App\Models\Department;
use App\Models\Staff;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function staffAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('staff endpoints require an access token', function () {
    $this->getJson('/api/staff')->assertUnauthorized();
    $this->postJson('/api/staff', ['employment_no' => 'EMP-0001', 'name' => 'Jane Doe'])
        ->assertUnauthorized();
});

test('staff can be listed ordered by employment number with their department', function () {
    $department = Department::factory()->create(['name' => 'Computing and Informatics']);

    Staff::factory()->create(['employment_no' => 'EMP-0002', 'name' => 'Zoe Writer', 'department_id' => $department->id]);
    Staff::factory()->create(['employment_no' => 'EMP-0001', 'name' => 'Adam Clerk']);

    $this->withToken(staffAccessTokenFor(User::factory()->create()))
        ->getJson('/api/staff')
        ->assertOk()
        ->assertJsonStructure([
            'staff' => [[
                'id', 'employment_no', 'name', 'email', 'phone', 'position',
                'department_id', 'department_name', 'is_active', 'created_at', 'updated_at',
            ]],
        ])
        ->assertJsonPath('staff.0.employment_no', 'EMP-0001')
        ->assertJsonPath('staff.1.employment_no', 'EMP-0002')
        ->assertJsonPath('staff.1.department_name', 'Computing and Informatics')
        ->assertJsonPath('staff.0.department_name', null);
});

test('a staff member can be created with every field and audit stamps', function () {
    $creator = User::factory()->create();
    $department = Department::factory()->create();

    $response = $this->withToken(staffAccessTokenFor($creator))
        ->postJson('/api/staff', [
            'employment_no' => 'EMP-0001',
            'name' => 'Jane Doe',
            'email' => 'jane.doe@school.test',
            'phone' => '+254 700 000 001',
            'position' => 'Senior Lecturer',
            'department_id' => $department->id,
            'is_active' => true,
        ]);

    $response->assertCreated()
        ->assertJsonPath('staff.employment_no', 'EMP-0001')
        ->assertJsonPath('staff.name', 'Jane Doe')
        ->assertJsonPath('staff.email', 'jane.doe@school.test')
        ->assertJsonPath('staff.phone', '+254 700 000 001')
        ->assertJsonPath('staff.position', 'Senior Lecturer')
        ->assertJsonPath('staff.department_id', $department->id)
        ->assertJsonPath('staff.department_name', $department->name)
        ->assertJsonPath('staff.is_active', true)
        ->assertJsonPath('staff.created_by', $creator->id)
        ->assertJsonPath('staff.updated_by', $creator->id);

    $this->assertDatabaseHas('staff', [
        'employment_no' => 'EMP-0001',
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ]);
});

test('a staff member only needs an employment number and name', function () {
    $user = User::factory()->create();

    $this->withToken(staffAccessTokenFor($user))
        ->postJson('/api/staff', [
            'employment_no' => 'EMP-0002',
            'name' => 'John Otieno',
        ])
        ->assertCreated()
        ->assertJsonPath('staff.is_active', true)
        ->assertJsonPath('staff.email', null)
        ->assertJsonPath('staff.phone', null)
        ->assertJsonPath('staff.position', null)
        ->assertJsonPath('staff.department_id', null)
        ->assertJsonPath('staff.created_by', $user->id);
});

test('creating staff validates the required fields and department reference', function () {
    $this->withToken(staffAccessTokenFor(User::factory()->create()))
        ->postJson('/api/staff', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employment_no', 'name']);

    $this->withToken(staffAccessTokenFor(User::factory()->create()))
        ->postJson('/api/staff', [
            'employment_no' => 'EMP-0003',
            'name' => 'No Department',
            'department_id' => 999999,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['department_id']);
});

test('employment numbers must be unique among live records', function () {
    Staff::factory()->create(['employment_no' => 'EMP-0001']);

    $this->withToken(staffAccessTokenFor(User::factory()->create()))
        ->postJson('/api/staff', [
            'employment_no' => 'EMP-0001',
            'name' => 'Another Person',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employment_no']);
});

test('staff email addresses must be unique and valid among live records', function () {
    Staff::factory()->create(['email' => 'taken@school.test']);

    $this->withToken(staffAccessTokenFor(User::factory()->create()))
        ->postJson('/api/staff', [
            'employment_no' => 'EMP-0004',
            'name' => 'Duplicate Email',
            'email' => 'taken@school.test',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    $this->withToken(staffAccessTokenFor(User::factory()->create()))
        ->postJson('/api/staff', [
            'employment_no' => 'EMP-0005',
            'name' => 'Bad Email',
            'email' => 'not-an-email',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('a staff member can be updated and stamps the updating user', function () {
    $creator = User::factory()->create();
    $updater = User::factory()->create();
    $department = Department::factory()->create();
    $staffMember = Staff::factory()->create([
        'employment_no' => 'EMP-0001',
        'is_active' => true,
        'created_by' => $creator->id,
    ]);

    $this->withToken(staffAccessTokenFor($updater))
        ->putJson('/api/staff/'.$staffMember->id, [
            'employment_no' => 'EMP-0001',
            'name' => 'Jane Revised',
            'position' => 'Head of Department',
            'department_id' => $department->id,
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('staff.name', 'Jane Revised')
        ->assertJsonPath('staff.position', 'Head of Department')
        ->assertJsonPath('staff.department_name', $department->name)
        ->assertJsonPath('staff.is_active', false)
        ->assertJsonPath('staff.created_by', $creator->id)
        ->assertJsonPath('staff.updated_by', $updater->id);

    $this->assertDatabaseHas('staff', [
        'id' => $staffMember->id,
        'name' => 'Jane Revised',
        'is_active' => 0,
        'created_by' => $creator->id,
        'updated_by' => $updater->id,
    ]);
});

test('updating staff validates the employment number excluding itself', function () {
    $staffMember = Staff::factory()->create(['employment_no' => 'EMP-0001']);
    Staff::factory()->create(['employment_no' => 'EMP-0002']);

    $this->withToken(staffAccessTokenFor(User::factory()->create()))
        ->putJson('/api/staff/'.$staffMember->id, [
            'employment_no' => 'EMP-0002',
            'name' => 'Stolen Number',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employment_no']);

    $this->withToken(staffAccessTokenFor(User::factory()->create()))
        ->putJson('/api/staff/'.$staffMember->id, [
            'employment_no' => 'EMP-0001',
            'name' => 'Kept Number',
        ])
        ->assertOk();
});

test('a staff member is soft deleted and disappears from the api', function () {
    $user = User::factory()->create();
    $staffMember = Staff::factory()->create(['employment_no' => 'EMP-0001']);

    $this->withToken(staffAccessTokenFor($user))
        ->deleteJson('/api/staff/'.$staffMember->id)
        ->assertNoContent();

    $this->assertSoftDeleted('staff', ['id' => $staffMember->id]);

    $this->withToken(staffAccessTokenFor($user))
        ->getJson('/api/staff/'.$staffMember->id)
        ->assertNotFound();

    $this->withToken(staffAccessTokenFor($user))
        ->getJson('/api/staff')
        ->assertOk()
        ->assertJsonMissing(['id' => $staffMember->id]);
});

test('a soft deleted staff member frees their employment number for reuse', function () {
    $user = User::factory()->create();
    Staff::factory()->create(['employment_no' => 'EMP-0001'])->delete();

    $this->withToken(staffAccessTokenFor($user))
        ->postJson('/api/staff', [
            'employment_no' => 'EMP-0001',
            'name' => 'Rehired Person',
        ])
        ->assertCreated()
        ->assertJsonPath('staff.employment_no', 'EMP-0001');
});

test('a single staff member can be shown with their department', function () {
    $department = Department::factory()->create(['name' => 'Finance and Accounts']);
    $staffMember = Staff::factory()->create([
        'employment_no' => 'EMP-0010',
        'department_id' => $department->id,
    ]);

    $this->withToken(staffAccessTokenFor(User::factory()->create()))
        ->getJson('/api/staff/'.$staffMember->id)
        ->assertOk()
        ->assertJsonPath('staff.employment_no', 'EMP-0010')
        ->assertJsonPath('staff.department_name', 'Finance and Accounts');
});

test('a missing staff member returns 404', function () {
    $this->withToken(staffAccessTokenFor(User::factory()->create()))
        ->getJson('/api/staff/999')
        ->assertNotFound();
});

test('staff can be searched by name', function () {
    Staff::factory()->create(['name' => 'Jane Doe']);
    Staff::factory()->create(['name' => 'John Otieno']);

    $this->withToken(staffAccessTokenFor(User::factory()->create()))
        ->getJson('/api/staff?search=jane')
        ->assertOk()
        ->assertJsonCount(1, 'staff')
        ->assertJsonPath('staff.0.name', 'Jane Doe');
});

test('staff can be searched by employment number', function () {
    Staff::factory()->create(['employment_no' => 'EMP-0001']);
    Staff::factory()->create(['employment_no' => 'EMP-0002']);

    $this->withToken(staffAccessTokenFor(User::factory()->create()))
        ->getJson('/api/staff?search=EMP-0002')
        ->assertOk()
        ->assertJsonCount(1, 'staff')
        ->assertJsonPath('staff.0.employment_no', 'EMP-0002');
});

test('a staff search with no matches returns an empty list', function () {
    Staff::factory()->create(['name' => 'Jane Doe']);

    $this->withToken(staffAccessTokenFor(User::factory()->create()))
        ->getJson('/api/staff?search=zzzz')
        ->assertOk()
        ->assertJson(['staff' => []]);
});
