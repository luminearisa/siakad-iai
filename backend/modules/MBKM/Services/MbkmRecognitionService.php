<?php

namespace Modules\MBKM\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Academic\Models\Semester;
use Modules\Assessment\Services\GradeCalculationService;
use Modules\Course\Models\Course;
use Modules\Curriculum\Enums\CurriculumStatus;
use Modules\Curriculum\Models\Curriculum;
use Modules\Curriculum\Models\CurriculumSubject;
use Modules\Identity\Models\User;
use Modules\MBKM\Enums\RecognitionStatus;
use Modules\MBKM\Models\MbkmParticipant;
use Modules\MBKM\Models\MbkmRecognition;
use Modules\Student\Models\Student;

/**
 * Recognition / credit conversion.
 *
 * Supports one-to-many (one activity -> many courses) and many-to-one (many
 * activities -> one course) mapping, with full validation before approval and a
 * workflow: draft -> submitted -> reviewed -> approved -> locked.
 *
 * Validation covers: participant validity, course validity, curriculum match,
 * credit sanity, duplicates, academic period, credit ceiling and the locked state.
 */
class MbkmRecognitionService
{
    public function __construct(
        protected MbkmHistoryService $historyService,
        protected MbkmNotificationService $notificationService,
        protected MbkmAcademicIntegrationService $integrationService,
        protected GradeCalculationService $gradeCalculationService,
        protected MbkmParticipantService $participantService,
    ) {}

    /**
     * Create a recognition row after validating every business rule.
     */
    public function create(MbkmParticipant $participant, array $data, User $actor): MbkmRecognition
    {
        return DB::transaction(function () use ($participant, $data, $actor) {
            // Serialise concurrent recognition writes for this participant.
            // Both the duplicate check and the credit-ceiling sum are
            // read-then-write; without this lock two simultaneous creates for
            // the same course both pass and both insert.
            $participant = $this->lockParticipant($participant);

            $participant->loadMissing(['program', 'student.studyProgram']);

            $student = $participant->student;

            if (!$student) {
                throw ValidationException::withMessages([
                    'participant' => ['Peserta tidak memiliki data mahasiswa.'],
                ]);
            }

            $course = null;

            if (!empty($data['course_id'])) {
                $course = Course::find($data['course_id']);

                if (!$course) {
                    throw ValidationException::withMessages([
                        'course_id' => ['Mata kuliah tidak valid.'],
                    ]);
                }

                $this->assertCourseMatchesCurriculum($student, $course, $data['curriculum_id'] ?? null);
                $this->assertNoDuplicate($participant, $course, $data['activity_log_id'] ?? null);
            }

            $semesterId = $data['semester_id'] ?? $participant->program?->semester_id;

            if ($semesterId && !Semester::whereKey($semesterId)->exists()) {
                throw ValidationException::withMessages([
                    'semester_id' => ['Periode akademik tidak valid.'],
                ]);
            }

            $credits = $this->resolveCredits($data['credits'] ?? null, $course);

            $this->assertCreditCeiling($participant, $credits, $course);

            $curriculumId = $data['curriculum_id']
                ?? $this->resolveCurriculum($student)?->id
                ?? null;

            $recognition = MbkmRecognition::create([
                'participant_id' => $participant->id,
                'program_id' => $participant->program_id,
                'activity_log_id' => $data['activity_log_id'] ?? null,
                'source_label' => $data['source_label'] ?? null,
                'course_id' => $course?->id,
                'curriculum_id' => $curriculumId,
                'curriculum_subject_id' => $data['curriculum_subject_id'] ?? $this->resolveCurriculumSubject($student, $course)?->id,
                'credits' => $credits,
                'recognition_type' => $data['recognition_type'] ?? 'course_conversion',
                'score' => $data['score'] ?? null,
                'semester_id' => $semesterId,
                'status' => RecognitionStatus::DRAFT,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
                'sync_status' => 'pending',
            ]);

            $this->historyService->record(
                entity: $recognition,
                action: 'recognition.created',
                toStatus: RecognitionStatus::DRAFT->value,
                notes: 'Rekognisi dibuat.',
                meta: ['credits' => $credits, 'course_id' => $course?->id],
                actorId: $actor->id
            );

            return $recognition->load(['course', 'participant.student']);
        });
    }

    /**
     * Update a recognition row (refused once approved/locked).
     */
    public function update(MbkmRecognition $recognition, array $data, User $actor): MbkmRecognition
    {
        if ($recognition->isLocked()) {
            throw ValidationException::withMessages([
                'status' => ['Rekognisi yang sudah disetujui/terkunci tidak dapat diubah melalui endpoint biasa.'],
            ]);
        }

        // The duplicate check and the credit-ceiling sum are read-then-write, so
        // this has to run inside a transaction holding the participant row —
        // otherwise two concurrent updates can push the total past
        // `max_recognized_credits`, or both move onto the same course.
        return DB::transaction(function () use ($recognition, $data, $actor) {
            $recognition->loadMissing(['participant.student', 'course']);

            if ($recognition->participant) {
                $this->lockParticipant($recognition->participant);
            }

            $student = $recognition->participant?->student;
            $course = $recognition->course;

            if (!empty($data['course_id']) && (int) $data['course_id'] !== (int) $recognition->course_id) {
                $course = Course::find($data['course_id']);

                if (!$course) {
                    throw ValidationException::withMessages(['course_id' => ['Mata kuliah tidak valid.']]);
                }

                if ($student) {
                    $this->assertCourseMatchesCurriculum($student, $course, $data['curriculum_id'] ?? null);
                    $this->assertNoDuplicate($recognition->participant, $course, $data['activity_log_id'] ?? $recognition->activity_log_id, $recognition->id);
                }
            }

            $credits = $this->resolveCredits($data['credits'] ?? $recognition->credits, $course);
            $this->assertCreditCeiling($recognition->participant, $credits, $course, $recognition->id);

            $from = $recognition->status instanceof RecognitionStatus
                ? $recognition->status->value
                : (string) $recognition->status;

            $recognition->update([
                'course_id' => $course?->id,
                'curriculum_id' => $data['curriculum_id'] ?? $recognition->curriculum_id,
                'curriculum_subject_id' => $data['curriculum_subject_id'] ?? $recognition->curriculum_subject_id,
                'activity_log_id' => $data['activity_log_id'] ?? $recognition->activity_log_id,
                'source_label' => $data['source_label'] ?? $recognition->source_label,
                'credits' => $credits,
                'recognition_type' => $data['recognition_type'] ?? $recognition->recognition_type,
                'score' => $data['score'] ?? $recognition->score,
                'semester_id' => $data['semester_id'] ?? $recognition->semester_id,
                'notes' => $data['notes'] ?? $recognition->notes,
            ]);

            $this->historyService->record(
                entity: $recognition,
                action: 'recognition.updated',
                fromStatus: $from,
                toStatus: $from,
                notes: 'Rekognisi diperbarui.',
                actorId: $actor->id
            );

            return $recognition->fresh(['course', 'participant.student']);
        });
    }

    /**
     * Recognition workflow transition.
     */
    public function transition(MbkmRecognition $recognition, string $target, User $actor, ?string $notes = null): MbkmRecognition
    {
        $current = $recognition->status instanceof RecognitionStatus
            ? $recognition->status
            : RecognitionStatus::from((string) $recognition->status);

        $allowed = match ($current) {
            RecognitionStatus::DRAFT => [RecognitionStatus::SUBMITTED],
            RecognitionStatus::SUBMITTED => [RecognitionStatus::REVIEWED, RecognitionStatus::REJECTED, RecognitionStatus::DRAFT],
            RecognitionStatus::REVIEWED => [RecognitionStatus::APPROVED, RecognitionStatus::REJECTED, RecognitionStatus::SUBMITTED],
            RecognitionStatus::APPROVED => [RecognitionStatus::LOCKED],
            RecognitionStatus::REJECTED => [RecognitionStatus::DRAFT],
            RecognitionStatus::LOCKED => [],
        };

        $targetEnum = RecognitionStatus::from($target);

        if (!in_array($targetEnum, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => ["Transisi rekognisi dari {$current->value} ke {$target} tidak diizinkan."],
            ]);
        }

        return DB::transaction(function () use ($recognition, $targetEnum, $current, $actor, $notes) {
            $recognition->loadMissing('participant');

            if ($recognition->participant) {
                $this->lockParticipant($recognition->participant);
            }

            // The transition check above ran on a snapshot. Re-read under the
            // lock, otherwise two concurrent approvals of the same recognition
            // both pass the check and both push the same credits into the KHS.
            $recognition->refresh();

            $fresh = $recognition->status instanceof RecognitionStatus
                ? $recognition->status
                : RecognitionStatus::from((string) $recognition->status);

            if ($fresh !== $current) {
                throw ValidationException::withMessages([
                    'status' => ["Status rekognisi sudah berubah menjadi {$fresh->value}. Muat ulang data lalu coba lagi."],
                ]);
            }

            $payload = ['status' => $targetEnum];

            if ($targetEnum === RecognitionStatus::SUBMITTED) {
                $payload['submitted_at'] = now();
            }

            if ($targetEnum === RecognitionStatus::REVIEWED) {
                $payload['reviewed_at'] = now();
                $payload['reviewed_by'] = $actor->id;
                $payload['review_notes'] = $notes;
            }

            if ($targetEnum === RecognitionStatus::APPROVED) {
                // Re-validate before approval: data may have changed since draft.
                $recognition->loadMissing(['participant', 'course']);

                if ($recognition->course) {
                    $this->assertNoDuplicate(
                        $recognition->participant,
                        $recognition->course,
                        $recognition->activity_log_id,
                        $recognition->id
                    );
                }

                $this->assertCreditCeiling($recognition->participant, (int) $recognition->credits, $recognition->course, $recognition->id);

                $score = $recognition->score ?? $recognition->participant?->final_score;

                if ($score !== null) {
                    $conversion = $this->gradeCalculationService->convertScoreToGrade((float) $score);
                    $payload['score'] = $score;
                    $payload['letter_grade'] = $conversion['letter_grade'];
                    $payload['grade_point'] = $conversion['grade_point'];
                }

                $payload['approved_at'] = now();
                $payload['approved_by'] = $actor->id;
            }

            if ($targetEnum === RecognitionStatus::LOCKED) {
                $payload['locked_at'] = now();
            }

            $recognition->update($payload);

            // On approval, push into the existing academic pipeline.
            if (in_array($targetEnum, [RecognitionStatus::APPROVED, RecognitionStatus::LOCKED], true)) {
                $this->integrationService->sync($recognition, $actor);
                $this->participantService->refreshRecognizedCredits($recognition->participant);
            }

            $this->historyService->record(
                entity: $recognition,
                action: 'recognition.' . $targetEnum->value,
                fromStatus: $current->value,
                toStatus: $targetEnum->value,
                notes: $notes,
                meta: ['credits' => $recognition->credits, 'course_id' => $recognition->course_id],
                actorId: $actor->id
            );

            $recognition->loadMissing('participant.student');

            if (in_array($targetEnum, [RecognitionStatus::APPROVED, RecognitionStatus::LOCKED], true)) {
                $this->notificationService->notifyStudent(
                    $recognition->participant?->student,
                    'recognition.approved',
                    'Rekognisi SKS Disetujui',
                    'Rekognisi ' . ($recognition->course?->name ?? 'aktivitas MBKM') . " sebesar {$recognition->credits} SKS telah disetujui dan masuk ke hasil studi Anda.",
                    ['recognition_id' => $recognition->id]
                );
            }

            return $recognition->fresh(['course', 'participant.student']);
        });
    }

    /**
     * Correct an approved recognition: revert the academic sync, unlock, and keep
     * the full audit trail of the correction.
     */
    public function requestCorrection(MbkmRecognition $recognition, User $actor, string $reason): MbkmRecognition
    {
        if (!$recognition->isLocked()) {
            throw ValidationException::withMessages([
                'status' => ['Hanya rekognisi yang sudah disetujui/terkunci yang dapat dikoreksi.'],
            ]);
        }

        return DB::transaction(function () use ($recognition, $actor, $reason) {
            $from = $recognition->status instanceof RecognitionStatus
                ? $recognition->status->value
                : (string) $recognition->status;

            $this->integrationService->revert($recognition);

            $recognition->update([
                'status' => RecognitionStatus::DRAFT,
                'locked_at' => null,
                'review_notes' => $reason,
            ]);

            $this->historyService->record(
                entity: $recognition,
                action: 'recognition.correction_requested',
                fromStatus: $from,
                toStatus: RecognitionStatus::DRAFT->value,
                notes: $reason,
                actorId: $actor->id
            );

            $this->participantService->refreshRecognizedCredits($recognition->participant);

            return $recognition->fresh(['course']);
        });
    }

    /**
     * Totals + per-source breakdown of a participant's recognition.
     *
     * @return array{total_credits: int, approved_credits: int, pending_credits: int, items: array<int, array<string, mixed>>}
     */
    public function summary(MbkmParticipant $participant): array
    {
        $rows = $participant->recognitions()->with('course')->get();

        $approved = $rows->filter(fn (MbkmRecognition $r) => in_array(
            $r->status instanceof RecognitionStatus ? $r->status->value : (string) $r->status,
            [RecognitionStatus::APPROVED->value, RecognitionStatus::LOCKED->value],
            true
        ));

        return [
            'total_credits' => (int) $rows->sum('credits'),
            'approved_credits' => (int) $approved->sum('credits'),
            'pending_credits' => (int) $rows->diff($approved)->sum('credits'),
            'items' => $rows->map(fn (MbkmRecognition $r) => [
                'id' => $r->id,
                'source_label' => $r->source_label,
                'course_id' => $r->course_id,
                'course_code' => $r->course?->code,
                'course_name' => $r->course?->name,
                'credits' => $r->credits,
                'score' => $r->score,
                'letter_grade' => $r->letter_grade,
                'grade_point' => $r->grade_point,
                'status' => $r->status instanceof RecognitionStatus ? $r->status->value : (string) $r->status,
                'recognition_type' => $r->recognition_type instanceof \Modules\MBKM\Enums\RecognitionType
                    ? $r->recognition_type->value
                    : (string) $r->recognition_type,
                'sync_status' => $r->sync_status,
            ])->values()->all(),
        ];
    }

    // -----------------------------------------------------------------------
    // Validation helpers
    // -----------------------------------------------------------------------

    /**
     * The recognized course must belong to the student's curriculum (and to the
     * curriculum explicitly supplied, if any).
     */
    protected function assertCourseMatchesCurriculum(Student $student, Course $course, ?int $curriculumId = null): void
    {
        $curriculum = $curriculumId
            ? Curriculum::find($curriculumId)
            : $this->resolveCurriculum($student);

        if ($curriculumId && !$curriculum) {
            throw ValidationException::withMessages([
                'curriculum_id' => ['Kurikulum tidak valid.'],
            ]);
        }

        if ($curriculum) {
            if ((int) $curriculum->study_program_id !== (int) $student->study_program_id) {
                throw ValidationException::withMessages([
                    'curriculum_id' => ['Kurikulum tidak sesuai dengan program studi mahasiswa.'],
                ]);
            }

            $belongsToCurriculum = CurriculumSubject::whereHas('curriculumSemester', function ($q) use ($curriculum) {
                $q->where('curriculum_id', $curriculum->id);
            })->where('course_id', $course->id)->exists();

            if (!$belongsToCurriculum) {
                throw ValidationException::withMessages([
                    'course_id' => ['Mata kuliah tidak terdaftar pada kurikulum mahasiswa.'],
                ]);
            }

            return;
        }

        // No curriculum resolvable: at minimum the course must belong to the
        // student's study program (or be a shared/university-wide course).
        if ($course->study_program_id && (int) $course->study_program_id !== (int) $student->study_program_id) {
            throw ValidationException::withMessages([
                'course_id' => ['Mata kuliah berasal dari program studi lain.'],
            ]);
        }
    }

    /**
     * Prevent duplicate recognition of the same source for the same course while
     * still allowing many-to-one (different activities -> same course).
     */
    /**
     * Take a row lock on the participant for the rest of the transaction.
     *
     * The participant row is chosen deliberately: it always exists, so
     * `lockForUpdate()` is deterministic. Locking the *recognition* row being
     * inserted would not work (there is nothing to lock yet), and relying on
     * gap locks over `mbkm_recognitions` is engine- and isolation-level
     * dependent.
     */
    protected function lockParticipant(MbkmParticipant $participant): MbkmParticipant
    {
        return MbkmParticipant::whereKey($participant->id)->lockForUpdate()->firstOrFail();
    }

    protected function assertNoDuplicate(
        MbkmParticipant $participant,
        Course $course,
        ?int $activityLogId = null,
        ?int $ignoreId = null
    ): void {
        $query = MbkmRecognition::where('participant_id', $participant->id)
            ->where('course_id', $course->id)
            ->where('status', '!=', RecognitionStatus::REJECTED->value);

        if ($activityLogId) {
            $query->where('activity_log_id', $activityLogId);
        } else {
            $query->whereNull('activity_log_id');
        }

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'course_id' => ['Rekognisi untuk mata kuliah ini sudah ada (duplikat).'],
            ]);
        }
    }

    protected function resolveCredits(?int $credits, ?Course $course): int
    {
        $resolved = $credits ?? (int) ($course?->credits ?? 0);

        if ($resolved < 1) {
            throw ValidationException::withMessages([
                'credits' => ['SKS rekognisi harus lebih besar dari 0.'],
            ]);
        }

        return $resolved;
    }

    /**
     * Total recognized credits may not exceed the program ceiling.
     */
    protected function assertCreditCeiling(
        MbkmParticipant $participant,
        int $credits,
        ?Course $course = null,
        ?int $ignoreId = null
    ): void {
        $participant->loadMissing('program');

        $ceiling = $participant->program?->max_recognized_credits;

        if ($ceiling === null) {
            return;
        }

        $query = MbkmRecognition::where('participant_id', $participant->id)
            ->where('status', '!=', RecognitionStatus::REJECTED->value);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        $existing = (int) $query->sum('credits');

        if (($existing + $credits) > $ceiling) {
            throw ValidationException::withMessages([
                'credits' => ["Total SKS rekognisi melebihi batas program ({$ceiling} SKS)."],
            ]);
        }

        // Credits may not exceed the course's own weight.
        if ($course && $credits > (int) $course->credits) {
            throw ValidationException::withMessages([
                'credits' => ["SKS rekognisi tidak boleh melebihi SKS mata kuliah ({$course->credits} SKS)."],
            ]);
        }
    }

    protected function resolveCurriculum(Student $student): ?Curriculum
    {
        if (!$student->study_program_id) {
            return null;
        }

        return Curriculum::where('study_program_id', $student->study_program_id)
            ->where('status', CurriculumStatus::ACTIVE)
            ->first()
            ?? Curriculum::where('study_program_id', $student->study_program_id)->orderByDesc('id')->first();
    }

    protected function resolveCurriculumSubject(Student $student, ?Course $course): ?CurriculumSubject
    {
        if (!$course) {
            return null;
        }

        $curriculum = $this->resolveCurriculum($student);

        if (!$curriculum) {
            return null;
        }

        return CurriculumSubject::whereHas('curriculumSemester', function ($q) use ($curriculum) {
            $q->where('curriculum_id', $curriculum->id);
        })->where('course_id', $course->id)->first();
    }
}
