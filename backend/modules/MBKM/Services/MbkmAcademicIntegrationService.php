<?php

namespace Modules\MBKM\Services;

use Illuminate\Support\Facades\DB;
use Modules\Assessment\Enums\ComponentType;
use Modules\Assessment\Enums\GradeStatus;
use Modules\Assessment\Enums\SchemeStatus;
use Modules\Assessment\Models\AssessmentComponent;
use Modules\Assessment\Models\AssessmentScheme;
use Modules\Assessment\Models\AssessmentSchemeItem;
use Modules\Assessment\Models\StudentGrade;
use Modules\Class\Enums\ClassStatus;
use Modules\Class\Models\AcademicClass;
use Modules\Enrollment\Enums\EnrollmentItemStatus;
use Modules\Enrollment\Enums\EnrollmentStatus;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Enrollment\Models\StudentEnrollmentItem;
use Modules\Identity\Models\User;
use Modules\MBKM\Models\MbkmRecognition;
use Modules\Student\Models\Student;

/**
 * Integration layer between MBKM recognition and the *existing* academic pipeline.
 *
 * The repository has no separate academic-result / KHS / transcript storage: KHS
 * is derived from `student_enrollments` -> `student_enrollment_items` ->
 * `academic_classes` + `student_grades` (see StudentPortalService::getStudentKHS).
 *
 * Therefore a recognition is pushed into exactly those tables:
 *   recognition approved
 *     -> StudentEnrollment (KRS) for the recognition academic period
 *     -> AcademicClass (section MBKM) for the recognized course
 *     -> AssessmentScheme + component + StudentGrade carrying the MBKM score
 *     -> StudentEnrollmentItem linking the course into the KRS
 *     -> KHS / transcript pick it up through the untouched existing pipeline.
 *
 * No second grade store, no second KHS, no MBKM-specific GPA formula.
 */
class MbkmAcademicIntegrationService
{
    /**
     * Push one approved recognition into the academic result pipeline.
     */
    public function sync(MbkmRecognition $recognition, ?User $actor = null): MbkmRecognition
    {
        return DB::transaction(function () use ($recognition, $actor) {
            $recognition->loadMissing(['participant.student', 'course', 'semester', 'program']);

            $student = $recognition->participant?->student;
            $course = $recognition->course;
            $semesterId = $recognition->semester_id ?? $recognition->program?->semester_id;

            if (!$student) {
                return $this->markNotSynced($recognition, 'Peserta tidak memiliki data mahasiswa.');
            }

            if (!$course) {
                return $this->markNotSynced(
                    $recognition,
                    'Rekognisi belum dipetakan ke mata kuliah sehingga belum dapat masuk ke KRS/KHS.'
                );
            }

            if (!$semesterId) {
                return $this->markNotSynced($recognition, 'Periode akademik rekognisi belum ditentukan.');
            }

            $score = (float) ($recognition->score ?? $recognition->participant?->final_score ?? 0);

            // 1. KRS (enrollment) for the recognition academic period
            $enrollment = StudentEnrollment::firstOrCreate(
                [
                    'student_id' => $student->id,
                    'semester_id' => $semesterId,
                ],
                [
                    'status' => EnrollmentStatus::APPROVED,
                    'total_credits' => 0,
                    'max_credits' => (int) config('mbkm.default_max_credits', 24),
                    'approved_at' => now(),
                    'approved_by' => $actor?->id,
                    'notes' => 'Dibuat otomatis dari rekognisi MBKM.',
                ]
            );

            // An existing draft KRS must not silently swallow the recognition:
            // approve it so the recognition becomes a real academic record.
            if ($enrollment->status === EnrollmentStatus::DRAFT) {
                $enrollment->update([
                    'status' => EnrollmentStatus::APPROVED,
                    'approved_at' => now(),
                    'approved_by' => $actor?->id,
                ]);
            }

            // 2. Academic class representing the MBKM conversion (own section)
            $academicClass = AcademicClass::firstOrCreate(
                [
                    'semester_id' => $semesterId,
                    'course_id' => $course->id,
                    'section' => 'MBKM',
                ],
                [
                    'study_program_id' => $student->study_program_id,
                    'code' => 'MBKM-' . $course->code,
                    'name' => 'Rekognisi MBKM - ' . $course->name,
                    'capacity' => 0,
                    'status' => ClassStatus::COMPLETED,
                    'notes' => 'Kelas konversi MBKM (dibuat otomatis oleh modul MBKM).',
                ]
            );

            // 3. Assessment scheme carrying the MBKM score into the existing scale
            $scheme = AssessmentScheme::firstOrCreate(
                [
                    'academic_class_id' => $academicClass->id,
                    'name' => 'Penilaian Rekognisi MBKM',
                ],
                [
                    'description' => 'Skema penilaian otomatis untuk konversi nilai MBKM.',
                    'status' => SchemeStatus::ACTIVE,
                    'total_weight' => 100,
                    'is_active' => true,
                ]
            );

            if (!$scheme->is_active) {
                $scheme->update(['is_active' => true, 'status' => SchemeStatus::ACTIVE]);
            }

            $component = AssessmentComponent::firstOrCreate(
                [
                    'academic_class_id' => $academicClass->id,
                    'code' => 'MBKM-SCORE',
                ],
                [
                    'name' => 'Nilai MBKM',
                    'type' => ComponentType::OTHER,
                    'max_score' => 100,
                    'is_required' => true,
                    'sequence' => 1,
                ]
            );

            AssessmentSchemeItem::firstOrCreate(
                [
                    'assessment_scheme_id' => $scheme->id,
                    'assessment_component_id' => $component->id,
                ],
                ['weight' => 100]
            );

            $scheme->recalculateTotalWeight();

            // 4. The grade itself, in the existing student_grades table
            StudentGrade::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'academic_class_id' => $academicClass->id,
                    'assessment_component_id' => $component->id,
                ],
                [
                    'score' => $score,
                    'graded_by' => $actor?->id,
                    'graded_at' => now(),
                    'status' => GradeStatus::FINAL,
                    'notes' => 'Nilai konversi MBKM (peserta ' . ($recognition->participant?->participant_number ?? '-') . ').',
                ]
            );

            // 5. Link the course into the KRS
            //    Many-to-one support: several recognitions may target the same
            //    course, so the KRS item carries the aggregated credits.
            $aggregatedCredits = (int) MbkmRecognition::where('participant_id', $recognition->participant_id)
                ->where('course_id', $course->id)
                ->whereIn('status', [
                    \Modules\MBKM\Enums\RecognitionStatus::APPROVED->value,
                    \Modules\MBKM\Enums\RecognitionStatus::LOCKED->value,
                ])
                ->sum('credits');

            $item = StudentEnrollmentItem::updateOrCreate(
                [
                    'enrollment_id' => $enrollment->id,
                    'class_id' => $academicClass->id,
                ],
                [
                    'course_id' => $course->id,
                    'credits' => max((int) $recognition->credits, $aggregatedCredits) ?: (int) $course->credits,
                    'status' => EnrollmentItemStatus::ENROLLED,
                    'finalized_at' => now(),
                    'notes' => 'Rekognisi MBKM #' . $recognition->id,
                ]
            );

            $enrollment->recalculateCredits();

            $recognition->update([
                'academic_enrollment_id' => $enrollment->id,
                'academic_enrollment_item_id' => $item->id,
                'academic_class_id' => $academicClass->id,
                'sync_status' => 'synced',
                'sync_message' => 'Berhasil disinkronkan ke KRS, nilai akademik, dan KHS.',
                'synced_at' => now(),
            ]);

            return $recognition->fresh();
        });
    }

    /**
     * Reverse a previously synced recognition (used by the correction flow).
     */
    public function revert(MbkmRecognition $recognition): MbkmRecognition
    {
        return DB::transaction(function () use ($recognition) {
            if ($recognition->academic_enrollment_item_id) {
                StudentEnrollmentItem::whereKey($recognition->academic_enrollment_item_id)->delete();
            }

            if ($recognition->academic_enrollment_id) {
                StudentEnrollment::find($recognition->academic_enrollment_id)?->recalculateCredits();
            }

            if ($recognition->academic_class_id) {
                StudentGrade::where('academic_class_id', $recognition->academic_class_id)->delete();
            }

            $recognition->update([
                'academic_enrollment_id' => null,
                'academic_enrollment_item_id' => null,
                'academic_class_id' => null,
                'sync_status' => 'reverted',
                'sync_message' => 'Sinkronisasi akademik dibatalkan (koreksi rekognisi).',
                'synced_at' => null,
            ]);

            return $recognition->fresh();
        });
    }

    protected function markNotSynced(MbkmRecognition $recognition, string $message): MbkmRecognition
    {
        $recognition->update([
            'sync_status' => 'not_synced',
            'sync_message' => $message,
        ]);

        return $recognition->fresh();
    }

    /**
     * KHS-facing view of a participant's academic result, built from the same
     * pipeline the student portal uses (no separate calculation).
     *
     * @return array<int, array<string, mixed>>
     */
    public function academicResultForParticipant(\Modules\MBKM\Models\MbkmParticipant $participant): array
    {
        $participant->loadMissing('recognitions.course');

        return $participant->recognitions
            ->map(fn (MbkmRecognition $r) => [
                'recognition_id' => $r->id,
                'course_id' => $r->course_id,
                'course_code' => $r->course?->code,
                'course_name' => $r->course?->name,
                'credits' => $r->credits,
                'score' => $r->score,
                'letter_grade' => $r->letter_grade,
                'grade_point' => $r->grade_point,
                'status' => $r->status instanceof \Modules\MBKM\Enums\RecognitionStatus
                    ? $r->status->value
                    : (string) $r->status,
                'sync_status' => $r->sync_status,
                'enrollment_item_id' => $r->academic_enrollment_item_id,
            ])
            ->values()
            ->all();
    }
}
