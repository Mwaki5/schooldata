<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * Mirror the hardcoded role and permission catalogs into Spatie's tables.
 *
 * The enums remain the source of truth; this seeding is idempotent, so it
 * is safe to rerun whenever a catalog entry is added (new rows appear and
 * role bundles re-sync). The move-users migration calls it so every fresh
 * database — tests included — carries the catalog without a separate step.
 *
 * The guard comes from User::guardName(), the same pin Spatie resolves at
 * assignment and check time, so the rows always match the lookups.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $guard = (new User)->guardName();

        $permissionModels = collect(Permission::cases())
            ->mapWithKeys(fn (Permission $permission): array => [
                $permission->value => PermissionModel::findOrCreate($permission->value, $guard),
            ]);

        foreach (Role::cases() as $role) {
            $roleModel = RoleModel::findOrCreate($role->value, $guard);

            // The models themselves are passed so the sync never has to
            // re-resolve a guard of its own.
            $roleModel->syncPermissions(
                collect($role->permissions())
                    ->map(fn (Permission $permission): PermissionModel => $permissionModels[$permission->value])
                    ->all()
            );
        }
    }
}
