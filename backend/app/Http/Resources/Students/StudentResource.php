<?php

namespace App\Http\Resources\Students;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
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
            'user_id' => $this->user_id,
            'login_id' => $this->user?->login_id,
            'email' => $this->user?->email,
            'name' => User::composeName($this->first_name, $this->middle_name, $this->last_name)
                ?: ($this->user?->name ?? ''),
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'gender' => $this->gender,
            'status' => $this->status,
            'phone' => $this->phone,
            'national_id' => $this->national_id,
            'date_of_birth' => $this->date_of_birth,
            'county' => $this->county,
            'place_of_residence' => $this->place_of_residence,
            'religion' => $this->religion,
            'address' => $this->address,
            'profile_picture' => $this->profile_picture,
            'alternative_phone' => $this->alternative_phone,
            'is_pwd' => $this->is_pwd,
            'disability_type' => $this->disability_type,
            'disability_description' => $this->disability_description,
            'sponsored' => $this->sponsored,
            'sponsored_by' => $this->sponsored_by,
            'index_no' => $this->index_no,
            'exam_year' => $this->exam_year,
            'previous_school' => $this->previous_school,
            'enrolment' => $this->enrolmentPayload(),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * The student's current enrolment as a nested segment for the form,
     * or null when the relation was not loaded or there is no enrolment.
     *
     * @return array<string, mixed>|null
     */
    private function enrolmentPayload(): ?array
    {
        if (! $this->relationLoaded('latestEnrolment') || ! $this->latestEnrolment) {
            return null;
        }

        $enrolment = $this->latestEnrolment;
        $enrolment->loadMissing(['course', 'courseStage', 'curriculum', 'academicSession', 'certificationLevel']);

        return [
            'id' => $enrolment->id,
            'course_id' => $enrolment->course_id,
            'course_stage_id' => $enrolment->course_stage_id,
            'course_stage_code' => $enrolment->courseStage?->code,
            'curriculum_id' => $enrolment->curriculum_id,
            'academic_session_id' => $enrolment->academic_session_id,
            'certification_level_id' => $enrolment->certification_level_id,
            'enrolment_date' => $enrolment->enrolment_date?->toDateString(),
            'status' => $enrolment->status?->value,
            'remarks' => $enrolment->remarks,
            // Display labels so the student form can prefill its pickers.
            'course_label' => $enrolment->course
                ? implode(' — ', array_filter([$enrolment->course->code, $enrolment->course->name]))
                : null,
            'curriculum_label' => $enrolment->curriculum?->name,
            'academic_session_label' => $enrolment->academicSession?->name,
            'certification_level_label' => $enrolment->certificationLevel?->name,
        ];
    }
}
