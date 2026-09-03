<?php

namespace Modules\Graduation\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Graduation\Models\YudisiumParticipant;
use Modules\Graduation\Models\YudisiumPeriod;
use Modules\Graduation\Services\YudisiumEligibilityService;

class YudisiumEligibilityController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected YudisiumEligibilityService $eligibilityService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'study_program_id' => $request->query('study_program_id'),
            'admission_year' => $request->query('admission_year'),
            'search' => $request->query('search'),
        ];

        $results = $this->eligibilityService->auditAll($filters);

        // Filter by eligibility if requested
        if ($request->has('is_eligible')) {
            $isEligible = filter_var($request->query('is_eligible'), FILTER_VALIDATE_BOOLEAN);
            $results = $results->filter(fn ($item) => $item['is_eligible'] === $isEligible)->values();
        }

        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 10);
        $total = $results->count();

        $items = $results->slice(($page - 1) * $perPage, $perPage)->values();

        return $this->successResponse(
            data: $items,
            message: 'Data kelayakan yudisium berhasil dimuat.',
            meta: [
                'current_page' => $page,
                'last_page' => (int) ceil($total / max($perPage, 1)),
                'per_page' => $perPage,
                'total' => $total,
            ]
        );
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'yudisium_period_id' => ['required', 'integer', 'exists:yudisium_periods,id'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:students,id'],
        ]);

        $period = YudisiumPeriod::findOrFail($validated['yudisium_period_id']);
        $registered = [];

        foreach ($validated['student_ids'] as $studentId) {
            $audit = $this->eligibilityService->auditStudent(\Modules\Student\Models\Student::findOrFail($studentId));

            $participant = YudisiumParticipant::updateOrCreate(
                [
                    'yudisium_period_id' => $period->id,
                    'student_id' => $studentId,
                ],
                [
                    'application_date' => now()->toDateString(),
                    'status' => 'submitted',
                    'total_credits' => $audit['passed_credits'],
                    'gpa' => $audit['passed_gpa'],
                    'study_duration_days' => $audit['study_duration_days'],
                    'thesis_id' => $audit['thesis']?->id,
                ]
            );

            $registered[] = $participant;
        }

        return $this->successResponse(
            data: $registered,
            message: count($registered) . ' mahasiswa berhasil didaftarkan ke Yudisium.',
            code: 201
        );
    }
}
