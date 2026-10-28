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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 30);
            $table->foreignId('student_id')->constrained('student_profiles')->cascadeOnDelete();
            // The academic session is stamped by the backend from the
            // active session at creation time and is never accepted from
            // the client: every entry of one session groups together.
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->string('invoice_type', 30);
            $table->foreignId('fee_structure_id')->nullable()->constrained('fee_structures')->nullOnDelete();
            $table->string('description', 500)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('entry_kind', 30)->default('INVOICE');
            // One reversal per original, enforced by the database so a
            // double submission or concurrent request can never raise
            // two corrections for the same invoice.
            $table->foreignId('reverses_invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->string('reversal_reason', 500)->nullable();
            // Client-supplied retry guard: replaying the same key
            // returns the original row instead of billing twice.
            $table->string('idempotency_key', 100)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            // Numbers are unique for the life of the row — the sequence
            // never reissues one. The unique index backs the sequence
            // lookup and the retry guard alike. The pointer's unique
            // index is what makes a second reversal of one invoice
            // impossible, even under concurrency.
            //
            // Sign and reason invariants (positive charges, negative
            // reversals, a mandatory reason) are held by the services,
            // which are the only path to these rows — Laravel 12's
            // schema builder cannot express CHECK constraints
            // portably across the SQLite test and MySQL deployment
            // engines.
            $table->unique('invoice_number');
            $table->unique('reverses_invoice_id');
            $table->unique('idempotency_key');

            // The billing list filters on type and reads newest first;
            // the ledger groups entries per student and session.
            $table->index('invoice_type');
            $table->index('entry_kind');
            $table->index(['student_id', 'id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
