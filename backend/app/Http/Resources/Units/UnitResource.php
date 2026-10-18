<?php

namespace App\Http\Resources\Units;

use App\Http\Resources\CourseUnits\CourseUnitResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    /**
     * Get the data that should be returned in the resource.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            // Populated by `withCount('assignments')` on the controllers;
            // live deliveries only, because the count honours the
            // assignment's soft-delete scope.
            'assignments_count' => (int) $this->assignments_count,
            // Nested on the details payload, absent on lists.
            'assignments' => CourseUnitResource::collection($this->whenLoaded('assignments')),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
