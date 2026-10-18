<?php

namespace App\Http\Resources\ExamBodyGrades;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamBodyGradeResource extends JsonResource
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
            'exam_body_id' => $this->exam_body_id,
            'exam_body_name' => $this->examBody?->name,
            'grade' => $this->grade,
            'grade_start' => $this->grade_start,
            'grade_end' => $this->grade_end,
            'remark' => $this->remark,
            'sequence' => $this->sequence,
            'is_active' => $this->is_active,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
