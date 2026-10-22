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
        // Which fee structure a course + curriculum pair charges in a
        // given year (and session, when charging per session). The pair
        // is what an enrolment carries, so a student's fee is located
        // from their enrolment alone.
        Schema::create('course_fee_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('curriculum_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_structure_id')->constrained('fee_structures');
            // Which year of study the fee applies to, and — when it
            // charges per session — which session of that year. A
            // per-year fee leaves session_no empty.
            $table->unsignedSmallInteger('year_no');
            $table->unsignedSmallInteger('session_no')->nullable();
            $table->string('span', 20);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // A slot — pair, year and session — charges under exactly
            // one live assignment; repeats would make the enrolment-
            // based fee lookup ambiguous. The uniqueness itself is
            // enforced by validation among live records only; this
            // index backs the lookup.
            $table->index(
                ['course_id', 'curriculum_id', 'year_no', 'session_no'],
                'course_fee_assignments_slot_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_fee_assignments');
    }
};
