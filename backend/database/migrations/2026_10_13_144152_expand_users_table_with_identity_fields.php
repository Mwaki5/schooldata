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
        Schema::table('users', function (Blueprint $table) {
            // Widen the login id to fit wider HR numbering formats.
            // Stays nullable: self-registered students have no login id.
            $table->string('login_id', 100)->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            // Identity fields: the name parts split first/middle/last
            // while `name` stays the composed display name.
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('last_name', 100)->nullable()->after('middle_name');
            $table->string('gender', 10)->nullable()->default('other')->after('last_name');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->string('nationality', 100)->nullable()->after('date_of_birth');
            $table->string('county', 100)->nullable()->after('nationality');
            $table->string('place_of_birth', 100)->nullable()->after('county');
            $table->string('religion', 100)->nullable()->after('place_of_birth');
            $table->text('address')->nullable()->after('religion');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('postal_code', 20)->nullable()->after('city');
            $table->text('profile_picture')->nullable()->after('postal_code');
            $table->string('alternative_phone', 50)->nullable()->after('profile_picture');
            $table->boolean('is_pwd')->default(false)->after('alternative_phone');
            $table->string('disability_type', 100)->nullable()->after('is_pwd');
            $table->text('disability_description')->nullable()->after('disability_type');

            // Login security: `must_reset_password` forces a password
            // change on first sign-in; the attempt counters and lockout
            // window are reserved for the account-lockout feature.
            $table->boolean('must_reset_password')->default(false)->after('disability_description');
            $table->timestamp('password_changed_at')->nullable()->after('must_reset_password');
            $table->integer('failed_login_attempts')->default(0)->after('password_changed_at');
            $table->timestamp('last_failed_login_at')->nullable()->after('failed_login_attempts');
            $table->timestamp('locked_until')->nullable()->after('last_failed_login_at');
            $table->boolean('two_factor_enabled')->default(false)->after('locked_until');
            $table->string('status', 20)->nullable()->default('active')->after('two_factor_enabled');

            // Sign-in forensics, written on every successful login.
            $table->timestamp('last_login_at')->nullable()->after('status');
            $table->string('last_login_ip', 50)->nullable()->after('last_login_at');
            $table->text('last_login_user_agent')->nullable()->after('last_login_ip');

            // Audit stamps and soft deletes.
            $table->foreignId('created_by')->nullable()->after('last_login_user_agent')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn([
                'first_name',
                'middle_name',
                'last_name',
                'gender',
                'date_of_birth',
                'nationality',
                'county',
                'place_of_birth',
                'religion',
                'address',
                'city',
                'postal_code',
                'profile_picture',
                'alternative_phone',
                'is_pwd',
                'disability_type',
                'disability_description',
                'must_reset_password',
                'password_changed_at',
                'failed_login_attempts',
                'last_failed_login_at',
                'locked_until',
                'two_factor_enabled',
                'status',
                'last_login_at',
                'last_login_ip',
                'last_login_user_agent',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('login_id', 30)->nullable()->change();
        });
    }
};
