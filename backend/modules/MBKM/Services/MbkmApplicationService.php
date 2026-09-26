<?php

namespace Modules\MBKM\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Models\User;
use Modules\MBKM\Enums\ApplicationStatus;
use Modules\MBKM\Models\MbkmApplication;
use Modules\MBKM\Models\MbkmDocument;
use Modules\MBKM\Models\MbkmProgram;
use Modules\Student\Models\Student;

/**
 * Application lifecycle: create draft -> submit (full server-side validation) ->
 * verify -> decision, plus student withdrawal. Applications are historical
 * records and are never deleted.
 */
class MbkmApplicationService
{
    public function __construct(
        protected MbkmEligibilityService $eligibilityService,
        protected MbkmHistoryService $historyService,
        protected MbkmNotificationService $notificationService,
    ) {}

    /**
     * Create (or return the existing draft of) an application for a student.
     */
    public function create(MbkmProgram $program, Student $student, array $data, ?User $actor = null): MbkmApplication
    {
        return DB::transaction(function () use ($program, $student, $data, $actor) {
            $existing = MbkmApplication::where('program_id', $program->id)
                ->where('student_id', $student->id)
                ->whereIn('status', ApplicationStatus::activeStatuses())
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $application = MbkmApplication::create([
                'registration_number' => $this->generateRegistrationNumber($program),
                'program_id' => $program->id,
                'student_id' => $student->id,
                'motivation_statement' => $data['motivation_statement'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => ApplicationStatus::DRAFT,
                'academic_snapshot' => $this->eligibilityService->academicSnapshot($student),
            ]);

            $this->historyService->record(
                entity: $application,
                action: 'application.created',
                toStatus: ApplicationStatus::DRAFT->value,
                notes: 'Pendaftaran MBKM dibuat.',
                meta: ['program_id' => $program->id],
                actorId: $actor?->id
            );

            return $application;
        });
    }

    /**
     * Submit an application after running every server-side validation.
     *
     * @throws ValidationException
     */
    public function submit(MbkmApplication $application, ?User $actor = null): MbkmApplication
    {
        $application->loadMissing(['program.requirements', 'student.studyProgram', 'documents']);

        $program = $application->program;
        $student = $application->student;

        if (!$program || !$student) {
            throw ValidationException::withMessages([
                'application' => ['Data program atau mahasiswa tidak ditemukan.'],
            ]);
        }

        $statusValue = $application->status instanceof ApplicationStatus
            ? $application->status->value
            : (string) $application->status;

        if (!in_array($statusValue, [ApplicationStatus::DRAFT->value, ApplicationStatus::REVISION_REQUIRED->value], true)) {
            throw ValidationException::withMessages([
                'status' => ['Pendaftaran dengan status ' . $statusValue . ' tidak dapat diajukan.'],
            ]);
        }

        if (!$program->isRegistrationOpen()) {
            throw ValidationException::withMessages([
                'program' => ['Pendaftaran program ini sudah ditutup.'],
            ]);
        }

        // Duplicate active application for the same program
        $duplicate = MbkmApplication::where('program_id', $program->id)
            ->where('student_id', $student->id)
            ->where('id', '!=', $application->id)
            ->whereIn('status', ApplicationStatus::activeStatuses())
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'application' => ['Anda sudah memiliki pendaftaran aktif pada program ini.'],
            ]);
        }

        $uploadedCodes = $application->documents->pluck('category')->filter()->unique()->values()->all();

        $evaluation = $this->eligibilityService->evaluate(
            program: $program,
            student: $student,
            enforceDocuments: $program->requires_documents,
            uploadedDocumentCodes: $uploadedCodes,
        );

        if (!$evaluation['is_eligible']) {
            throw ValidationException::withMessages([
                'eligibility' => $evaluation['reasons'],
            ]);
        }

        $application->update([
            'status' => ApplicationStatus::SUBMITTED,
            'submitted_at' => now(),
            'academic_snapshot' => $evaluation['snapshot'],
        ]);

        $this->historyService->record(
            entity: $application,
            action: 'application.submitted',
            fromStatus: $statusValue,
            toStatus: ApplicationStatus::SUBMITTED->value,
            notes: 'Pendaftaran diajukan oleh mahasiswa.',
            meta: ['snapshot' => $evaluation['snapshot']],
            actorId: $actor?->id
        );

        $this->notificationService->notifyPermissionHolders(
            'mbkm.application.verify',
            'application.submitted',
            'Pendaftaran MBKM Baru',
            "{$student->full_name} mendaftar pada program {$program->name}.",
            ['application_id' => $application->id, 'program_id' => $program->id]
        );

        return $application->fresh(['program', 'student']);
    }

    /**
     * Administrative + academic verification by staff.
     */
    public function verify(
        MbkmApplication $application,
        User $verifier,
        string $decision,
        ?string $notes = null
    ): MbkmApplication {
        $application->loadMissing(['program', 'student']);

        $allowed = [
            ApplicationStatus::VERIFIED->value,
            ApplicationStatus::REJECTED->value,
            ApplicationStatus::REVISION_REQUIRED->value,
        ];

        if (!in_array($decision, $allowed, true)) {
            throw ValidationException::withMessages([
                'decision' => ['Keputusan verifikasi tidak valid.'],
            ]);
        }

        $from = $application->status instanceof ApplicationStatus
            ? $application->status->value
            : (string) $application->status;

        $application->update([
            'status' => $decision,
            'verified_at' => now(),
            'verified_by' => $verifier->id,
            'verification_notes' => $notes,
        ]);

        $this->historyService->record(
            entity: $application,
            action: 'application.verified',
            fromStatus: $from,
            toStatus: $decision,
            notes: $notes,
            actorId: $verifier->id
        );

        $titles = [
            ApplicationStatus::VERIFIED->value => 'Pendaftaran MBKM Terverifikasi',
            ApplicationStatus::REJECTED->value => 'Pendaftaran MBKM Ditolak',
            ApplicationStatus::REVISION_REQUIRED->value => 'Pendaftaran MBKM Perlu Perbaikan',
        ];

        $this->notificationService->notifyStudent(
            $application->student,
            'application.' . $decision,
            $titles[$decision] ?? 'Status Pendaftaran MBKM',
            "Pendaftaran Anda pada program {$application->program?->name} kini berstatus: " . $decision . '.',
            ['application_id' => $application->id]
        );

        return $application->fresh(['program', 'student']);
    }

    /**
     * Student withdraws their own application (historical record is preserved).
     */
    public function withdraw(MbkmApplication $application, string $reason, ?User $actor = null): MbkmApplication
    {
        $from = $application->status instanceof ApplicationStatus
            ? $application->status->value
            : (string) $application->status;

        if (in_array($from, [ApplicationStatus::WITHDRAWN->value, ApplicationStatus::SELECTED->value], true)) {
            throw ValidationException::withMessages([
                'status' => ['Pendaftaran dengan status ' . $from . ' tidak dapat dibatalkan.'],
            ]);
        }

        $application->update([
            'status' => ApplicationStatus::WITHDRAWN,
            'withdrawn_at' => now(),
            'withdrawal_reason' => $reason,
        ]);

        $this->historyService->record(
            entity: $application,
            action: 'application.withdrawn',
            fromStatus: $from,
            toStatus: ApplicationStatus::WITHDRAWN->value,
            notes: $reason,
            actorId: $actor?->id
        );

        return $application->fresh();
    }

    /**
     * Register an uploaded requirement document against an application.
     */
    public function attachDocument(
        MbkmApplication $application,
        string $category,
        array $fileData,
        ?User $actor = null
    ): MbkmDocument {
        $document = $application->documents()->create([
            'category' => $category,
            'title' => $fileData['title'] ?? null,
            'original_name' => $fileData['original_name'],
            'file_path' => $fileData['file_path'],
            'mime_type' => $fileData['mime_type'] ?? null,
            'size' => $fileData['size'] ?? 0,
            'status' => 'uploaded',
            'uploaded_by' => $actor?->id,
            'notes' => $fileData['notes'] ?? null,
        ]);

        $this->historyService->record(
            entity: $application,
            action: 'application.document_uploaded',
            notes: 'Dokumen ' . $category . ' diunggah.',
            meta: ['document_id' => $document->id, 'category' => $category],
            actorId: $actor?->id
        );

        return $document;
    }

    /**
     * Human readable, unique registration number.
     */
    public function generateRegistrationNumber(MbkmProgram $program): string
    {
        $year = now()->format('Y');
        $prefix = 'MBKM/' . strtoupper($program->code) . '/' . $year . '/';

        $lastSequence = MbkmApplication::where('registration_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->count();

        do {
            $lastSequence++;
            $candidate = $prefix . str_pad((string) $lastSequence, 4, '0', STR_PAD_LEFT);
        } while (MbkmApplication::where('registration_number', $candidate)->exists());

        return $candidate;
    }
}
