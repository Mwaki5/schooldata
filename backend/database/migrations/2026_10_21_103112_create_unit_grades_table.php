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
        Schema::create('unit_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id');
            $table->foreignId('course_unit_id');
            $table->foreignId('student_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->string('exam_type', 20);
            $table->unsignedTinyInteger('exam_no');
            $table->decimal('mark', 5, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // A mark may only exist for a course unit the student actually
            // registered in the session: this composite foreign key makes
            // that a database guarantee behind the controller's check, and
            // removes the marks along with the registration. The name is
            // explicit because the auto-generated one exceeds MySQL's
            // 64-character identifier limit.
            $table->foreign(['academic_session_id', 'student_id', 'course_unit_id'], 'unit_grades_registration_foreign')
                ->references(['academic_session_id', 'student_id', 'course_unit_id'])
                ->on('unit_registrations')
                ->cascadeOnDelete();

            $table->unique(
                ['academic_session_id', 'student_id', 'course_unit_id', 'exam_type', 'exam_no'],
                'unit_grades_slot_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_grades');
    }
};
