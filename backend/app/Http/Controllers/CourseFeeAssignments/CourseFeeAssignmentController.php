<?php

namespace App\Http\Controllers\CourseFeeAssignments;

use App\Http\Controllers\Concerns\PaginatesLists;
use App\Http\Controllers\Controller;
use App\Http\Requests\CourseFeeAssignments\StoreCourseFeeAssignmentRequest;
use App\Http\Requests\CourseFeeAssignments\UpdateCourseFeeAssignmentRequest;
use App\Http\Resources\CourseFeeAssignments\CourseFeeAssignmentResource;
use App\Models\CourseFeeAssignment;
use App\Models\FeeStructure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CourseFeeAssignmentController extends Controller
{
    use PaginatesLists;

    /**
     * The fee assignments, ordered by course then curriculum, with the
     * shared search / sort / pagination contract and their course,
     * curriculum and fee structure eagerly loaded. The search matches
     * the columns the list shows — the course's code or name, the
     * curriculum, or the fee structure — which live on related tables,
     * so it is applied before the shared pipeline. Each payload carries
     * the fee amount: the summed item total of its structure.
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $query = CourseFeeAssignment::query()->with([
            'course.certificationLevel',
            'course.department',
            'curriculum.examBody',
            'feeStructure.items:id,fee_structure_id,amount',
        ]);

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';

            $query->where(function (Builder $query) use ($like): void {
                $query->whereHas('course', function (Builder $query) use ($like): void {
                    $query->where('code', 'like', $like)
                        ->orWhere('name', 'like', $like);
                })
                    ->orWhereHas('curriculum', fn (Builder $query) => $query->where('name', 'like', $like))
                    ->orWhereHas('feeStructure', fn (Builder $query) => $query->where('name', 'like', $like));
            });
        }

        $paginator = $this->paginateList(
            $request,
            $query,
            searchable: [],
            sortable: [
                'span' => 'span',
                'created_at' => 'created_at',
            ],
            defaultOrder: [['course_id', 'asc'], ['curriculum_id', 'asc']],
        );

        $this->attachStructureTotals($paginator->items());

        return response()->json([
            'course_fee_assignments' => CourseFeeAssignmentResource::collection($paginator->items()),
            'meta' => $this->listMeta($paginator),
        ]);
    }

    /**
     * Create an assignment, stamping the creating user.
     */
    public function store(StoreCourseFeeAssignmentRequest $request): JsonResponse
    {
        $assignment = CourseFeeAssignment::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'course_fee_assignment' => new CourseFeeAssignmentResource(
                $this->loadRelations($assignment),
            ),
        ], Response::HTTP_CREATED);
    }

    /**
     * Show a single assignment with its course, curriculum and fee
     * structure.
     */
    public function show(CourseFeeAssignment $courseFeeAssignment): JsonResponse
    {
        return response()->json([
            'course_fee_assignment' => new CourseFeeAssignmentResource(
                $this->loadRelations($courseFeeAssignment),
            ),
        ]);
    }

    /**
     * Update an assignment, stamping the updating user.
     */
    public function update(UpdateCourseFeeAssignmentRequest $request, CourseFeeAssignment $courseFeeAssignment): JsonResponse
    {
        $courseFeeAssignment->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'course_fee_assignment' => new CourseFeeAssignmentResource(
                $this->loadRelations($courseFeeAssignment->refresh()),
            ),
        ]);
    }

    /**
     * Soft delete an assignment, freeing its pair for a new one.
     */
    public function destroy(CourseFeeAssignment $courseFeeAssignment): Response
    {
        $courseFeeAssignment->delete();

        return response()->noContent();
    }

    /**
     * The eager-loaded relations every payload carries, with the fee
     * amount attached: the summed item total of the structure, computed
     * from the loaded items so no per-row query runs.
     */
    private function loadRelations(CourseFeeAssignment $assignment): CourseFeeAssignment
    {
        $assignment->load([
            'course.certificationLevel',
            'course.department',
            'curriculum.examBody',
            'feeStructure.items:id,fee_structure_id,amount',
        ]);

        if ($assignment->feeStructure !== null) {
            $assignment->feeStructure->total_amount = (float) $assignment->feeStructure->items->sum('amount');
        }

        return $assignment;
    }

    /**
     * The same fee amount for a list, computed once per distinct
     * structure.
     *
     * @param  list<CourseFeeAssignment>  $assignments
     */
    private function attachStructureTotals(array $assignments): void
    {
        collect($assignments)
            ->pluck('feeStructure')
            ->filter()
            ->unique('id')
            ->each(function (FeeStructure $structure): void {
                $structure->total_amount = (float) $structure->items->sum('amount');
            });
    }
}
