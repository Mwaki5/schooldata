<?php

namespace App\Http\Controllers\Me;

use App\Enums\EnrolmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Enrolments\EnrolmentResource;
use App\Http\Resources\Students\StudentResource;
use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\CourseEnrolment;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * The relations the enrolment payloads render from, mirroring the
     * admin enrolment list.
     *
     * @var list<string>
     */
    private const LOADS = ['student.user', 'course', 'curriculum', 'academicSession', 'certificationLevel'];

    /**
     * Everything the signed-in student's dashboard shows, scoped strictly
     * to the requesting account: their profile, the running academic year
     * and term, enrolment totals and the enrolment rows themselves.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        // Queried through the relation (with `user` eager loaded for the
        // resource) rather than as a magic property, so the app-wide
        // lazy-loading prevention never trips on the auth user.
        $profile = $user->studentProfile()->with('user')->first();

        $enrolments = $profile === null
            ? collect()
            : CourseEnrolment::query()
                ->where('student_id', $profile->id)
                ->with(self::LOADS)
                ->orderByDesc('enrolment_date')
                ->orderByDesc('id')
                ->get();

        $courseIds = $enrolments->pluck('course_id')->unique()->values();

        return response()->json([
            'profile' => $profile === null ? null : new StudentResource($profile),
            'academic' => [
                'year' => AcademicYear::query()
                    ->where('is_active', true)
                    ->latest('start_date')
                    ->first(['id', 'name', 'start_date', 'end_date', 'is_active']),
                'session' => AcademicSession::query()
                    ->where('is_active', true)
                    ->latest('start_date')
                    ->first(['id', 'name', 'start_date', 'end_date', 'is_active']),
            ],
            'stats' => [
                'enrolments' => $enrolments->count(),
                'active' => $enrolments->where('status', EnrolmentStatus::Enrolled)->count(),
                'completed' => $enrolments->where('status', EnrolmentStatus::Completed)->count(),
                'courses' => $courseIds->count(),
                'units' => $courseIds->isEmpty()
                    ? 0
                    : Unit::query()->whereIn('course_id', $courseIds)->count(),
            ],
            'enrolments' => EnrolmentResource::collection($enrolments),
        ]);
    }
}
