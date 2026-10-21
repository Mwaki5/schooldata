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
        // A student registers a course-unit link, not the bare catalogue
        // unit: the link is what carries the module, allocated hours,
        // credit factor and unit type their registration actually
        // commits to, and the same unit may run in several courses.
        // Registered links are protected from deletion (restrict): a link
        // students registered is retired by marking it inactive, never by
        // unlinking it and taking its history with it.
        Schema::create('unit_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_unit_id')->constrained('course_units')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['academic_session_id', 'student_id', 'course_unit_id'],
                'unit_registrations_registration_unique',
            );
            $table->index(['student_id', 'academic_session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_registrations');
    }
};
