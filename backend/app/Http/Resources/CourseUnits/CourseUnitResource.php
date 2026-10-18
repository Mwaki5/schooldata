<?php

namespace App\Http\Resources\CourseUnits;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseUnitResource extends JsonResource
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
            'unit_id' => $this->unit_id,
            'unit_code' => $this->unit?->code,
            'unit_name' => $this->unit?->name,
            'course_id' => $this->course_id,
            'course_code' => $this->course?->code,
            'course_name' => $this->course?->name,
            'curriculum_id' => $this->curriculum_id,
            'curriculum_name' => $this->curriculum?->name,
            'core' => $this->core,
            'time_offered' => $this->time_offered,
            'taught_hours' => $this->taught_hours,
            'credit_factor' => $this->credit_factor,
            'is_active' => $this->is_active,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
