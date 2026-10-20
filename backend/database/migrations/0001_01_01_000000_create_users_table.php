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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // Identity fields: the name parts split first/middle/last
            // while `name` stays the composed display name.
            $table->string('first_name', 100)->nullable();
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('gender', 10)->nullable()->default('other');
            $table->date('date_of_birth')->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('county', 100)->nullable();
            $table->string('place_of_birth', 100)->nullable();
            $table->string('religion', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->text('profile_picture')->nullable();
            $table->string('alternative_phone', 50)->nullable();
            $table->boolean('is_pwd')->default(false);
            $table->string('disability_type', 100)->nullable();
            $table->text('disability_description')->nullable();

            $table->string('email')->unique();
            // Alternate sign-in identifier kept on the auth table: the
            // employment number for staff, the admission number for
            // students. Nullable because self-registered accounts only
            // sign in with an email address.
            $table->string('login_id', 100)->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // The system role the account holds, fixed by the application
            // (admin, trainer, student, finance) rather than the database.
            $table->enum('role', ['admin', 'trainer', 'student', 'finance'])->default('student');

            // Login security: `must_reset_password` forces a password
            // change on first sign-in; the attempt counters and lockout
            // window are reserved for the account-lockout feature.
            $table->boolean('must_reset_password')->default(false);
            $table->timestamp('password_changed_at')->nullable();
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('last_failed_login_at')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('status', 20)->nullable()->default('active');

            // Sign-in forensics, written on every successful login.
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 50)->nullable();
            $table->text('last_login_user_agent')->nullable();

            // Audit stamps and soft deletes.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
