<?php

namespace Modules\Student\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Academic\Resources\StudyProgramResource;
use Modules\Identity\Resources\UserResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'study_program_id' => $this->study_program_id,
            'study_program' => new StudyProgramResource($this->whenLoaded('studyProgram')),
            'student_number' => $this->student_number,
            'national_student_number' => $this->national_student_number,
            'national_id' => $this->national_id,
            'mother_name' => $this->mother_name,
            'full_name' => $this->full_name,
            'nickname' => $this->nickname,
            'gender' => $this->gender instanceof \BackedEnum ? $this->gender->value : $this->gender,
            'birth_place' => $this->birth_place,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'religion' => $this->religion,
            'marital_status' => $this->marital_status,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'province' => $this->province,
            'city' => $this->city,
            'district' => $this->district,
            'postal_code' => $this->postal_code,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'admission_year' => $this->admission_year,
            'entry_date' => $this->entry_date?->format('Y-m-d'),
            'graduation_date' => $this->graduation_date?->format('Y-m-d'),
            'photo_path' => $this->photo_path,
            'notes' => $this->notes,
            'families' => StudentFamilyResource::collection($this->whenLoaded('families')),
            'educations' => StudentEducationResource::collection($this->whenLoaded('educations')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
