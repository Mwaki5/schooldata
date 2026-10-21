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
        // One unit delivered inside one course + curriculum pair: the
        // teaching load (module, hours, credit) and role (basic / core /
        // common) belong to that delivery, not to the unit itself — the
        // same unit may be linked into several courses with different
        // loads.
        Schema::create('course_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('curriculum_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('modules_taught')->nullable();
            $table->unsignedSmallInteger('taught_hours')->nullable();
            $table->decimal('credit_factor', 10, 2)->nullable();
            $table->string('unit_type', 20)->default('core');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // A unit is linked to a course + curriculum pair once; repeat
            // links would double-count it in every offering query.
            $table->unique(['unit_id', 'course_id', 'curriculum_id'], 'course_units_link_unique');
            $table->index(['course_id', 'curriculum_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_units');
    }
};
