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
        Schema::create('course_enrolments', function (Blueprint $table) {
            $table->id();

            // The enrolled student (their profile row) taking a specific
            // course under a particular exam-body curriculum.
            $table->foreignId('student_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses');
            $table->foreignId('curriculum_id')->constrained('curricula');

            // Optional term the enrolment belongs to, and the certification
            // level the student entered at.
            $table->foreignId('academic_session_id')->nullable()->constrained('academic_sessions');
            $table->foreignId('certification_level_id')->constrained('certification_levels');

            $table->date('enrolment_date')->useCurrent();
            $table->enum('status', ['ENROLLED', 'COMPLETED', 'DROPPED'])->default('ENROLLED');
            $table->text('remarks')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_enrolments');
    }
};
