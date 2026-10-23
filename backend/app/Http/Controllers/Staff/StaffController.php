<?php

namespace App\Http\Controllers\Staff;

use App\Enums\Role;
use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreStaffRequest;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Http\Resources\Staff\StaffResource;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    use PaginatesLists;

    /**
     * The identity keys of the staff payload: stored on the user account.
     *
     * @var list<string>
     */
    private const IDENTITY_KEYS = [
        'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth',
        'nationality', 'county', 'place_of_birth', 'religion', 'address',
        'city', 'postal_code', 'profile_picture', 'alternative_phone',
        'is_pwd', 'disability_type', 'disability_description',
    ];

    /**
     * The employment keys of the staff payload: stored on the staff row.
     *
     * @var list<string>
     */
    private const EMPLOYMENT_KEYS = [
        'employment_no', 'work_email', 'work_phone', 'national_id',
        'kra_pin', 'nhif_number', 'nssf_number', 'position', 'department_id',
        'employment_type', 'employment_status', 'role', 'date_joined',
        'confirmation_date', 'contract_end_date', 'termination_date',
        'termination_reason', 'basic_salary', 'highest_qualification',
        'specialization',
    ];

    /**
     * List staff members ordered by employment number, with the shared
     * search / sort / pagination contract and both relations loaded.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'employment_status' => ['nullable', 'string', 'max:50'],
            'employment_type' => ['nullable', 'string', 'max:50'],
            'role' => ['nullable', 'string', 'max:50', Rule::exists('roles', 'name')],
        ]);

        $paginator = $this->paginateList(
            $request,
            Staff::query()->with(['department', 'user']),
            searchable: ['name', 'employment_no'],
            sortable: [
                'name' => 'name',
                'employment_no' => 'employment_no',
                'employment_status' => 'employment_status',
                'date_joined' => 'date_joined',
                'created_at' => 'created_at',
            ],
            defaultOrder: [['employment_no', 'asc']],
            applyFilters: function (Builder $query) use ($filters): void {
                $query
                    ->when($filters['department_id'] ?? null, fn ($query, $departmentId) => $query->where('department_id', $departmentId))
                    ->when($filters['employment_status'] ?? null, fn ($query, $status) => $query->where('employment_status', $status))
                    ->when($filters['employment_type'] ?? null, fn ($query, $type) => $query->where('employment_type', $type))
                    ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role));
            },
        );

        return response()->json([
            'staff' => StaffResource::collection($paginator->items()),
            'meta' => $this->listMeta($paginator),
        ]);
    }

    /**
     * Create a staff profile and provision its auth account, returning
     * the one-time temporary password for the new login.
     */
    public function store(StoreStaffRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $name = $this->composeName($validated);

        [$user, $temporaryPassword] = $this->provisionAccount($validated, $name);

        $staffMember = Staff::create([
            ...$this->staffColumns($validated),
            'name' => $name,
            'user_id' => $user->id,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'staff' => new StaffResource($staffMember->load(['department', 'user'])),
            'temporary_password' => $temporaryPassword,
        ], Response::HTTP_CREATED);
    }

    /**
     * Show a single staff member.
     */
    public function show(Staff $staff): JsonResponse
    {
        return response()->json([
            'staff' => new StaffResource($staff->load(['department', 'user'])),
        ]);
    }

    /**
     * Update a staff profile, keeping the linked auth account in sync.
     */
    public function update(UpdateStaffRequest $request, Staff $staff): JsonResponse
    {
        $validated = $request->validated();
        $staff->load('user');
        $name = $this->composeName($validated, $staff->user);

        $staff->update([
            ...$this->staffColumns($validated),
            'name' => $name,
            'updated_by' => $request->user()->id,
        ]);

        $temporaryPassword = $this->syncAccount($staff->refresh(), $validated, $name);

        return response()->json([
            'staff' => new StaffResource($staff->refresh()->load(['department', 'user'])),
            'temporary_password' => $temporaryPassword,
        ]);
    }

    /**
     * Soft delete a staff member together with their auth account. The
     * user link is severed first so a rehired employment number can
     * reuse the restored account without clashing on the unique link.
     */
    public function destroy(Staff $staff): Response
    {
        $user = $staff->load('user')->user;

        $staff->update(['user_id' => null]);
        $staff->delete();
        $user?->delete();

        return response()->noContent();
    }

    /**
     * Compose the display name from the split name parts, falling back
     * to the linked user's stored parts on partial updates.
     */
    private function composeName(array $validated, ?User $user = null): string
    {
        return User::composeName(
            $validated['first_name'] ?? $user?->first_name,
            $validated['middle_name'] ?? $user?->middle_name,
            $validated['last_name'] ?? $user?->last_name,
        );
    }

    /**
     * The employment columns of the payload.
     *
     * @return array<string, mixed>
     */
    private function staffColumns(array $validated): array
    {
        return array_intersect_key($validated, array_flip(self::EMPLOYMENT_KEYS));
    }

    /**
     * The identity columns of the payload.
     *
     * @return array<string, mixed>
     */
    private function identityColumns(array $validated): array
    {
        return array_intersect_key($validated, array_flip(self::IDENTITY_KEYS));
    }

    /**
     * Create (or restore, on rehire) the auth account for a staff
     * member. The employment number doubles as the login id and the
     * temporary password is returned exactly once.
     *
     * @return array{0: User, 1: string}
     */
    private function provisionAccount(array $validated, string $name): array
    {
        $temporaryPassword = Str::password(16);

        $user = User::withTrashed()->firstOrNew(['login_id' => $validated['employment_no']]);

        $this->saveUserAccount($user, [
            ...$this->identityColumns($validated),
            'name' => $name,
            'email' => $this->accountEmail($validated),
            'must_reset_password' => true,
            'password' => $temporaryPassword,
        ]);

        $user->restore();

        // The auth role rides along with the staff profile's role,
        // replacing any previous one the way the old column overwrite
        // did.
        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        return [$user, $temporaryPassword];
    }

    /**
     * Sync the linked auth account after a staff update. A legacy staff
     * row without an account is provisioned on the spot, returning its
     * one-time temporary password.
     */
    private function syncAccount(Staff $staff, array $validated, string $name): ?string
    {
        $staff->loadMissing('user');

        $user = $staff->user
            ?? User::withTrashed()->where('login_id', $validated['employment_no'] ?? $staff->employment_no)->first();

        if (! $user) {
            [$user, $temporaryPassword] = $this->provisionAccount(
                [...$validated, 'role' => $validated['role'] ?? $staff->role],
                $name,
            );

            $staff->update(['user_id' => $user->id]);

            return $temporaryPassword;
        }

        $this->saveUserAccount($user, [
            ...$this->identityColumns($validated),
            'name' => $name,
            'email' => $this->accountEmail($validated, $staff),
            'login_id' => $validated['employment_no'] ?? $staff->employment_no,
        ]);

        $user->restore();

        // The auth role rides along with the staff profile's role,
        // replacing any previous one the way the old column overwrite
        // did.
        $user->syncRoles([$validated['role'] ?? $staff->role]);

        return null;
    }

    /**
     * The user account email: the work email when provided, otherwise a
     * guaranteed-unique non-routable placeholder keyed to the number.
     */
    private function accountEmail(array $validated, ?Staff $staff = null): string
    {
        return $validated['work_email']
            ?? $staff?->work_email
            ?? Str::lower($validated['employment_no'] ?? $staff->employment_no).'@staff.invalid';
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
                'work_email' => 'That email address is already used by another account.',
            ]);
        }
    }
}
