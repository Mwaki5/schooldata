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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('initials', 20);
            $table->string('name');
            // The ladder length in modules (sessions of study): three
            // modules make one academic year.
            $table->unsignedSmallInteger('total_modules')->nullable();
            // The minimum entry grade sought from the leaving certificate,
            // stored as free text so any grading vocabulary fits.
            $table->string('entry_grade', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('certification_level_id')->nullable()->constrained('certification_levels')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Code and name uniqueness are enforced by validation among live
            // records (soft-deleted values are reusable), matching the
            // certification_levels convention.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
