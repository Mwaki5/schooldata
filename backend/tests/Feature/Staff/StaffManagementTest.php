<?php

use App\Models\Department;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

function staffAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('staff endpoints require an access token', function () {
    $this->getJson('/api/staff')->assertUnauthorized();
    $this->postJson('/api/staff', ['employment_no' => 'EMP-0001', 'first_name' => 'Jane', 'last_name' => 'Doe', 'role' => 'trainer'])
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
                'id', 'user_id', 'employment_no', 'name',
                'first_name', 'middle_name', 'last_name', 'gender',
                'date_of_birth', 'nationality', 'county', 'place_of_birth',
                'religion', 'address', 'city', 'postal_code',
                'profile_picture', 'alternative_phone', 'is_pwd',
                'disability_type', 'disability_description',
                'work_email', 'work_phone',
                'national_id', 'kra_pin', 'nhif_number', 'nssf_number',
                'position', 'department_id', 'department_name',
                'employment_type', 'employment_status', 'role',
                'date_joined', 'confirmation_date', 'contract_end_date',
                'termination_date', 'termination_reason', 'basic_salary',
                'highest_qualification', 'specialization',
                'created_at', 'updated_at',
            ]],
        ])
        ->assertJsonPath('staff.0.employment_no', 'EMP-0001')
        ->assertJsonPath('staff.1.employment_no', 'EMP-0002')
        ->assertJsonPath('staff.1.department_name', 'Computing and Informatics')
        ->assertJsonPath('staff.0.department_name', null);
});

test('creating staff provisions an account and returns a one-time temporary password', function () {
    $creator = User::factory()->create();
    $department = Department::factory()->create();

    $response = $this->withToken(staffAccessTokenFor($creator))
        ->postJson('/api/staff', [
            'employment_no' => 'TSC-000123',
            'first_name' => 'Jane',
            'middle_name' => 'Ann',
            'last_name' => 'Doe',
            'gender' => 'female',
            'date_of_birth' => '1990-05-04',
            'nationality' => 'Kenyan',
            'county' => 'Nairobi',
            'place_of_birth' => 'Nakuru',
            'religion' => 'Christian',
            'address' => 'P.O. Box 123, Nairobi',
            'city' => 'Nairobi',
            'postal_code' => '00100',
            'profile_picture' => 'https://cdn.example.test/jane.png',
            'alternative_phone' => '+254 711 000 111',
            'is_pwd' => false,
            'disability_type' => null,
            'disability_description' => null,
            'work_email' => 'jane.doe@school.test',
            'work_phone' => '+254 700 000 001',
            'national_id' => '12345678',
            'kra_pin' => 'A001234567X',
            'nhif_number' => 'NHIF-9911',
            'nssf_number' => 'NSSF-8822',
            'position' => 'Senior Lecturer',
            'department_id' => $department->id,
            'employment_type' => 'full_time',
            'employment_status' => 'probation',
            'role' => 'finance',
            'date_joined' => '2026-01-15',
            'confirmation_date' => '2026-07-15',
            'contract_end_date' => '2028-01-14',
            'termination_date' => null,
            'termination_reason' => null,
            'basic_salary' => 85000.5,
            'highest_qualification' => "Master's Degree",
            'specialization' => 'Computer Networks',
        ]);

    $response->assertCreated()
        ->assertJsonPath('staff.employment_no', 'TSC-000123')
        ->assertJsonPath('staff.name', 'Jane Ann Doe')
        ->assertJsonPath('staff.first_name', 'Jane')
        ->assertJsonPath('staff.middle_name', 'Ann')
        ->assertJsonPath('staff.last_name', 'Doe')
        ->assertJsonPath('staff.gender', 'female')
        ->assertJsonPath('staff.date_of_birth', '1990-05-04')
        ->assertJsonPath('staff.county', 'Nairobi')
        ->assertJsonPath('staff.is_pwd', false)
        ->assertJsonPath('staff.work_email', 'jane.doe@school.test')
        ->assertJsonPath('staff.work_phone', '+254 700 000 001')
        ->assertJsonPath('staff.national_id', '12345678')
        ->assertJsonPath('staff.kra_pin', 'A001234567X')
        ->assertJsonPath('staff.position', 'Senior Lecturer')
        ->assertJsonPath('staff.department_name', $department->name)
        ->assertJsonPath('staff.employment_type', 'full_time')
        ->assertJsonPath('staff.employment_status', 'probation')
        ->assertJsonPath('staff.role', 'finance')
        ->assertJsonPath('staff.date_joined', '2026-01-15')
        ->assertJsonPath('staff.basic_salary', '85000.50')
        ->assertJsonPath('staff.highest_qualification', "Master's Degree")
        ->assertJsonPath('staff.specialization', 'Computer Networks')
        ->assertJsonPath('staff.created_by', $creator->id)
        ->assertJsonPath('staff.updated_by', $creator->id);

    $temporaryPassword = $response->json('temporary_password');

    expect($temporaryPassword)->toBeString()->not->toBeEmpty();

    $user = User::where('login_id', 'TSC-000123')->firstOrFail();

    expect($user->name)->toBe('Jane Ann Doe')
        ->and($user->email)->toBe('jane.doe@school.test')
        ->and($user->role->value)->toBe('finance')
        ->and($user->gender)->toBe('female')
        ->and($user->must_reset_password)->toBeTrue()
        ->and(Hash::check($temporaryPassword, $user->password))->toBeTrue();

    $this->assertDatabaseHas('staff', [
        'employment_no' => 'TSC-000123',
        'user_id' => $user->id,
        'employment_status' => 'probation',
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ]);
});

test('a minimal staff record provisions an account with a placeholder email', function () {
    $user = User::factory()->create();

    $this->withToken(staffAccessTokenFor($user))
        ->postJson('/api/staff', [
            'employment_no' => 'EMP-0002',
            'first_name' => 'John',
            'last_name' => 'Otieno',
            'role' => 'trainer',
        ])
        ->assertCreated()
        ->assertJsonPath('staff.name', 'John Otieno')
        ->assertJsonPath('staff.role', 'trainer')
        ->assertJsonPath('staff.employment_status', 'active')
        ->assertJsonPath('staff.work_email', null)
        ->assertJsonPath('staff.position', null)
        ->assertJsonPath('staff.department_id', null)
        ->assertJsonPath('staff.date_joined', null)
        ->assertJsonPath('staff.basic_salary', null)
        ->assertJsonPath('staff.created_by', $user->id);

    $this->assertDatabaseHas('users', [
        'login_id' => 'EMP-0002',
        'email' => 'emp-0002@staff.invalid',
        'name' => 'John Otieno',
        'role' => 'trainer',
        'must_reset_password' => 1,
    ]);
});

test('creating staff validates the required fields and department reference', function () {
    $this->withToken(staffAccessTokenFor(User::factory()->create()))
        ->postJson('/api/staff', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employment_no', 'first_name', 'last_name', 'role']);

    $this->withToken(staffAccessTokenFor(User::factory()->create()))
        ->postJson('/api/staff', [
            'employment_no' => 'EMP-0003',
            'first_name' => 'No',
            'last_name' => 'Department',
            'role' => 'trainer',
            'department_id' => 999999,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['department_id']);
});

test('staff hr and identity fields are validated by type and vocabulary', function () {
    $this->withToken(staffAccessTokenFor(User::factory()->create()))
        ->postJson('/api/staff', [
            'employment_no' => 'EMP-0004',
            'first_name' => 'Bad',
            'last_name' => 'Values',
            'role' => 'student',
            'gender' => 'robot',
            'employment_type' => 'gig',
            'employment_status' => 'ghosted',
            'basic_salary' => 'a lot',
            'date_of_birth' => 'not-a-date',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'role',
            'gender',
            'employment_type',
            'employment_status',
            'basic_salary',
            'date_of_birth',
        ]);
});

test('employment numbers must be unique among live records', function () {
    Staff::factory()->create(['employment_no' => 'EMP-0001']);

    $this->withToken(staffAccessTokenFor(User::factory()->create()))
        ->postJson('/api/staff', [
            'employment_no' => 'EMP-0001',
            'first_name' => 'Another',
            'last_name' => 'Person',
            'role' => 'trainer',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employment_no']);
});

test('staff work email addresses must be unique and valid among live records', function () {
    Staff::factory()->create(['work_email' => 'taken@school.test']);

    $this->withToken(staffAccessTokenFor(User::factory()->create()))
        ->postJson('/api/staff', [
            'employment_no' => 'EMP-0005',
            'first_name' => 'Duplicate',
            'last_name' => 'Email',
            'role' => 'trainer',
            'work_email' => 'taken@school.test',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['work_email']);

    $this->withToken(staffAccessTokenFor(User::factory()->create()))
        ->postJson('/api/staff', [
            'employment_no' => 'EMP-0006',
            'first_name' => 'Bad',
            'last_name' => 'Email',
            'role' => 'trainer',
            'work_email' => 'not-an-email',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['work_email']);
});

test('a staff member can be updated and stamps the updating user', function () {
    $creator = User::factory()->create();
    $updater = User::factory()->create();
    $department = Department::factory()->create();
    $staffMember = Staff::factory()->create([
        'employment_no' => 'EMP-0001',
        'employment_status' => 'probation',
        'role' => 'trainer',
        'created_by' => $creator->id,
    ]);

    $this->withToken(staffAccessTokenFor($updater))
        ->putJson('/api/staff/'.$staffMember->id, [
            'employment_no' => 'EMP-0001',
            'first_name' => 'Jane',
            'middle_name' => 'Ann',
            'last_name' => 'Revised',
            'role' => 'trainer',
            'position' => 'Head of Department',
            'department_id' => $department->id,
            'employment_status' => 'terminated',
            'termination_date' => '2026-10-01',
            'termination_reason' => 'Resigned with notice.',
            'basic_salary' => 95000,
        ])
        ->assertOk()
        ->assertJsonPath('staff.name', 'Jane Ann Revised')
        ->assertJsonPath('staff.position', 'Head of Department')
        ->assertJsonPath('staff.department_name', $department->name)
        ->assertJsonPath('staff.employment_status', 'terminated')
        ->assertJsonPath('staff.termination_date', '2026-10-01')
        ->assertJsonPath('staff.termination_reason', 'Resigned with notice.')
        ->assertJsonPath('staff.basic_salary', '95000.00')
        ->assertJsonPath('staff.created_by', $creator->id)
        ->assertJsonPath('staff.updated_by', $updater->id);

    $this->assertDatabaseHas('staff', [
        'id' => $staffMember->id,
        'name' => 'Jane Ann Revised',
        'employment_status' => 'terminated',
        'created_by' => $creator->id,
        'updated_by' => $updater->id,
    ]);
});

test('updating staff syncs the linked account', function () {
    $admin = User::factory()->create();

    $created = $this->withToken(staffAccessTokenFor($admin))
        ->postJson('/api/staff', [
            'employment_no' => 'EMP-0001',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'role' => 'trainer',
        ])
        ->assertCreated();

    $staffId = $created->json('staff.id');
    $userId = $created->json('staff.user_id');

    $this->withToken(staffAccessTokenFor($admin))
        ->putJson('/api/staff/'.$staffId, [
            'employment_no' => 'EMP-0009',
            'first_name' => 'Jane',
            'middle_name' => 'Ann',
            'last_name' => 'Revised',
            'role' => 'admin',
            'employment_status' => 'active',
        ])
        ->assertOk()
        ->assertJsonPath('staff.name', 'Jane Ann Revised')
        ->assertJsonPath('staff.role', 'admin');

    $this->assertDatabaseHas('users', [
        'id' => $userId,
        'login_id' => 'EMP-0009',
        'name' => 'Jane Ann Revised',
        'first_name' => 'Jane',
        'middle_name' => 'Ann',
        'last_name' => 'Revised',
        'role' => 'admin',
        'email' => 'emp-0009@staff.invalid',
    ]);
});

test('updating staff validates the employment number excluding itself', function () {
    $staffMember = Staff::factory()->create(['employment_no' => 'EMP-0001']);
    Staff::factory()->create(['employment_no' => 'EMP-0002']);

    $this->withToken(staffAccessTokenFor(User::factory()->create()))
        ->putJson('/api/staff/'.$staffMember->id, [
            'employment_no' => 'EMP-0002',
            'first_name' => 'Stolen',
            'last_name' => 'Number',
            'role' => 'trainer',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employment_no']);

    $this->withToken(staffAccessTokenFor(User::factory()->create()))
        ->putJson('/api/staff/'.$staffMember->id, [
            'employment_no' => 'EMP-0001',
            'first_name' => 'Kept',
            'last_name' => 'Number',
            'role' => 'trainer',
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

test('deleting a staff member also removes their account', function () {
    $admin = User::factory()->create();

    $created = $this->withToken(staffAccessTokenFor($admin))
        ->postJson('/api/staff', [
            'employment_no' => 'EMP-0001',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'role' => 'trainer',
        ])
        ->assertCreated();

    $userId = $created->json('staff.user_id');

    $this->withToken(staffAccessTokenFor($admin))
        ->deleteJson('/api/staff/'.$created->json('staff.id'))
        ->assertNoContent();

    $this->assertSoftDeleted('staff', ['id' => $created->json('staff.id')]);
    $this->assertSoftDeleted('users', ['id' => $userId]);

    $this->postJson('/api/login', [
        'email' => 'EMP-0001',
        'password' => $created->json('temporary_password'),
    ])->assertUnprocessable();
});

test('rehiring the same employment number restores the account', function () {
    $admin = User::factory()->create();

    $first = $this->withToken(staffAccessTokenFor($admin))
        ->postJson('/api/staff', [
            'employment_no' => 'EMP-0001',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'role' => 'trainer',
        ])
        ->assertCreated();

    $userId = $first->json('staff.user_id');

    $this->withToken(staffAccessTokenFor($admin))
        ->deleteJson('/api/staff/'.$first->json('staff.id'))
        ->assertNoContent();

    $second = $this->withToken(staffAccessTokenFor($admin))
        ->postJson('/api/staff', [
            'employment_no' => 'EMP-0001',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'role' => 'trainer',
        ])
        ->assertCreated();

    expect($second->json('staff.user_id'))->toBe($userId);

    $this->assertDatabaseCount('users', 1 + 1); // admin + the restored account

    $user = User::withTrashed()->findOrFail($userId);

    expect($user->trashed())->toBeFalse();
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
