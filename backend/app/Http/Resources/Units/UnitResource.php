<?php

namespace App\Http\Resources\Units;

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
            // Loaded with withCount / loadCount: how many courses link
            // this unit.
            'course_units_count' => $this->whenHas('course_units_count'),
            // The deliveries, whenever the controller eager-loads them:
            // how this unit is taught inside each course + curriculum.
            'course_units' => CourseUnitResource::collection($this->whenLoaded('courseUnits')),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
