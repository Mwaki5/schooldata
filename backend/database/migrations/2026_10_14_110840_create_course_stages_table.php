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
        Schema::create('course_stages', function (Blueprint $table) {
            $table->id();

            // Every course carries its own progression ladder. The module
            // number is the position in the ladder (1..n); a module is one
            // session of study and three modules make one academic year.
            // year_no / session_no locate the stage in the calendar and are
            // derivable from module_no (three sessions per academic year).
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->unsignedSmallInteger('module_no');
            $table->unsignedSmallInteger('year_no');
            $table->unsignedSmallInteger('session_no');
            $table->string('code', 10);

            $table->timestamps();

            $table->unique(['course_id', 'module_no']);
            $table->unique(['course_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_stages');
    }
};
