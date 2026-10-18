<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refine the teaching load carried by each course + curriculum
 * assignment:
 *
 *   - `core` marks whether the unit is a required part of the course
 *     (true, the default) or an elective.
 *   - `time_offered` records WHEN in the programme of study the unit is
 *     taken — a free label such as "Year 1 Term 1" — replacing the old
 *     `modules_taught` count, which no business rule used.
 *
 * Down restores `modules_taught` (nullable, as it was) but cannot bring
 * back its old values: those numbers are gone the moment the column is
 * dropped, on MySQL as on SQLite.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('course_units', function (Blueprint $table) {
            $table->boolean('core')->default(true)->after('curriculum_id');
            $table->string('time_offered', 100)->nullable()->after('core');
        });

        Schema::table('course_units', function (Blueprint $table) {
            $table->dropColumn('modules_taught');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_units', function (Blueprint $table) {
            $table->unsignedSmallInteger('modules_taught')->nullable();
        });

        Schema::table('course_units', function (Blueprint $table) {
            $table->dropColumn(['time_offered', 'core']);
        });
    }
};
