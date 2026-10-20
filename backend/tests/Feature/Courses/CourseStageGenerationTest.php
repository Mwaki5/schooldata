<?php

use App\Models\Course;
use App\Models\CourseStage;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

test('creating a course generates three stages for every academic year', function () {
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    $token = JWTAuth::claims(['type' => 'access'])->fromUser(User::factory()->create());

    $response = $this->withToken($token)->postJson('/api/courses', [
        'code' => 'DIT',
        'initials' => 'DIT',
        'name' => 'Diploma in Information Technology',
        'total_modules' => 6,
        'is_active' => true,
    ]);

    $response->assertCreated();

    $codes = CourseStage::query()
        ->where('course_id', $response->json('course.id'))
        ->orderBy('module_no')
        ->pluck('code')
        ->all();

    expect($codes)->toBe(['Y1S1', 'Y1S2', 'Y1S3', 'Y2S1', 'Y2S2', 'Y2S3']);
});

test('the backfill command fills existing courses without duplicating stages', function () {
    $course = Course::factory()->create(['total_modules' => 3]);
    $course->stages()->create([
        'module_no' => 1,
        'year_no' => 1,
        'session_no' => 1,
        'code' => 'Y1S1',
    ]);

    $this->artisan('course-stages:backfill')->assertSuccessful();

    $codes = $course->stages()->orderBy('module_no')->pluck('code')->all();

    expect($codes)->toBe(['Y1S1', 'Y1S2', 'Y1S3']);
});
