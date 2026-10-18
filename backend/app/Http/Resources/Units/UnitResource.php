<?php

namespace App\Http\Resources\Units;

use App\Http\Resources\Courses\CourseResource;
use App\Http\Resources\Curriculums\CurriculumResource;
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
            'course_id' => $this->course_id,
            'curriculum_id' => $this->curriculum_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'modules_taught' => $this->modules_taught,
            'taught_hours' => $this->taught_hours,
            'credit_factor' => $this->credit_factor,
            'core' => $this->core,
            'is_active' => $this->is_active,
            // Nested on list and details payloads whenever the
            // controllers eager-load the relations.
            'course' => new CourseResource($this->whenLoaded('course')),
            'curriculum' => new CurriculumResource($this->whenLoaded('curriculum')),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
