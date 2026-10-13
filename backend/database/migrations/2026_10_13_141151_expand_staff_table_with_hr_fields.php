<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            // The employment number doubles as the login id once an
            // account is provisioned, matching the wider HR numbering
            // formats (e.g. TSC numbers).
            $table->string('employment_no', 50)->nullable(false)->change();
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->renameColumn('email', 'work_email');
            $table->renameColumn('phone', 'work_phone');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->string('national_id', 50)->nullable()->after('work_phone');
            $table->string('kra_pin', 50)->nullable()->after('national_id');
            $table->string('nhif_number', 50)->nullable()->after('kra_pin');
            $table->string('nssf_number', 50)->nullable()->after('nhif_number');
            $table->string('employment_type', 50)->nullable()->after('position');
            $table->string('employment_status', 50)->nullable()->default('active')->after('employment_type');
            $table->date('date_joined')->nullable()->after('employment_status');
            $table->date('confirmation_date')->nullable()->after('date_joined');
            $table->date('contract_end_date')->nullable()->after('confirmation_date');
            $table->date('termination_date')->nullable()->after('contract_end_date');
            $table->text('termination_reason')->nullable()->after('termination_date');
            $table->decimal('basic_salary', 12, 2)->nullable()->after('termination_reason');
            $table->string('highest_qualification', 100)->nullable()->after('basic_salary');
            $table->string('specialization', 255)->nullable()->after('highest_qualification');
        });

        // Convert the boolean flag into the richer status vocabulary
        // before dropping it.
        DB::table('staff')
            ->where('is_active', false)
            ->update(['employment_status' => 'inactive']);

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('department_id');
        });

        DB::table('staff')
            ->where('employment_status', '!=', 'active')
            ->update(['is_active' => false]);

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn([
                'national_id',
                'kra_pin',
                'nhif_number',
                'nssf_number',
                'employment_type',
                'employment_status',
                'date_joined',
                'confirmation_date',
                'contract_end_date',
                'termination_date',
                'termination_reason',
                'basic_salary',
                'highest_qualification',
                'specialization',
            ]);
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->renameColumn('work_email', 'email');
            $table->renameColumn('work_phone', 'phone');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->string('employment_no', 30)->nullable(false)->change();
        });
    }
};
