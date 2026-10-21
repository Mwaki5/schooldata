<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The unit catalogue: a unit's own identity (code, name,
        // description), independent of any course. How a unit is delivered
        // inside a course — module, hours, credit, core, active — lives on
        // `course_units`.
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // Microsecond precision keeps deletions in distinct buckets so
            // the unique index below never collides on a shared timestamp.
            $table->softDeletes('deleted_at', 6);

            // The code identifies the unit in the catalogue, free to be
            // linked into any course. Soft-deleted rows release their code
            // for reuse, matching the app's soft-delete conventions
            // everywhere else (MySQL has no partial indexes, so
            // `deleted_at` joins the key).
            $table->unique(['code', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
