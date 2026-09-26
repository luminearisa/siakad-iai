<?php

namespace Modules\Academic\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Academic\Enums\AcademicStatus;
use Modules\Academic\Models\AcademicYear;
use Modules\Academic\Models\Semester;
use Modules\Academic\Requests\AcademicYearRequest;
use Modules\Academic\Resources\AcademicYearResource;
use Modules\Academic\Services\AcademicPeriodGuard;

class AcademicYearController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly AcademicPeriodGuard $periodGuard
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = AcademicYear::with('semesters');

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['name'],
            filterableColumns: ['status'],
            defaultSort: 'id',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Academic years retrieved successfully.',
            resourceClass: AcademicYearResource::class
        );
    }

    public function store(AcademicYearRequest $request): JsonResponse
    {
        // Status tidak pernah diambil dari default kolom lagi: create biasa selalu
        // menghasilkan tahun ajaran nonaktif (gap #9).
        $data = $request->validated();
        $data['status'] = ($data['status'] ?? null) === AcademicStatus::ACTIVE->value
            ? AcademicStatus::ACTIVE->value
            : AcademicStatus::INACTIVE->value;

        try {
            $academicYear = DB::transaction(function () use ($data) {
                if ($data['status'] === AcademicStatus::ACTIVE->value) {
                    $this->deactivateAllYears();
                }

                return AcademicYear::create($data);
            });
        } catch (QueryException $exception) {
            if ($this->isSingleActiveViolation($exception)) {
                return $this->errorResponse(
                    'Sudah ada tahun ajaran lain yang aktif. Nonaktifkan tahun ajaran tersebut terlebih dahulu.',
                    409
                );
            }

            throw $exception;
        }

        return $this->successResponse(
            data: new AcademicYearResource($academicYear),
            message: 'Academic year created successfully.',
            code: 201
        );
    }

    public function show(AcademicYear $academicYear): JsonResponse
    {
        return $this->successResponse(
            data: new AcademicYearResource($academicYear->load('semesters')),
            message: 'Academic year retrieved successfully.'
        );
    }

    public function update(AcademicYearRequest $request, AcademicYear $academicYear): JsonResponse
    {
        $data = $request->validated();
        $activating = ($data['status'] ?? null) === AcademicStatus::ACTIVE->value;

        try {
            DB::transaction(function () use ($data, $academicYear, $activating) {
                if ($activating) {
                    $this->deactivateAllYears($academicYear->id);
                    // Also ensure active semester matches this academic year if it has semesters
                    $firstSemester = $academicYear->semesters()->orderBy('start_date')->first();
                    if ($firstSemester) {
                        Semester::query()->update(['status' => AcademicStatus::INACTIVE->value]);
                        $firstSemester->update(['status' => AcademicStatus::ACTIVE]);
                    }
                }

                $academicYear->update($data);
            });
        } catch (QueryException $exception) {
            if ($this->isSingleActiveViolation($exception)) {
                return $this->errorResponse(
                    'Sudah ada tahun ajaran lain yang aktif. Nonaktifkan tahun ajaran tersebut terlebih dahulu.',
                    409
                );
            }

            throw $exception;
        }

        return $this->successResponse(
            data: new AcademicYearResource($academicYear->refresh()),
            message: 'Academic year updated successfully.'
        );
    }

    public function setActive(AcademicYear $academicYear): JsonResponse
    {
        DB::transaction(function () use ($academicYear) {
            $this->deactivateAllYears($academicYear->id);
            $academicYear->update(['status' => AcademicStatus::ACTIVE]);

            // Sync semester — aktifkan semester paling awal berdasarkan start_date
            $semester = $academicYear->semesters()->orderBy('start_date')->first();
            if ($semester) {
                Semester::query()->update(['status' => AcademicStatus::INACTIVE->value]);
                $semester->update(['status' => AcademicStatus::ACTIVE]);
            }
        });

        return $this->successResponse(
            data: new AcademicYearResource($academicYear->load('semesters')),
            message: "Tahun ajaran {$academicYear->name} berhasil diaktifkan."
        );
    }

    /**
     * Gap #10 — tolak penghapusan tahun ajaran yang masih dipakai / sedang aktif.
     */
    public function destroy(AcademicYear $academicYear): JsonResponse
    {
        if ($academicYear->status === AcademicStatus::ACTIVE) {
            return $this->errorResponse(
                "Tahun ajaran {$academicYear->name} sedang aktif dan tidak dapat dihapus. "
                . 'Aktifkan tahun ajaran lain terlebih dahulu.',
                409
            );
        }

        $dependencies = $this->periodGuard->academicYearDependencies($academicYear);

        if ($dependencies !== []) {
            return $this->errorResponse(
                $this->periodGuard->buildMessage("Tahun ajaran {$academicYear->name}", $dependencies),
                409,
                ['dependencies' => $dependencies]
            );
        }

        DB::transaction(function () use ($academicYear) {
            // Semester tanpa data turunan ikut terhapus (cascadeOnDelete).
            $academicYear->delete();
        });

        return $this->successResponse(
            data: null,
            message: 'Academic year deleted successfully.'
        );
    }

    private function deactivateAllYears(?int $exceptId = null): void
    {
        AcademicYear::query()
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->update(['status' => AcademicStatus::INACTIVE->value]);
    }

    private function isSingleActiveViolation(QueryException $exception): bool
    {
        return str_contains($exception->getMessage(), 'academic_years_single_active_unique')
            || str_contains($exception->getMessage(), 'active_unique_flag');
    }
}
