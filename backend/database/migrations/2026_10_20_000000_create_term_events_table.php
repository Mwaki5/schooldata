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
        Schema::create('term_events', function (Blueprint $table) {
            $table->id();

            // A calendar event only exists as part of a term, so it is
            // removed together with its session.
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();

            // Closed vocabulary of events, see the TermEventName enum.
            $table->string('name');

            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // A session carries at most one date range per event.
            $table->unique(['academic_session_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('term_events');
    }
};
