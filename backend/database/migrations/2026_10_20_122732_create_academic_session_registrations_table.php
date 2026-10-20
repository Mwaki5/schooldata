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
        Schema::create('academic_session_registrations', function (Blueprint $table) {
            $table->id();

            // One row per "report session" action: a student reporting for
            // one academic session. The module is the ladder position they
            // are registering for; a module is one session of study and
            // three modules make one academic year, so year_no / session_no
            // are derivable from module_no but stored for direct querying.
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->foreignId('course_stage_id')->nullable()->constrained('course_stages')->nullOnDelete();
            $table->unsignedSmallInteger('module_no');
            $table->unsignedSmallInteger('year_no');
            $table->unsignedSmallInteger('session_no');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // A student reports a given module at most once per session.
            $table->unique(
                ['academic_session_id', 'student_id', 'module_no'],
                'academic_session_registrations_report_unique',
            );
            $table->index(['student_id', 'academic_session_id'], 'academic_session_registrations_student_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_session_registrations');
    }
};
