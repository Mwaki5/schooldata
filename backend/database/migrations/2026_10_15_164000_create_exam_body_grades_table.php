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
        Schema::create('exam_body_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_body_id')->constrained('exam_bodies');
            $table->string('grade', 50);
            $table->decimal('grade_start', 5, 2);
            $table->decimal('grade_end', 5, 2);
            $table->text('remark')->nullable();
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sequence')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // The grade scale is listed per exam body in sequence order.
            $table->index(['exam_body_id', 'sequence']);

            // Grade names are unique per exam body among live records only
            // (enforced by validation), so a soft-deleted band's name can
            // be reused by a later scale.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_body_grades');
    }
};
