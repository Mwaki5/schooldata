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
        Schema::table('student_profiles', function (Blueprint $table) {
            // Sponsorship: whether the student's fees are sponsored,
            // and by whom (optional even when sponsored).
            $table->boolean('sponsored')->default(false)->after('disability_description');
            $table->string('sponsored_by', 150)->nullable()->after('sponsored');

            // The KCPE/KCSE index number and year from the previous school.
            $table->string('index_no', 50)->nullable()->after('sponsored_by');
            $table->string('exam_year', 4)->nullable()->after('index_no');
            $table->string('previous_school', 150)->nullable()->after('exam_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'sponsored',
                'sponsored_by',
                'index_no',
                'exam_year',
                'previous_school',
            ]);
        });
    }
};
