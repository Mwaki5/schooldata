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
        Schema::create('staff', function (Blueprint $table) {
            $table->id();

            // The provisioned auth account for the staff member, linked
            // when the staff profile is saved. Nullable for rows created
            // before account provisioning existed.
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();

            // The employment number doubles as the login id once an
            // account is provisioned, matching the wider HR numbering
            // formats (e.g. TSC numbers).
            $table->string('employment_no', 50);
            $table->string('name', 150);
            $table->string('work_email')->nullable();
            $table->string('work_phone', 30)->nullable();
            $table->string('national_id', 50)->nullable();
            $table->string('kra_pin', 50)->nullable();
            $table->string('nhif_number', 50)->nullable();
            $table->string('nssf_number', 50)->nullable();
            $table->string('position', 100)->nullable();
            $table->string('employment_type', 50)->nullable();
            $table->string('employment_status', 50)->nullable()->default('active');

            // The system role the staff member holds (a subset of the
            // Role enum: student accounts are not staff). Required by
            // validation; applied to the user account when a login is
            // provisioned for the employment number.
            $table->string('role', 50)->nullable();

            $table->date('date_joined')->nullable();
            $table->date('confirmation_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->text('termination_reason')->nullable();
            $table->decimal('basic_salary', 12, 2)->nullable();
            $table->string('highest_qualification', 100)->nullable();
            $table->string('specialization', 255)->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Employment number uniqueness is enforced by validation among
            // live records only, so a soft-deleted staff member's number
            // can be reused.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
