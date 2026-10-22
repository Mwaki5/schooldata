<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Get the data that should be returned in the resource.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // The payload renders the user's roles and the permissions they
        // bundle; load them with the user so no response path hits a lazy
        // load (lazy loading is prevented app-wide).
        $this->loadMissing('roles.permissions', 'permissions');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'login_id' => $this->login_id,
            // Roles bundle permissions; the client gates its UI on the
            // permission names, mirroring the route middleware. Sorted
            // for a stable payload.
            'roles' => $this->getRoleNames()->values()->all(),
            'permissions' => $this->getAllPermissions()->pluck('name')->sort()->values()->all(),
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'gender' => $this->gender,
            'status' => $this->status,
            'must_reset_password' => $this->must_reset_password,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
        ];
    }
}
