<?php

namespace Modules\Enrollment\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Audit\Services\AuditService;
use Modules\Class\Enums\ClassStatus;
use Modules\Class\Models\AcademicClass;
use Modules\Enrollment\Models\KrsPackage;
use Modules\Enrollment\Models\StudentEnrollment;

class LoadKrsPackageAction
{
    public function __construct(
        protected AddEnrollmentItemAction $addEnrollmentItemAction
    ) {}

    /**
     * Apply a KRS package template to an active enrollment.
     * Uses partial-load strategy: adds MK that are available, collects failures.
     *
     * @return array{added: string[], failed: array{course: string, reason: string}[]}
     * @throws ValidationException
     */
    public function execute(StudentEnrollment $enrollment, int $packageId): array
    {
        if (!$enrollment->isEditable()) {
            throw ValidationException::withMessages([
                'enrollment' => ["Tidak dapat memuat paket KRS: status enrollment saat ini adalah '{$enrollment->status->value}'. Hanya enrollment dengan status draft atau revision_required yang dapat dimodifikasi."],
            ]);
        }

        $package = KrsPackage::with('items.course')->findOrFail($packageId);

        // Pastikan paket sesuai dengan prodi mahasiswa
        $studentProgramId = $enrollment->student->study_program_id ?? null;
        if ($studentProgramId && $package->study_program_id !== $studentProgramId) {
            throw ValidationException::withMessages([
                'krs_package_id' => ["Paket KRS '{$package->name}' bukan untuk program studi mahasiswa ini."],
            ]);
        }

        $results = ['added' => [], 'failed' => []];

        foreach ($package->items as $packageItem) {
            $courseName = $packageItem->course?->name ?? "Mata Kuliah #{$packageItem->course_id}";

            // Cari kelas yang buka di semester enrollment untuk MK ini
            $class = AcademicClass::where('course_id', $packageItem->course_id)
                ->where('semester_id', $enrollment->semester_id)
                ->where('status', ClassStatus::OPEN)
                ->first();

            if (!$class) {
                $results['failed'][] = [
                    'course' => $courseName,
                    'reason' => 'Tidak ada kelas yang sedang buka (Open) untuk mata kuliah ini di semester aktif.',
                ];
                continue;
            }

            try {
                // bypass_curriculum = true karena paket sudah dikurasi oleh admin
                $this->addEnrollmentItemAction->execute($enrollment, $class->id, null, true);
                $results['added'][] = $courseName;
            } catch (\Throwable $e) {
                $results['failed'][] = [
                    'course'  => $courseName,
                    'reason'  => $e->getMessage(),
                ];
            }
        }

        AuditService::log(
            action: 'package_loaded',
            module: 'Enrollment',
            description: "KRS Package '{$package->name}' loaded into enrollment #{$enrollment->id}. Added: " . count($results['added']) . ", Failed: " . count($results['failed']) . ".",
            entity: $enrollment,
            oldValues: null,
            newValues: ['package_id' => $packageId, 'added_count' => count($results['added'])]
        );

        return $results;
    }
}
