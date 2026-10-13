<?php

namespace App\Http\Resources\CertificationLevels;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificationLevelResource extends JsonResource
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
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'sequence' => $this->sequence,
            'is_active' => $this->is_active,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
