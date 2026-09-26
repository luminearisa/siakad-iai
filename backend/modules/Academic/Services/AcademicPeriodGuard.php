<?php

namespace Modules\Academic\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Modules\Academic\Models\AcademicYear;
use Modules\Academic\Models\Semester;
use Modules\Attendance\Models\StudentAttendance;
use Modules\Attendance\Models\TeachingSession;
use Modules\Class\Models\AcademicClass;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Graduation\Models\YudisiumPeriod;
use Modules\MBKM\Models\MbkmProgram;
use Modules\MBKM\Models\MbkmRecognition;
use Modules\Schedule\Models\ClassSchedule;
use Modules\Thesis\Models\Thesis;

/**
 * Gap #10 — "Tahun ajaran / semester bisa dihapus padahal masih dipakai".
 *
 * Mengumpulkan data turunan yang terikat pada sebuah periode akademik sehingga
 * controller dapat menolak penghapusan dengan HTTP 409 beserta alasan yang
 * jelas, alih-alih menghapus berantai (cascade/nullOnDelete) secara senyap.
 */
class AcademicPeriodGuard
{
    /**
     * Data turunan yang menghalangi penghapusan satu semester.
     *
     * @return list<array{label: string, count: int, semester: string|null}>
     */
    public function semesterDependencies(Semester $semester): array
    {
        return $this->collectDependencies($semester, $semester->id);
    }

    /**
     * Data turunan seluruh semester milik satu tahun ajaran (transitif).
     *
     * @return list<array{label: string, count: int, semester: string|null}>
     */
    public function academicYearDependencies(AcademicYear $academicYear): array
    {
        $dependencies = [];

        foreach ($academicYear->semesters()->get() as $semester) {
            foreach ($this->collectDependencies($semester, $semester->id) as $dependency) {
                $dependencies[] = $dependency;
            }
        }

        return $dependencies;
    }

    /**
     * @return list<array{label: string, count: int, semester: string|null}>
     */
    private function collectDependencies(Semester $semester, int $semesterId): array
    {
        $name = $semester->name;
        $dependencies = [];

        $add = function (string $label, ?string $model, callable $scope) use (&$dependencies, $name): void {
            $count = $this->count($model, $scope);

            if ($count > 0) {
                $dependencies[] = ['label' => $label, 'count' => $count, 'semester' => $name];
            }
        };

        $add('KRS mahasiswa (student_enrollments)', StudentEnrollment::class,
            fn (Builder $query) => $query->where('semester_id', $semesterId));

        $classIds = $this->classIds($semesterId);

        $add('kelas perkuliahan (academic_classes)', AcademicClass::class,
            fn (Builder $query) => $query->where('semester_id', $semesterId));

        if ($classIds->isNotEmpty()) {
            $add('jadwal kelas (class_schedules)', ClassSchedule::class,
                fn (Builder $query) => $query->whereIn('class_id', $classIds));

            $add('sesi perkuliahan (teaching_sessions)', TeachingSession::class,
                fn (Builder $query) => $query->whereIn('academic_class_id', $classIds));

            $add('presensi mahasiswa (student_attendances)', StudentAttendance::class,
                fn (Builder $query) => $query->whereIn('academic_class_id', $classIds));
        }

        $add('skripsi (theses)', Thesis::class,
            fn (Builder $query) => $query
                ->where('start_semester_id', $semesterId)
                ->orWhere('completion_semester_id', $semesterId));

        $add('periode yudisium (yudisium_periods)', YudisiumPeriod::class,
            fn (Builder $query) => $query->where('semester_id', $semesterId));

        $add('program MBKM (mbkm_programs)', MbkmProgram::class,
            fn (Builder $query) => $query->where('semester_id', $semesterId));

        $add('konversi SKS MBKM (mbkm_recognitions)', MbkmRecognition::class,
            fn (Builder $query) => $query->where('semester_id', $semesterId));

        return $dependencies;
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function classIds(int $semesterId): \Illuminate\Support\Collection
    {
        if (! $this->available(AcademicClass::class)) {
            return collect();
        }

        return AcademicClass::withTrashed()
            ->where('semester_id', $semesterId)
            ->pluck('id');
    }

    private function count(?string $model, callable $scope): int
    {
        if (! $this->available($model)) {
            return 0;
        }

        /** @var Builder $query */
        $query = $model::query();

        if ($this->usesSoftDeletes($model)) {
            $query = $query->withTrashed();
        }

        return (int) $scope($query)->count();
    }

    private function available(?string $model): bool
    {
        if ($model === null || ! class_exists($model)) {
            return false;
        }

        return Schema::hasTable((new $model)->getTable());
    }

    private function usesSoftDeletes(string $model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model), true);
    }

    /**
     * Susun pesan penolakan berbahasa Indonesia dari daftar data penghalang.
     *
     * @param  list<array{label: string, count: int, semester: string|null}>  $dependencies
     */
    public function buildMessage(string $subject, array $dependencies): string
    {
        $details = collect($dependencies)
            ->map(function (array $dependency): string {
                $suffix = $dependency['semester'] !== null
                    ? " pada semester {$dependency['semester']}"
                    : '';

                return "{$dependency['count']} {$dependency['label']}{$suffix}";
            })
            ->implode(', ');

        return "{$subject} tidak dapat dihapus karena masih digunakan oleh {$details}. "
            . 'Nonaktifkan atau arsipkan periode tersebut sebagai gantinya.';
    }
}
