<?php

namespace Modules\Curriculum\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Curriculum\Models\CurriculumSemester;
use Modules\Curriculum\Models\CurriculumSubject;
use Modules\Curriculum\Requests\AddCurriculumSubjectRequest;
use Modules\Curriculum\Resources\CurriculumSubjectResource;
use Modules\Curriculum\Services\CurriculumService;

class CurriculumSemesterController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected CurriculumService $curriculumService
    ) {}

    public function subjects(CurriculumSemester $semester): JsonResponse
    {
        $subjects = $semester->subjects()->with('course')->get();

        return $this->successResponse(
            data: CurriculumSubjectResource::collection($subjects),
            message: 'Curriculum semester subjects retrieved successfully.'
        );
    }

    public function storeSubject(AddCurriculumSubjectRequest $request, CurriculumSemester $semester): JsonResponse
    {
        $subject = $this->curriculumService->addSubject($semester, $request->validated());

        return $this->successResponse(
            data: new CurriculumSubjectResource($subject),
            message: 'Subject added to curriculum semester successfully.',
            code: 201
        );
    }

    public function destroySubject(Request $request, CurriculumSemester $semester, CurriculumSubject $subject): JsonResponse
    {
        if (!$request->user()?->hasPermissionTo('curricula.manage_subjects')) {
            return $this->errorResponse('Unauthorized to remove subject from curriculum.', 403);
        }

        if ($subject->curriculum_semester_id !== $semester->id) {
            return $this->errorResponse('Subject does not belong to this semester.', 422);
        }

        $this->curriculumService->removeSubject($subject);

        return $this->successResponse(
            data: null,
            message: 'Subject removed from curriculum semester successfully.'
        );
    }
}
