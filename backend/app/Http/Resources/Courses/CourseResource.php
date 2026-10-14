<?php

namespace App\Http\Resources\Courses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
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
            'initials' => $this->initials,
            'name' => $this->name,
            'duration_months' => $this->duration_months,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'certification_level_id' => $this->certification_level_id,
            'certification_level_name' => $this->certificationLevel?->name,
            'department_id' => $this->department_id,
            'department_name' => $this->department?->name,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
