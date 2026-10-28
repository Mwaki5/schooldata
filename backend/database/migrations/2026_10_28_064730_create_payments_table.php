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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 30);
            $table->foreignId('student_id')->constrained('student_profiles')->cascadeOnDelete();
            // Stamped by the backend from the active session, like every
            // ledger entry — never accepted from the client.
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->string('payment_method', 30);
            $table->string('reference', 100)->nullable();
            // The payment date defaults to the server's day; only a
            // holder of `payments.backdate` may set it into the past.
            $table->date('payment_date');
            $table->decimal('amount', 12, 2);
            $table->string('entry_kind', 30)->default('PAYMENT');
            // One reversal per original, enforced by the database so a
            // double submission or concurrent request can never raise
            // two corrections for the same payment.
            $table->foreignId('reverses_payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->string('reversal_reason', 500)->nullable();
            $table->string('notes', 500)->nullable();
            // Client-supplied retry guard: replaying the same key
            // returns the original row instead of recording twice.
            $table->string('idempotency_key', 100)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            // Receipt numbers are unique for the life of the row, and
            // the retry guard is backed by its own unique index.
            $table->unique('receipt_number');
            $table->unique('reverses_payment_id');
            $table->unique('idempotency_key');

            // A transaction reference is unique within its method, so
            // the same bank slip or till number cannot be entered
            // twice. NULL references (cash without a slip) are exempt,
            // which both engines treat as distinct in a unique index.
            $table->unique(['payment_method', 'reference']);

            // Sign and reason invariants (positive payments, negative
            // reversals, a mandatory reason) are held by the services,
            // which are the only path to these rows — Laravel 12's
            // schema builder cannot express CHECK constraints
            // portably across the SQLite test and MySQL deployment
            // engines. The unique indexes above are what make a
            // second reversal of one payment impossible, even under
            // concurrency.

            // The ledger groups entries per student and session.
            $table->index(['student_id', 'id']);
            $table->index('payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
