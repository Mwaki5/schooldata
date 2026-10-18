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
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses');
            $table->foreignId('curriculum_id')->constrained('curricula');
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('modules_taught')->nullable();
            $table->unsignedSmallInteger('taught_hours')->nullable();
            $table->decimal('credit_factor', 10, 2)->nullable();
            $table->boolean('core')->default(true);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // Microsecond precision keeps deletions in distinct buckets so
            // the unique index below never collides on a shared timestamp.
            $table->softDeletes('deleted_at', 6);

            // A course is delivered per curriculum, and a unit's code
            // identifies it inside that pair: another course — or another
            // curriculum of the same course — may reuse the same code for
            // a genuinely different unit. Soft-deleted rows free their
            // code for reuse, matching the app's soft-delete conventions
            // everywhere else (MySQL has no partial indexes, so
            // `deleted_at` joins the key instead).
            $table->unique(['curriculum_id', 'course_id', 'code', 'deleted_at']);

            $table->index(['course_id', 'curriculum_id']);
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
