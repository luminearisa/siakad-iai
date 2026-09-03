<?php

namespace Modules\Lecturer\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Academic\Resources\StudyProgramResource;
use Modules\Identity\Resources\UserResource;

class LecturerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'homebase_study_program_id' => $this->homebase_study_program_id,
            'homebase_study_program' => new StudyProgramResource($this->whenLoaded('homebaseStudyProgram')),
            'lecturer_number' => $this->lecturer_number,
            'nidn' => $this->nidn,
            'nidk' => $this->nidk,
            'nip' => $this->nip,
            'full_name' => $this->full_name,
            'gender' => $this->gender instanceof \BackedEnum ? $this->gender->value : $this->gender,
            'birth_place' => $this->birth_place,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'academic_degree' => $this->academic_degree,
            'functional_position' => $this->functional_position,
            'academic_advising_quota' => $this->academic_advising_quota ?? 0,
            'thesis_supervisor_quota' => $this->thesis_supervisor_quota ?? 0,
            'thesis_examiner_quota' => $this->thesis_examiner_quota ?? 0,
            'active_advising_count' => \Modules\Advising\Models\AcademicAdvisor::where('lecturer_id', $this->id)->where('status', 'active')->count(),
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'join_date' => $this->join_date?->format('Y-m-d'),
            'photo_path' => $this->photo_path,
            'notes' => $this->notes,
            'educations' => LecturerEducationResource::collection($this->whenLoaded('educations')),
            'expertises' => LecturerExpertiseResource::collection($this->whenLoaded('expertises')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
