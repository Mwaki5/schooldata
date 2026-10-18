<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalise the flat `units` table into a reusable master catalogue
 * (`units`) plus per-delivery assignments (`course_units`).
 *
 * Field ownership:
 *   - Master `units`: `code` (globally unique among live records),
 *     `name`, `description`, `is_active`, audit.
 *   - `course_units`: the course + curriculum a delivery happens under
 *     plus its teaching load (`modules_taught`, `taught_hours`,
 *     `credit_factor`) and its own active flag and audit.
 *
 * Up: every existing row — soft-deleted ones included — becomes exactly
 * one master unit (keeping its id and its code, so references stay
 * stable) and one assignment carrying the old course, curriculum and
 * teaching load. Nothing is merged or dropped: rows that share a name
 * become separate masters, reported later by `GET /api/units/duplicates`
 * for a human to review.
 *
 * Down: assignments are exploded back into the old flat shape (one row
 * per assignment, reusing the master's code), which is exactly what the
 * old schema allowed, so the rollback is lossless. The restored legacy
 * columns are nullable (the original ones were NOT NULL) because SQLite
 * cannot add a NOT NULL column to a populated table and a never-assigned
 * master has no course to point at; the API validation as before demands
 * course and curriculum on every write.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('course_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses');
            $table->foreignId('curriculum_id')->constrained('curricula');
            $table->unsignedSmallInteger('modules_taught')->nullable();
            $table->unsignedSmallInteger('taught_hours')->nullable();
            $table->decimal('credit_factor', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // Microsecond precision keeps deletions in distinct buckets so
            // the unique index below never collides on a shared timestamp.
            $table->softDeletes('deleted_at', 6);

            // A unit is delivered once per course + curriculum. The old
            // flat table held exactly one such row per unit id, so the
            // key cannot clash while the data is copied over.
            //
            // `deleted_at` is part of the key because MySQL has no partial
            // indexes: live rows (deleted_at IS NULL) stay unique among
            // themselves while a soft-deleted assignment frees its
            // course + curriculum for a fresh assignment, matching the
            // app's soft-delete conventions everywhere else.
            $table->unique(['unit_id', 'course_id', 'curriculum_id', 'deleted_at']);

            $table->index(['course_id', 'curriculum_id']);
        });

        // Copy every legacy row — including soft-deleted ones, which the
        // query builder sees because it applies no model scopes — into
        // its own assignment, preserving audit stamps, timestamps and
        // deletion state verbatim. The legacy code stays on the master,
        // which keeps its id.
        DB::table('units')
            ->orderBy('id')
            ->chunkById(500, function ($units): void {
                foreach ($units as $unit) {
                    // A row without a course never had a delivery (only
                    // possible for masters created after the first
                    // version of this migration): it stays a pure
                    // catalogue entry.
                    if ($unit->course_id === null || $unit->curriculum_id === null) {
                        continue;
                    }

                    DB::table('course_units')->insert([
                        'unit_id' => $unit->id,
                        'course_id' => $unit->course_id,
                        'curriculum_id' => $unit->curriculum_id,
                        'modules_taught' => $unit->modules_taught,
                        'taught_hours' => $unit->taught_hours,
                        'credit_factor' => $unit->credit_factor,
                        'is_active' => $unit->is_active,
                        'created_by' => $unit->created_by,
                        'updated_by' => $unit->updated_by,
                        'created_at' => $unit->created_at,
                        'updated_at' => $unit->updated_at,
                        'deleted_at' => $unit->deleted_at,
                    ]);
                }
            });

        // Dropping the delivery columns needs their foreign keys and the
        // old (course_id, curriculum_id) index out of the way first:
        // MySQL refuses a drop while a FK names the column, and SQLite's
        // native DROP COLUMN refuses columns used by keys or indexes
        // (it rebuilds the table for the FK drop itself). `code` stays:
        // it is the master's global unit code.
        Schema::table('units', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropForeign(['curriculum_id']);
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropIndex(['course_id', 'curriculum_id']);
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn([
                'course_id',
                'curriculum_id',
                'modules_taught',
                'taught_hours',
                'credit_factor',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            // Nullable (unlike the original NOT NULL columns): SQLite
            // refuses a NOT NULL add on a populated table, and a master
            // created after the migration without any assignment has no
            // course to point at. The same app-level validation as
            // before still requires these fields on every write.
            $table->foreignId('course_id')->nullable()->constrained('courses');
            $table->foreignId('curriculum_id')->nullable()->constrained('curricula');
            $table->unsignedSmallInteger('modules_taught')->nullable();
            $table->unsignedSmallInteger('taught_hours')->nullable();
            $table->decimal('credit_factor', 10, 2)->nullable();

            $table->index(['course_id', 'curriculum_id']);
        });

        // Capture both id sets while `units` still holds only masters:
        // the explode below inserts its flat rows into the same table.
        $assignedUnitIds = DB::table('course_units')->pluck('unit_id')->unique()->all();

        // Fold every assignment back into a flat row. Master data (code,
        // name, description, status, deletion state) comes from the
        // master; teaching load and course/curriculum come from the
        // assignment; `updated_at` keeps the most recent of the two so
        // no edit time is lost. The master keeps its code throughout,
        // so no placeholder is needed anywhere.
        DB::table('course_units')
            ->orderBy('id')
            ->chunkById(500, function ($assignments): void {
                foreach ($assignments as $assignment) {
                    $master = DB::table('units')->where('id', $assignment->unit_id)->first();

                    DB::table('units')->insert([
                        'course_id' => $assignment->course_id,
                        'curriculum_id' => $assignment->curriculum_id,
                        'code' => $master->code ?? 'LEGACY-'.$assignment->unit_id,
                        'name' => $master->name ?? 'Recovered unit',
                        'description' => $master->description ?? null,
                        'modules_taught' => $assignment->modules_taught,
                        'taught_hours' => $assignment->taught_hours,
                        'credit_factor' => $assignment->credit_factor,
                        'is_active' => $master->is_active ?? $assignment->is_active,
                        'created_by' => $master->created_by ?? $assignment->created_by,
                        'updated_by' => $assignment->updated_by ?? $master->updated_by ?? null,
                        'created_at' => $master->created_at ?? $assignment->created_at,
                        'updated_at' => max($master->updated_at ?? '', $assignment->updated_at ?? '') ?: null,
                        'deleted_at' => $master->deleted_at ?? null,
                    ]);
                }
            });

        // The exploded rows above now carry the content of every master
        // that had assignments, so the stripped master rows themselves
        // can go. Masters without any assignment stay exactly as they
        // are — their code is already on the row.
        DB::table('units')->whereIn('id', $assignedUnitIds)->delete();

        Schema::dropIfExists('course_units');
    }
};
