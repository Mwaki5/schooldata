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
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // A course is delivered per curriculum; list a course's units
            // within one curriculum by this pair.
            $table->index(['course_id', 'curriculum_id']);

            // A unit's code identifies it inside its course; a different
            // course may reuse the same code. Uniqueness is enforced by
            // validation among live records only, so a soft-deleted code
            // can be reused.
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
