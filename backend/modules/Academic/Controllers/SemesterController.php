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
use Modules\Academic\Requests\SemesterRequest;
use Modules\Academic\Resources\SemesterResource;
use Modules\Academic\Services\AcademicPeriodGuard;

class SemesterController extends Controller
{
    use HasApiResponse;

    public function __construct(
        private readonly AcademicPeriodGuard $periodGuard
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Semester::with('academicYear');

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['name'],
            filterableColumns: ['status', 'type', 'academic_year_id'],
            defaultSort: 'id',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Semesters retrieved successfully.',
            resourceClass: SemesterResource::class
        );
    }

    public function store(SemesterRequest $request): JsonResponse
    {
        // Status tidak pernah diambil dari default kolom lagi: create biasa selalu
        // menghasilkan semester nonaktif (gap #9).
        $data = $request->validated();
        $data['status'] = ($data['status'] ?? null) === AcademicStatus::ACTIVE->value
            ? AcademicStatus::ACTIVE->value
            : AcademicStatus::INACTIVE->value;

        try {
            $semester = DB::transaction(function () use ($data) {
                if ($data['status'] === AcademicStatus::ACTIVE->value) {
                    $this->activatePeriod($data['academic_year_id'] ?? null);
                }

                return Semester::create($data);
            });
        } catch (QueryException $exception) {
            if ($this->isSingleActiveViolation($exception)) {
                return $this->errorResponse(
                    'Sudah ada semester lain yang aktif. Nonaktifkan semester tersebut terlebih dahulu.',
                    409
                );
            }

            throw $exception;
        }

        return $this->successResponse(
            data: new SemesterResource($semester->load('academicYear')),
            message: 'Semester created successfully.',
            code: 201
        );
    }

    public function show(Semester $semester): JsonResponse
    {
        return $this->successResponse(
            data: new SemesterResource($semester->load('academicYear')),
            message: 'Semester retrieved successfully.'
        );
    }

    public function update(SemesterRequest $request, Semester $semester): JsonResponse
    {
        $data = $request->validated();
        $activating = ($data['status'] ?? null) === AcademicStatus::ACTIVE->value;
        $academicYearId = $data['academic_year_id'] ?? $semester->academic_year_id;

        try {
            DB::transaction(function () use ($data, $semester, $activating, $academicYearId) {
                if ($activating) {
                    $this->activatePeriod($academicYearId, $semester->id);
                }

                $semester->update($data);
            });
        } catch (QueryException $exception) {
            if ($this->isSingleActiveViolation($exception)) {
                return $this->errorResponse(
                    'Sudah ada semester lain yang aktif. Nonaktifkan semester tersebut terlebih dahulu.',
                    409
                );
            }

            throw $exception;
        }

        return $this->successResponse(
            data: new SemesterResource($semester->refresh()->load('academicYear')),
            message: 'Semester updated successfully.'
        );
    }

    public function setActive(Semester $semester): JsonResponse
    {
        DB::transaction(function () use ($semester) {
            Semester::where('id', '!=', $semester->id)
                ->update(['status' => AcademicStatus::INACTIVE->value]);
            $semester->update(['status' => AcademicStatus::ACTIVE]);

            if ($semester->academic_year_id) {
                AcademicYear::where('id', '!=', $semester->academic_year_id)
                    ->update(['status' => AcademicStatus::INACTIVE->value]);
                AcademicYear::where('id', $semester->academic_year_id)
                    ->update(['status' => AcademicStatus::ACTIVE->value]);
            }
        });

        return $this->successResponse(
            data: new SemesterResource($semester->load('academicYear')),
            message: "Semester {$semester->name} berhasil diaktifkan."
        );
    }

    /**
     * Gap #10 — tolak penghapusan semester yang masih dipakai / sedang aktif.
     */
    public function destroy(Semester $semester): JsonResponse
    {
        if ($semester->status === AcademicStatus::ACTIVE) {
            return $this->errorResponse(
                "Semester {$semester->name} sedang aktif dan tidak dapat dihapus. "
                . 'Aktifkan semester lain terlebih dahulu.',
                409
            );
        }

        $dependencies = $this->periodGuard->semesterDependencies($semester);

        if ($dependencies !== []) {
            return $this->errorResponse(
                $this->periodGuard->buildMessage("Semester {$semester->name}", $dependencies),
                409,
                ['dependencies' => $dependencies]
            );
        }

        $semester->delete();

        return $this->successResponse(
            data: null,
            message: 'Semester deleted successfully.'
        );
    }

    /**
     * Nonaktifkan seluruh semester/tahun ajaran lain, lalu aktifkan tahun ajaran
     * induk — semuanya dalam satu transaksi agar atomik.
     */
    private function activatePeriod(?int $academicYearId, ?int $exceptSemesterId = null): void
    {
        Semester::query()
            ->when($exceptSemesterId !== null, fn ($query) => $query->where('id', '!=', $exceptSemesterId))
            ->update(['status' => AcademicStatus::INACTIVE->value]);

        if ($academicYearId) {
            AcademicYear::where('id', '!=', $academicYearId)
                ->update(['status' => AcademicStatus::INACTIVE->value]);
            AcademicYear::where('id', $academicYearId)
                ->update(['status' => AcademicStatus::ACTIVE->value]);
        }
    }

    private function isSingleActiveViolation(QueryException $exception): bool
    {
        return str_contains($exception->getMessage(), 'semesters_single_active_unique')
            || str_contains($exception->getMessage(), 'active_unique_flag');
    }
}
