<?php

namespace App\Http\Resources\Units;

use App\Http\Resources\Courses\CourseResource;
use App\Http\Resources\Curriculums\CurriculumResource;
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
            'course_id' => $this->course_id,
            'curriculum_id' => $this->curriculum_id,
            'modules_taught' => $this->modules_taught,
            'taught_hours' => $this->taught_hours,
            'credit_factor' => $this->credit_factor,
            'unit_type' => $this->unit_type->value,
            'is_active' => $this->is_active,
            // Nested on list payloads whenever the controller
            // eager-loads the relations.
            'unit' => new UnitResource($this->whenLoaded('unit')),
            'course' => new CourseResource($this->whenLoaded('course')),
            'curriculum' => new CurriculumResource($this->whenLoaded('curriculum')),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
