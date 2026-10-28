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
        Schema::table('refunds', function (Blueprint $table) {
            // The two rows a refunds table now holds: a refund paying
            // credit out, and the linked negative row that corrects one.
            $table->string('entry_kind', 30)->default('REFUND');
            // One reversal per original, enforced by the database so a
            // double submission or concurrent request can never raise
            // two corrections for the same refund.
            $table->foreignId('reverses_refund_id')->nullable()->constrained('refunds')->restrictOnDelete();

            // The correction path for a refund paid to the wrong
            // student, in the wrong amount or twice. Without it a coin
            // that left the ledger could never be traced back, and a
            // refunded payment would be permanently un-reversible.
            $table->unique('reverses_refund_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reverses_refund_id');
            $table->dropColumn('entry_kind');
        });
    }
};
