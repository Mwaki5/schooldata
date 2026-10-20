<?php

use App\Models\AcademicSession;
use App\Models\TermEvent;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

function termEventsAccessTokenFor(User $user): string
{
    JWTAuth::factory()->setTTL((int) config('jwt.ttl'));

    return JWTAuth::claims(['type' => 'access'])->fromUser($user);
}

test('term event endpoints require an access token', function () {
    $this->getJson('/api/term-events')->assertUnauthorized();
    $this->postJson('/api/term-events', ['name' => 'Graduation'])
        ->assertUnauthorized();
});

test('term events are listed newest start date first with their session name', function () {
    $session = AcademicSession::factory()->create(['name' => 'Term 1, 2026/2027']);

    TermEvent::factory()->create([
        'academic_session_id' => $session->id,
        'name' => 'Session Registration',
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-05',
    ]);
    TermEvent::factory()->create([
        'academic_session_id' => $session->id,
        'name' => 'Midterm Exam',
        'start_date' => '2026-11-02',
        'end_date' => '2026-11-13',
    ]);

    $this->withToken(termEventsAccessTokenFor(User::factory()->create()))
        ->getJson('/api/term-events')
        ->assertOk()
        ->assertJsonStructure([
            'term_events' => [[
                'id', 'academic_session_id', 'academic_session_name', 'name',
                'start_date', 'end_date', 'is_active', 'created_by', 'updated_by',
            ]],
            'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
        ])
        ->assertJsonPath('term_events.0.name', 'Midterm Exam')
        ->assertJsonPath('term_events.0.academic_session_name', 'Term 1, 2026/2027')
        ->assertJsonPath('term_events.1.name', 'Session Registration')
        ->assertJsonPath('meta.total', 2);
});

test('the term event list supports session and status filters, search, sorting and pagination', function () {
    $firstTerm = AcademicSession::factory()->create();
    $secondTerm = AcademicSession::factory()->create();

    TermEvent::factory()->create([
        'academic_session_id' => $firstTerm->id,
        'name' => 'Session Registration',
        'start_date' => '2026-09-01',
        'is_active' => true,
    ]);
    TermEvent::factory()->create([
        'academic_session_id' => $firstTerm->id,
        'name' => 'Graduation',
        'start_date' => '2026-12-10',
        'is_active' => false,
    ]);
    TermEvent::factory()->create([
        'academic_session_id' => $secondTerm->id,
        'name' => 'School Break',
        'start_date' => '2027-01-04',
        'is_active' => true,
    ]);

    $token = termEventsAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->getJson('/api/term-events?academic_session_id='.$firstTerm->id)
        ->assertOk()
        ->assertJsonCount(2, 'term_events')
        ->assertJsonPath('term_events.0.name', 'Graduation');

    $this->withToken($token)
        ->getJson('/api/term-events?is_active=1')
        ->assertOk()
        ->assertJsonCount(2, 'term_events');

    $this->withToken($token)
        ->getJson('/api/term-events?search=break')
        ->assertOk()
        ->assertJsonCount(1, 'term_events')
        ->assertJsonPath('term_events.0.name', 'School Break');

    $sorted = $this->withToken($token)
        ->getJson('/api/term-events?sort=name&order=asc')
        ->assertOk();

    expect(collect($sorted->json('term_events'))->pluck('name')->all())
        ->toBe(['Graduation', 'School Break', 'Session Registration']);

    $this->withToken($token)
        ->getJson('/api/term-events?per_page=2&page=2')
        ->assertOk()
        ->assertJsonCount(1, 'term_events')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.total', 3);
});

test('a term event can be created with audit stamps', function () {
    $creator = User::factory()->create();
    $session = AcademicSession::factory()->create(['name' => 'Term 2, 2026/2027']);

    $response = $this->withToken(termEventsAccessTokenFor($creator))
        ->postJson('/api/term-events', [
            'academic_session_id' => $session->id,
            'name' => 'End Term Exams',
            'start_date' => '2027-04-05',
            'end_date' => '2027-04-09',
            'is_active' => true,
        ]);

    $response->assertCreated()
        ->assertJsonPath('term_event.academic_session_id', $session->id)
        ->assertJsonPath('term_event.academic_session_name', 'Term 2, 2026/2027')
        ->assertJsonPath('term_event.name', 'End Term Exams')
        ->assertJsonPath('term_event.start_date', '2027-04-05')
        ->assertJsonPath('term_event.end_date', '2027-04-09')
        ->assertJsonPath('term_event.is_active', true)
        ->assertJsonPath('term_event.created_by', $creator->id)
        ->assertJsonPath('term_event.updated_by', $creator->id);

    $this->assertDatabaseHas('term_events', [
        'academic_session_id' => $session->id,
        'name' => 'End Term Exams',
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ]);
});

test('a term event defaults to inactive', function () {
    $user = User::factory()->create();
    $session = AcademicSession::factory()->create();

    $this->withToken(termEventsAccessTokenFor($user))
        ->postJson('/api/term-events', [
            'academic_session_id' => $session->id,
            'name' => 'Graduation',
            'start_date' => '2027-04-08',
            'end_date' => '2027-04-08',
        ])
        ->assertCreated()
        ->assertJsonPath('term_event.is_active', false);
});

test('term events only accept the supported vocabulary and valid dates', function () {
    $user = User::factory()->create();
    $session = AcademicSession::factory()->create();

    $this->withToken(termEventsAccessTokenFor($user))
        ->postJson('/api/term-events', [
            'academic_session_id' => $session->id,
            'name' => 'Sports Day',
            'start_date' => '2027-01-10',
            'end_date' => '2027-01-09',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'end_date']);
});

test('a session carries at most one date range per event', function () {
    $user = User::factory()->create();
    $session = AcademicSession::factory()->create();
    $otherSession = AcademicSession::factory()->create();

    TermEvent::factory()->create([
        'academic_session_id' => $session->id,
        'name' => 'Midterm Exam',
    ]);

    // Repeating the event in the same session collides...
    $this->withToken(termEventsAccessTokenFor($user))
        ->postJson('/api/term-events', [
            'academic_session_id' => $session->id,
            'name' => 'Midterm Exam',
            'start_date' => '2027-02-01',
            'end_date' => '2027-02-05',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    // ...but the same event is fine in another term.
    $this->withToken(termEventsAccessTokenFor($user))
        ->postJson('/api/term-events', [
            'academic_session_id' => $otherSession->id,
            'name' => 'Midterm Exam',
            'start_date' => '2027-02-01',
            'end_date' => '2027-02-05',
        ])
        ->assertCreated();
});

test('a single term event can be shown and missing ones return not found', function () {
    $termEvent = TermEvent::factory()->create(['name' => 'Graduation']);
    $token = termEventsAccessTokenFor(User::factory()->create());

    $this->withToken($token)
        ->getJson('/api/term-events/'.$termEvent->id)
        ->assertOk()
        ->assertJsonPath('term_event.id', $termEvent->id)
        ->assertJsonPath('term_event.name', 'Graduation');

    $this->withToken($token)
        ->getJson('/api/term-events/999999')
        ->assertNotFound();
});

test('a term event can be updated without tripping its own uniqueness rule', function () {
    $editor = User::factory()->create();
    $session = AcademicSession::factory()->create(['name' => 'Term 1, 2026/2027']);
    $termEvent = TermEvent::factory()->create([
        'academic_session_id' => $session->id,
        'name' => 'Session Registration',
        'created_by' => $editor->id,
    ]);

    $this->withToken(termEventsAccessTokenFor($actor = User::factory()->create()))
        ->putJson('/api/term-events/'.$termEvent->id, [
            'academic_session_id' => $session->id,
            'name' => 'Session Registration',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-07',
            'is_active' => true,
        ])
        ->assertOk()
        ->assertJsonPath('term_event.end_date', '2026-09-07')
        ->assertJsonPath('term_event.is_active', true)
        ->assertJsonPath('term_event.updated_by', $actor->id);

    $this->assertDatabaseHas('term_events', [
        'id' => $termEvent->id,
        'is_active' => true,
        'updated_by' => $actor->id,
    ]);
});

test('a term event can be deleted', function () {
    $termEvent = TermEvent::factory()->create();

    $this->withToken(termEventsAccessTokenFor(User::factory()->create()))
        ->deleteJson('/api/term-events/'.$termEvent->id)
        ->assertNoContent();

    $this->assertDatabaseMissing('term_events', ['id' => $termEvent->id]);
});
