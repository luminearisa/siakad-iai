<?php

namespace Modules\Enrollment\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Academic\Models\Semester;
use Modules\Class\Models\AcademicClass;
use Modules\Enrollment\Enums\EnrollmentItemStatus;
use Modules\Enrollment\Enums\EnrollmentStatus;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Identity\Models\User;
use Modules\Student\Models\Student;

class EnrollmentSeeder extends Seeder
{
    public function run(): void
    {
        $activeSemester = Semester::where('status', 'active')->first() ?? Semester::first();
        if (!$activeSemester) {
            return;
        }

        $approver = User::where('email', 'admin@siakad.ac.id')->first();
        $dosenUser = User::where('email', 'dosen@siakad.ac.id')->first();

        $std1 = Student::where('student_number', '202501001')->first(); // Budi Santoso (PAI)
        $std2 = Student::where('student_number', '202501002')->first(); // Ahmad Dahlan (PAI)
        $std3 = Student::where('student_number', '202501003')->first(); // Fatimah (PAI)
        $std4 = Student::where('student_number', '202502001')->first(); // Aisyah (PBA)
        $std5 = Student::where('student_number', '202503001')->first(); // Farhan (HKI)
        $std6 = Student::where('student_number', '202504001')->first(); // Rizky (ES)

        $classPai201 = AcademicClass::where('code', 'PAI201-A')->first();
        $classMku101 = AcademicClass::where('code', 'MKU101-A')->first();
        $classMku102 = AcademicClass::where('code', 'MKU102-A')->first();
        $classMku103 = AcademicClass::where('code', 'MKU103-A')->first();
        $classMku105 = AcademicClass::where('code', 'MKU105-A')->first();
        $classPai203 = AcademicClass::where('code', 'PAI203-A')->first();
        $classHki201 = AcademicClass::where('code', 'HKI201-A')->first();
        $classEs201 = AcademicClass::where('code', 'ES201-A')->first();

        // 1. Budi Santoso (202501001) -> APPROVED (7 SKS)
        if ($std1) {
            $enr1 = StudentEnrollment::firstOrCreate(
                [
                    'student_id' => $std1->id,
                    'semester_id' => $activeSemester->id,
                ],
                [
                    'status' => EnrollmentStatus::APPROVED,
                    'total_credits' => 7,
                    'submitted_at' => now()->subDays(10),
                    'approved_at' => now()->subDays(9),
                    'approved_by' => $dosenUser?->id ?: $approver?->id,
                    'notes' => 'KRS Semester Ganjil 2025/2026 Disetujui Dosen PA.',
                ]
            );

            if ($classPai201) {
                $enr1->items()->firstOrCreate(
                    ['class_id' => $classPai201->id],
                    ['course_id' => $classPai201->course_id, 'credits' => 3, 'status' => EnrollmentItemStatus::ENROLLED]
                );
            }
            if ($classMku101) {
                $enr1->items()->firstOrCreate(
                    ['class_id' => $classMku101->id],
                    ['course_id' => $classMku101->course_id, 'credits' => 2, 'status' => EnrollmentItemStatus::ENROLLED]
                );
            }
            if ($classMku102) {
                $enr1->items()->firstOrCreate(
                    ['class_id' => $classMku102->id],
                    ['course_id' => $classMku102->course_id, 'credits' => 2, 'status' => EnrollmentItemStatus::ENROLLED]
                );
            }
            $enr1->recalculateCredits();
        }

        // 2. Ahmad Dahlan (202501002) -> SUBMITTED (Pending Review Dosen PA) (9 SKS)
        if ($std2) {
            $enr2 = StudentEnrollment::firstOrCreate(
                [
                    'student_id' => $std2->id,
                    'semester_id' => $activeSemester->id,
                ],
                [
                    'status' => EnrollmentStatus::SUBMITTED,
                    'total_credits' => 9,
                    'submitted_at' => now()->subHours(12),
                    'notes' => 'Pengajuan KRS Semester Gasal menunggu verifikasi Dosen Pembimbing Akademik.',
                ]
            );

            if ($classPai201) {
                $enr2->items()->firstOrCreate(
                    ['class_id' => $classPai201->id],
                    ['course_id' => $classPai201->course_id, 'credits' => 3, 'status' => EnrollmentItemStatus::ENROLLED]
                );
            }
            if ($classMku101) {
                $enr2->items()->firstOrCreate(
                    ['class_id' => $classMku101->id],
                    ['course_id' => $classMku101->course_id, 'credits' => 2, 'status' => EnrollmentItemStatus::ENROLLED]
                );
            }
            if ($classMku103) {
                $enr2->items()->firstOrCreate(
                    ['class_id' => $classMku103->id],
                    ['course_id' => $classMku103->course_id, 'credits' => 2, 'status' => EnrollmentItemStatus::ENROLLED]
                );
            }
            if ($classMku105) {
                $enr2->items()->firstOrCreate(
                    ['class_id' => $classMku105->id],
                    ['course_id' => $classMku105->course_id, 'credits' => 2, 'status' => EnrollmentItemStatus::ENROLLED]
                );
            }
            $enr2->recalculateCredits();
        }

        // 3. Fatimah Az-Zahra (202501003) -> APPROVED (10 SKS)
        if ($std3) {
            $enr3 = StudentEnrollment::firstOrCreate(
                [
                    'student_id' => $std3->id,
                    'semester_id' => $activeSemester->id,
                ],
                [
                    'status' => EnrollmentStatus::APPROVED,
                    'total_credits' => 10,
                    'submitted_at' => now()->subDays(8),
                    'approved_at' => now()->subDays(7),
                    'approved_by' => $dosenUser?->id ?: $approver?->id,
                    'notes' => 'KRS telah disetujui.',
                ]
            );

            if ($classPai201) {
                $enr3->items()->firstOrCreate(
                    ['class_id' => $classPai201->id],
                    ['course_id' => $classPai201->course_id, 'credits' => 3, 'status' => EnrollmentItemStatus::ENROLLED]
                );
            }
            if ($classPai203) {
                $enr3->items()->firstOrCreate(
                    ['class_id' => $classPai203->id],
                    ['course_id' => $classPai203->course_id, 'credits' => 3, 'status' => EnrollmentItemStatus::ENROLLED]
                );
            }
            if ($classMku101) {
                $enr3->items()->firstOrCreate(
                    ['class_id' => $classMku101->id],
                    ['course_id' => $classMku101->course_id, 'credits' => 2, 'status' => EnrollmentItemStatus::ENROLLED]
                );
            }
            if ($classMku102) {
                $enr3->items()->firstOrCreate(
                    ['class_id' => $classMku102->id],
                    ['course_id' => $classMku102->course_id, 'credits' => 2, 'status' => EnrollmentItemStatus::ENROLLED]
                );
            }
            $enr3->recalculateCredits();
        }

        // 4. Aisyah Rahmawati (PBA) -> APPROVED (7 SKS)
        if ($std4) {
            $enr4 = StudentEnrollment::firstOrCreate(
                [
                    'student_id' => $std4->id,
                    'semester_id' => $activeSemester->id,
                ],
                [
                    'status' => EnrollmentStatus::APPROVED,
                    'total_credits' => 7,
                    'submitted_at' => now()->subDays(5),
                    'approved_at' => now()->subDays(4),
                    'approved_by' => $approver?->id,
                    'notes' => 'Disetujui Pembimbing PBA.',
                ]
            );

            if ($classMku103) {
                $enr4->items()->firstOrCreate(
                    ['class_id' => $classMku103->id],
                    ['course_id' => $classMku103->course_id, 'credits' => 2, 'status' => EnrollmentItemStatus::ENROLLED]
                );
            }
            if ($classMku101) {
                $enr4->items()->firstOrCreate(
                    ['class_id' => $classMku101->id],
                    ['course_id' => $classMku101->course_id, 'credits' => 2, 'status' => EnrollmentItemStatus::ENROLLED]
                );
            }
            if ($classPai201) {
                $enr4->items()->firstOrCreate(
                    ['class_id' => $classPai201->id],
                    ['course_id' => $classPai201->course_id, 'credits' => 3, 'status' => EnrollmentItemStatus::ENROLLED]
                );
            }
            $enr4->recalculateCredits();
        }

        // 5. Farhan (HKI) -> APPROVED (5 SKS)
        if ($std5) {
            $enr5 = StudentEnrollment::firstOrCreate(
                [
                    'student_id' => $std5->id,
                    'semester_id' => $activeSemester->id,
                ],
                [
                    'status' => EnrollmentStatus::APPROVED,
                    'total_credits' => 5,
                    'submitted_at' => now()->subDays(6),
                    'approved_at' => now()->subDays(5),
                    'approved_by' => $approver?->id,
                    'notes' => 'Disetujui Dosen PA HKI.',
                ]
            );

            if ($classHki201) {
                $enr5->items()->firstOrCreate(
                    ['class_id' => $classHki201->id],
                    ['course_id' => $classHki201->course_id, 'credits' => 3, 'status' => EnrollmentItemStatus::ENROLLED]
                );
            }
            if ($classMku101) {
                $enr5->items()->firstOrCreate(
                    ['class_id' => $classMku101->id],
                    ['course_id' => $classMku101->course_id, 'credits' => 2, 'status' => EnrollmentItemStatus::ENROLLED]
                );
            }
            $enr5->recalculateCredits();
        }

        // 6. Rizky (ES) -> APPROVED (5 SKS)
        if ($std6) {
            $enr6 = StudentEnrollment::firstOrCreate(
                [
                    'student_id' => $std6->id,
                    'semester_id' => $activeSemester->id,
                ],
                [
                    'status' => EnrollmentStatus::APPROVED,
                    'total_credits' => 5,
                    'submitted_at' => now()->subDays(4),
                    'approved_at' => now()->subDays(3),
                    'approved_by' => $approver?->id,
                    'notes' => 'Disetujui Dosen PA FEBI.',
                ]
            );

            if ($classEs201) {
                $enr6->items()->firstOrCreate(
                    ['class_id' => $classEs201->id],
                    ['course_id' => $classEs201->course_id, 'credits' => 3, 'status' => EnrollmentItemStatus::ENROLLED]
                );
            }
            if ($classMku102) {
                $enr6->items()->firstOrCreate(
                    ['class_id' => $classMku102->id],
                    ['course_id' => $classMku102->course_id, 'credits' => 2, 'status' => EnrollmentItemStatus::ENROLLED]
                );
            }
            $enr6->recalculateCredits();
        }

        // Update enrolled count for each academic class
        foreach (AcademicClass::all() as $cls) {
            $count = \Modules\Enrollment\Models\StudentEnrollmentItem::where('class_id', $cls->id)->count();
            $cls->update(['enrolled_count' => $count]);
        }
    }
}
