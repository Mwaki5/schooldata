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
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();

            // One auth account per student profile. Nullable and severed on
            // deletion (same pattern as staff.user_id) so a re-admitted
            // student can reclaim their restored account without clashing on
            // the unique link; hard user deletes null the reference.
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();

            $table->string('national_id', 50)->nullable();
            $table->string('status', 20)->nullable()->default('active');
            $table->string('first_name', 100)->nullable();
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('gender', 10)->nullable()->default('other');
            $table->date('date_of_birth')->nullable();
            $table->string('county', 100)->nullable();
            $table->string('place_of_residence', 100)->nullable();
            $table->string('religion', 100)->nullable();
            $table->text('address')->nullable();
            $table->text('profile_picture')->nullable();
            $table->string('alternative_phone', 50)->nullable();
            $table->boolean('is_pwd')->default(false);
            $table->string('disability_type', 100)->nullable();
            $table->text('disability_description')->nullable();

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
        Schema::dropIfExists('student_profiles');
    }
};
