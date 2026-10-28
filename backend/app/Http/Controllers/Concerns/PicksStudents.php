<?php

namespace App\Http\Controllers\Concerns;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The shared student picker behind the finance screens' searchable
 * selects.
 *
 * The finance desk bills and takes money from students without holding
 * `students.view` — the roster permission — so each desk's endpoint
 * rides its own permission (`invoices.create`, `payments.receive`) and
 * answers with just what a picker renders: id, composed name and
 * admission number, searchable by any of them, capped.
 */
trait PicksStudents
{
    /**
     * The student picker rows: names and admission numbers.
     */
    protected function pickStudents(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim($data['search'] ?? '');

        $students = StudentProfile::query()
            ->with('user:id,login_id,name')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('first_name', 'like', $like)
                        ->orWhere('middle_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhereHas('user', fn (Builder $inner) => $inner->where('login_id', 'like', $like));
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(50)
            ->get()
            ->map(fn (StudentProfile $student): array => [
                'id' => $student->id,
                'name' => User::composeName($student->first_name, $student->middle_name, $student->last_name)
                    ?: ($student->user?->name ?? ''),
                'login_id' => $student->user?->login_id,
            ]);

        return response()->json(['students' => $students]);
    }
}
