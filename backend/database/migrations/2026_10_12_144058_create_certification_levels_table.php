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
        Schema::create('certification_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_body_id')->constrained('exam_bodies');
            $table->string('name', 100);
            $table->string('code', 30);
            $table->text('description');
            $table->smallInteger('sequence');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Code uniqueness is scoped to the exam body and enforced by
            // validation among live records (soft-deleted codes are reusable).
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certification_levels');
    }
};
