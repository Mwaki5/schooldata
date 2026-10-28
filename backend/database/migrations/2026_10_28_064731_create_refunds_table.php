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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number', 30);
            $table->foreignId('student_id')->constrained('student_profiles')->cascadeOnDelete();
            // Stamped by the backend from the active session, like every
            // ledger entry — never accepted from the client.
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->string('payment_method', 30);
            $table->string('reference', 100)->nullable();
            // Refunds are money held for the student going back out:
            // always positive, never more than their current credit.
            $table->decimal('amount', 12, 2);
            $table->string('reason', 500);
            $table->string('idempotency_key', 100)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            // Refund numbers and retry guards are unique for the life of
            // the row, and a refund reference is unique within its
            // method — the same slip cannot be paid out twice. The
            // positive-amount invariant is held by the service, which
            // is the only path to these rows.
            $table->unique('refund_number');
            $table->unique('idempotency_key');
            $table->unique(['payment_method', 'reference']);

            // The ledger groups entries per student and session.
            $table->index(['student_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
