<?php

namespace App\Http\Resources\Enrolments;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrolmentResource extends JsonResource
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
            'student_id' => $this->student_id,
            'student_name' => User::composeName(
                $this->student?->first_name,
                $this->student?->middle_name,
                $this->student?->last_name,
            ) ?: ($this->student?->user?->name ?? ''),
            'student_login_id' => $this->student?->user?->login_id,
            'course_id' => $this->course_id,
            'course_code' => $this->course?->code,
            'course_name' => $this->course?->name,
            'curriculum_id' => $this->curriculum_id,
            'curriculum_name' => $this->curriculum?->name,
            'academic_session_id' => $this->academic_session_id,
            'academic_session_name' => $this->academicSession?->name,
            'certification_level_id' => $this->certification_level_id,
            'certification_level_name' => $this->certificationLevel?->name,
            'enrolment_date' => $this->enrolment_date,
            'status' => $this->status,
            'remarks' => $this->remarks,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
