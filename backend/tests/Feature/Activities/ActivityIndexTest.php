<?php

use App\Models\Department;
use App\Models\ExamBody;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function activityIndexAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('admins can list the activity log', function () {
    $admin = User::factory()->create();
    $department = Department::factory()->create();

    $response = $this->withToken(activityIndexAccessTokenFor($admin))
        ->getJson('/api/activities');

    $response->assertOk()
        ->assertJsonStructure([
            'activities' => [[
                'id',
                'log_name',
                'event',
                'description',
                'causer_id',
                'causer_name',
                'causer_email',
                'subject_type',
                'subject_id',
                'properties',
                'created_at',
            ]],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            'filters' => ['events', 'subject_types'],
        ]);

    expect($response->json('activities.0.event'))->toBe('created')
        ->and($response->json('activities.0.subject_type'))->toBe(Department::class)
        ->and($response->json('activities.0.subject_id'))->toBe($department->id)
        ->and($response->json('activities.0.causer_name'))->toBeNull();
});

test('the activity log shows the user who caused each entry', function () {
    $admin = User::factory()->create(['name' => 'Audit Author']);

    activity()->causedBy($admin)->event('review')->log('Reviewed records');

    $response = $this->withToken(activityIndexAccessTokenFor($admin))
        ->getJson('/api/activities');

    $response->assertOk();

    expect($response->json('activities.0.causer_name'))->toBe('Audit Author')
        ->and($response->json('activities.0.causer_email'))->toBe($admin->email)
        ->and($response->json('activities.0.description'))->toBe('Reviewed records');
});

test('non-admin roles cannot view the activity log', function (string $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->withToken(activityIndexAccessTokenFor($user))
        ->getJson('/api/activities')
        ->assertForbidden();
})->with(['trainer', 'student', 'finance']);

test('the activity log requires an access token', function () {
    $this->getJson('/api/activities')->assertUnauthorized();
});

test('the activity log paginates newest first with a bounded page size', function () {
    $admin = User::factory()->create();

    Department::factory()->count(16)->create();

    $token = activityIndexAccessTokenFor($admin);

    $firstPage = $this->withToken($token)->getJson('/api/activities');

    $firstPage->assertOk()
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonPath('meta.total', 16)
        ->assertJsonPath('meta.last_page', 2);

    expect($firstPage->json('activities'))->toHaveCount(15);

    $ids = $firstPage->json('activities.*.id');
    expect($ids)->toBe(collect($ids)->sortDesc()->all());

    $secondPage = $this->withToken($token)->getJson('/api/activities?page=2');

    $secondPage->assertOk()->assertJsonPath('meta.current_page', 2);
    expect($secondPage->json('activities'))->toHaveCount(1);

    $this->withToken($token)->getJson('/api/activities?per_page=500')->assertUnprocessable();
    $this->withToken($token)->getJson('/api/activities?per_page=2')->assertUnprocessable();
});

test('the activity log can be filtered by event', function () {
    $admin = User::factory()->create();

    Department::factory()->create();
    activity()->event('review')->log('Reviewed records');

    $response = $this->withToken(activityIndexAccessTokenFor($admin))
        ->getJson('/api/activities?event=review');

    $response->assertOk();

    expect($response->json('activities'))->toHaveCount(1)
        ->and($response->json('activities.0.event'))->toBe('review');
});

test('the activity log can be filtered by subject type', function () {
    $admin = User::factory()->create();

    Department::factory()->create();
    ExamBody::factory()->create();

    $response = $this->withToken(activityIndexAccessTokenFor($admin))
        ->getJson('/api/activities?subject_type='.urlencode(Department::class));

    $response->assertOk();

    expect($response->json('activities'))->toHaveCount(1)
        ->and($response->json('activities.0.subject_type'))->toBe(Department::class);
});

test('the activity log lists the available filter options', function () {
    $admin = User::factory()->create();

    Department::factory()->create();
    activity('auth')->event('login')->log('Logged in');

    $response = $this->withToken(activityIndexAccessTokenFor($admin))
        ->getJson('/api/activities');

    $response->assertOk();

    expect($response->json('filters.events'))->toContain('created', 'login')
        ->and($response->json('filters.subject_types'))->toContain(Department::class);
});
