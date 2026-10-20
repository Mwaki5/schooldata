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
        Schema::create('session_stage_offerings', function (Blueprint $table) {
            $table->id();

            // One row per stage explicitly offered in a session. The absence
            // of a row means the stage is closed; rows are never pre-created
            // for every stage of every course.
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('course_stage_id')->constrained('course_stages')->cascadeOnDelete();

            // A row may exist but be closed (an explicitly withdrawn stage),
            // which is distinct from never having been opened at all.
            $table->enum('status', ['open', 'closed'])->default('closed');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Explicit short names: MySQL caps identifiers at 64 characters
            // and the conventional generated names exceed that here.
            $table->unique(
                ['academic_session_id', 'course_stage_id'],
                'session_stage_offerings_session_stage_unique',
            );
            $table->index(
                ['academic_session_id', 'status'],
                'session_stage_offerings_session_status_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_stage_offerings');
    }
};
