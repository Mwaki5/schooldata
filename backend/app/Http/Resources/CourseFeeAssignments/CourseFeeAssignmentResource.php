<?php

namespace App\Http\Resources\CourseFeeAssignments;

use App\Http\Resources\Courses\CourseResource;
use App\Http\Resources\Curriculums\CurriculumResource;
use App\Http\Resources\FeeStructures\FeeStructureResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseFeeAssignmentResource extends JsonResource
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
            'fee_structure_id' => $this->fee_structure_id,
            'span' => $this->span->value,
            // Nested on list payloads whenever the controller
            // eager-loads the relations.
            'course' => new CourseResource($this->whenLoaded('course')),
            'curriculum' => new CurriculumResource($this->whenLoaded('curriculum')),
            'fee_structure' => new FeeStructureResource($this->whenLoaded('feeStructure')),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
