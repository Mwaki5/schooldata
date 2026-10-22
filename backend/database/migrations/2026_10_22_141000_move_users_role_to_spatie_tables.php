<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Move the single `users.role` column into Spatie's many-to-many
     * roles.
     *
     * The role and permission catalogs are hardcoded enums mirrored into
     * the package tables by the seeder (also called here, so every fresh
     * database — tests included — carries them); each account's existing
     * role name becomes a role assignment, and the column is dropped so
     * there is a single source of truth. Users may now hold many roles;
     * permissions decide access.
     */
    public function up(): void
    {
        (new RolePermissionSeeder)->run();

        $roleIds = DB::table('roles')->pluck('id', 'name');

        DB::table('users')->whereNotNull('role')->orderBy('id')->chunkById(200, function ($users) use ($roleIds): void {
            foreach ($users as $user) {
                $roleId = $roleIds[$user->role] ?? null;

                if ($roleId === null) {
                    continue;
                }

                DB::table('model_has_roles')->insert([
                    'role_id' => $roleId,
                    'model_type' => User::class,
                    'model_id' => $user->id,
                ]);
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('student');
        });

        // The admin role is the first seeded, so it stands in for the
        // restored column's accounts; other assignments are lost.
        DB::table('users')
            ->whereIn('id', function ($query): void {
                $query->select('model_id')
                    ->from('model_has_roles')
                    ->where('model_type', User::class)
                    ->where('role_id', DB::table('roles')->where('name', 'admin')->value('id'));
            })
            ->update(['role' => 'admin']);
    }
};
