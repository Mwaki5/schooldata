<?php

namespace App\Http\Controllers\Students;

use App\Enums\Role;
use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\StoreStudentRequest;
use App\Http\Requests\Students\UpdateStudentRequest;
use App\Http\Resources\Students\StudentResource;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentController extends Controller
{
    use PaginatesLists;

    /**
     * The identity keys of the student payload: stored on the profile row.
     *
     * @var list<string>
     */
    private const PROFILE_KEYS = [
        'national_id', 'status', 'first_name', 'middle_name', 'last_name',
        'phone', 'gender', 'date_of_birth', 'county',
        'place_of_residence', 'religion', 'address',
        'profile_picture', 'alternative_phone', 'is_pwd', 'disability_type',
        'disability_description', 'sponsored', 'sponsored_by', 'index_no',
        'exam_year', 'previous_school',
    ];

    /**
     * The relations every student payload renders from: the linked
     * account plus the current enrolment with its display relations.
     *
     * @var list<string>
     */
    private const LOADS = [
        'user',
        'latestEnrolment.course',
        'latestEnrolment.curriculum',
        'latestEnrolment.academicSession',
        'latestEnrolment.certificationLevel',
    ];

    /**
     * List students ordered by name, with the shared search / sort /
     * pagination contract and the linked account loaded.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'string', 'max:10'],
        ]);

        $paginator = $this->paginateList(
            $request,
            StudentProfile::query()->with(self::LOADS),
            // Search spans the profile identity and the linked account's
            // login id / email, so the whole clause is composed below.
            searchable: [],
            sortable: [
                'name' => 'first_name',
                'status' => 'status',
                'gender' => 'gender',
                'date_of_birth' => 'date_of_birth',
                'created_at' => 'created_at',
            ],
            defaultOrder: [['first_name', 'asc'], ['last_name', 'asc']],
            applyFilters: function (Builder $query) use ($filters, $request): void {
                $search = trim((string) $request->query('search', ''));

                if ($search !== '') {
                    $like = '%'.addcslashes($search, '%_\\').'%';

                    $query->where(function (Builder $query) use ($like): void {
                        $query->where('first_name', 'like', $like)
                            ->orWhere('middle_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('national_id', 'like', $like)
                            ->orWhere('phone', 'like', $like)
                            ->orWhereHas('user', function (Builder $query) use ($like): void {
                                $query->where('login_id', 'like', $like)
                                    ->orWhere('email', 'like', $like);
                            });
                    });
                }

                $query
                    ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                    ->when($filters['gender'] ?? null, fn ($query, $gender) => $query->where('gender', $gender));
            },
        );

        return response()->json([
            'students' => StudentResource::collection($paginator->items()),
            'meta' => $this->listMeta($paginator),
        ]);
    }

    /**
     * Register a student and enrol them onto their course in one
     * transaction: the auth account, profile row and enrolment either all
     * persist or none do. Returns the one-time temporary password.
     */
    public function store(StoreStudentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $name = $this->composeName($validated);

        [$student, $temporaryPassword] = DB::transaction(function () use ($validated, $request, $name): array {
            [$user, $temporaryPassword] = $this->provisionAccount($validated, $name);

            $student = StudentProfile::create([
                ...array_intersect_key($validated, array_flip(self::PROFILE_KEYS)),
                'status' => $validated['status'] ?? 'active',
                'user_id' => $user->id,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $this->createEnrolment($student, $validated['enrolment'], $request->user()->id);

            return [$student, $temporaryPassword];
        });

        return response()->json([
            'student' => new StudentResource($student->load(self::LOADS)),
            'temporary_password' => $temporaryPassword,
        ], Response::HTTP_CREATED);
    }

    /**
     * Show a single student with their current enrolment.
     */
    public function show(StudentProfile $student): JsonResponse
    {
        return response()->json([
            'student' => new StudentResource($student->load(self::LOADS)),
        ]);
    }

    /**
     * Update a student profile and their enrolment together, keeping the
     * linked auth account in sync. All writes share one transaction; a
     * student without an enrolment gains their first one.
     */
    public function update(UpdateStudentRequest $request, StudentProfile $student): JsonResponse
    {
        $validated = $request->validated();
        $student->load('user');
        $name = $this->composeName($validated, $student);

        DB::transaction(function () use ($validated, $request, $student, $name): void {
            $student->update([
                ...array_intersect_key($validated, array_flip(self::PROFILE_KEYS)),
                'status' => $validated['status'] ?? $student->status,
                'updated_by' => $request->user()->id,
            ]);

            $this->syncAccount($student->refresh(), $validated, $name);
            $this->updateEnrolment($student, $validated['enrolment'], $request->user()->id);
        });

        return response()->json([
            'student' => new StudentResource($student->refresh()->load(self::LOADS)),
        ]);
    }

    /**
     * Soft delete a student together with their enrolments and auth
     * account. The user link is severed first so a re-admitted student
     * can reuse the restored account without clashing on the unique link.
     */
    public function destroy(StudentProfile $student): Response
    {
        $student->loadMissing('user');
        $user = $student->user;

        $student->enrolments()->delete();
        $student->update(['user_id' => null]);
        $student->delete();
        $user?->delete();

        return response()->noContent();
    }

    /**
     * Compose the display name from the split name parts, falling back
     * to the profile's stored parts on partial updates.
     */
    private function composeName(array $validated, ?StudentProfile $student = null): string
    {
        return User::composeName(
            $validated['first_name'] ?? $student?->first_name,
            $validated['middle_name'] ?? $student?->middle_name,
            $validated['last_name'] ?? $student?->last_name,
        );
    }

    /**
     * Create (or restore, on re-admission) the auth account for a
     * student. The admission number doubles as the login id and the
     * temporary password is returned exactly once.
     *
     * @return array{0: User, 1: string}
     */
    private function provisionAccount(array $validated, string $name): array
    {
        $temporaryPassword = Str::password(16);

        $user = User::withTrashed()->firstOrNew(['login_id' => $validated['admission_no']]);

        $this->saveUserAccount($user, [
            'name' => $name,
            'email' => $validated['email'] ?? $user->email ?? $this->fallbackEmail($validated['admission_no']),
            'login_id' => $validated['admission_no'],
            'role' => Role::Student,
            'status' => $validated['status'] ?? 'active',
            'must_reset_password' => true,
            'password' => $temporaryPassword,
        ]);

        $user->restore();

        return [$user, $temporaryPassword];
    }

    /**
     * Sync the linked auth account after a student update: display name,
     * login id, status and (when supplied) the email move with the
     * profile; a blank email keeps the current address.
     */
    private function syncAccount(StudentProfile $student, array $validated, string $name): void
    {
        $user = $student->user
            ?? User::withTrashed()->where('login_id', $validated['admission_no'])->first();

        if (! $user) {
            [$user] = $this->provisionAccount($validated, $name);

            $student->update(['user_id' => $user->id]);

            return;
        }

        $this->saveUserAccount($user, [
            'name' => $name,
            'email' => $validated['email'] ?? $user->email,
            'login_id' => $validated['admission_no'],
            'role' => Role::Student,
            'status' => $validated['status'] ?? $student->status,
        ]);

        $user->restore();
    }

    /**
     * Create the first enrolment for a freshly registered student from
     * the nested enrolment segment of the payload.
     */
    private function createEnrolment(StudentProfile $student, array $enrolment, int $userId): void
    {
        $student->enrolments()->create([
            ...$enrolment,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    /**
     * Move the enrolment segment of an update onto the student's current
     * enrolment, creating the first one when the student had none.
     */
    private function updateEnrolment(StudentProfile $student, array $enrolment, int $userId): void
    {
        $current = $student->enrolments()->latest('id')->first();

        if (! $current) {
            $this->createEnrolment($student, $enrolment, $userId);

            return;
        }

        $current->update([
            ...$enrolment,
            'updated_by' => $userId,
        ]);
    }

    /**
     * The account email when the admin leaves the field blank: a
     * guaranteed-unique non-routable placeholder keyed to the number.
     */
    private function fallbackEmail(string $admissionNo): string
    {
        return Str::lower($admissionNo).'@student.invalid';
    }

    /**
     * Save the user account, translating an email uniqueness clash into
     * a validation error instead of a server error.
     */
    private function saveUserAccount(User $user, array $attributes): void
    {
        try {
            $user->forceFill($attributes)->save();
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'email' => 'That email address is already used by another account.',
            ]);
        }
    }
}
