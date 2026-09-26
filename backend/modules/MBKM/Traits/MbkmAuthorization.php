<?php

namespace Modules\MBKM\Traits;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\Models\User;
use Modules\Lecturer\Models\Lecturer;
use Modules\MBKM\Models\MbkmParticipant;
use Modules\Student\Models\Student;

/**
 * Server-side ownership / scope resolution for the MBKM module.
 *
 * Never trust student_id / participant_id coming from the request: every
 * endpoint resolves the acting student or lecturer from the authenticated user
 * and then verifies ownership of the target record.
 *
 * The `mbkm.manage` permission is the "staff" marker (granted to super_admin and
 * admin_akademik). A plain lecturer (role `dosen` without it) may only touch
 * participants they actually supervise.
 */
trait MbkmAuthorization
{
    protected function currentUser(Request $request): ?User
    {
        return $request->user();
    }

    protected function isMbkmManager(Request $request): bool
    {
        $user = $this->currentUser($request);

        return (bool) $user?->hasPermissionTo('mbkm.manage');
    }

    /**
     * A plain lecturer: has the dosen role but no module management rights.
     */
    protected function isMbkmLecturerOnly(Request $request): bool
    {
        $user = $this->currentUser($request);

        return (bool) ($user && $user->hasRole('dosen') && !$user->hasPermissionTo('mbkm.manage'));
    }

    protected function isMbkmStudent(Request $request): bool
    {
        return (bool) $this->currentUser($request)?->hasRole('mahasiswa');
    }

    /**
     * Resolve the student profile bound to the authenticated user.
     */
    protected function currentStudent(Request $request): ?Student
    {
        $user = $this->currentUser($request);

        if (!$user) {
            return null;
        }

        return $user->student ?? Student::where('user_id', $user->id)->first();
    }

    protected function currentLecturer(Request $request): ?Lecturer
    {
        return $this->currentUser($request)?->lecturer;
    }

    /**
     * Does the acting lecturer supervise this participant (any role)?
     */
    protected function lecturerSupervisesParticipant(Request $request, MbkmParticipant $participant): bool
    {
        $lecturerId = $this->currentLecturer($request)?->id;

        if (!$lecturerId) {
            return false;
        }

        return $participant->supervisors()->where('lecturer_id', $lecturerId)->exists();
    }

    /**
     * Study-program scoped managers: a user who may manage study programs but
     * not the whole module only sees participants of their own program.
     *
     * This is what stops an admin of prodi A from touching prodi B's data, and
     * it must be part of every *write* check too — not only the read check.
     */
    protected function managesParticipantStudyProgram(Request $request, MbkmParticipant $participant): bool
    {
        return $this->managesStudentStudyProgram($request, $participant->student_id);
    }

    /**
     * Generic study-program scope check for records that are not yet bound to a
     * participant — an application, for instance, which exists before any
     * placement does. Same rule: `mbkm.manage_study_program` only ever covers
     * the students of the holder's own homebase program.
     */
    protected function managesStudentStudyProgram(Request $request, ?int $studentId): bool
    {
        $user = $this->currentUser($request);

        if (!$user?->hasPermissionTo('mbkm.manage_study_program') || $studentId === null) {
            return false;
        }

        $programId = $user->lecturer?->homebase_study_program_id;

        if (!$programId) {
            return false;
        }

        return Student::whereKey($studentId)
            ->where('study_program_id', $programId)
            ->exists();
    }

    /**
     * Full access check for a single participant record.
     */
    protected function mayAccessParticipant(Request $request, MbkmParticipant $participant): bool
    {
        if ($this->isMbkmManager($request)) {
            return true;
        }

        if ($this->isMbkmStudent($request)) {
            return $this->currentStudent($request)?->id === $participant->student_id;
        }

        if ($this->isMbkmLecturerOnly($request)) {
            return $this->lecturerSupervisesParticipant($request, $participant);
        }

        return $this->managesParticipantStudyProgram($request, $participant);
    }

    /**
     * Write access to a participant's *own* execution data (activity plans,
     * logbook, attendance, self-assessment, issues, withdrawal/extension
     * requests): the participant themself, their supervisor, or a manager.
     */
    protected function mayWriteParticipant(Request $request, MbkmParticipant $participant): bool
    {
        if ($this->isMbkmManager($request)) {
            return true;
        }

        if ($this->isMbkmStudent($request)) {
            return $this->currentStudent($request)?->id === $participant->student_id;
        }

        return $this->isMbkmLecturerOnly($request) && $this->lecturerSupervisesParticipant($request, $participant);
    }

    /**
     * Academic/administrative write access (reviewing logbooks, recognition,
     * placements, assessments, completion verification): a manager, one of the
     * participant's supervisors, or a manager scoped to the participant's own
     * study program. The participant themself is explicitly excluded.
     *
     * Every administrative write endpoint must call this — holding a module-wide
     * permission such as `mbkm.participants.manage` is not on its own enough to
     * prove the actor is scoped to *this* participant.
     */
    protected function mayManageParticipant(Request $request, MbkmParticipant $participant): bool
    {
        if ($this->isMbkmManager($request)) {
            return true;
        }

        if ($this->isMbkmLecturerOnly($request) && $this->lecturerSupervisesParticipant($request, $participant)) {
            return true;
        }

        return $this->managesParticipantStudyProgram($request, $participant);
    }

    /**
     * Standard 403 payload.
     */
    protected function mbkmDeny(string $message = 'Anda tidak memiliki akses ke data MBKM ini.'): JsonResponse
    {
        return ApiResponse::error($message, 403);
    }

    /**
     * Ids of the students whose MBKM records the authenticated user may see.
     *
     * Returns `null` when the user is unrestricted (module manager), and an
     * **empty array** when they may see nothing — callers must treat the two
     * differently. Use this for any listing endpoint that is not already
     * bound to a single participant, otherwise the endpoint becomes a
     * cross-student data leak.
     *
     * @return array<int, int>|null
     */
    protected function visibleStudentIds(Request $request): ?array
    {
        if ($this->isMbkmManager($request)) {
            return null;
        }

        if ($this->isMbkmStudent($request)) {
            $studentId = $this->currentStudent($request)?->id;

            return $studentId ? [(int) $studentId] : [];
        }

        // Study-program scoped managers see every student of their own program.
        // This branch must come BEFORE the plain-lecturer branch: a kaprodi is
        // also a `dosen`, so checking lecturer-only first would silently reduce
        // them to just the participants they personally supervise.
        $user = $this->currentUser($request);
        if ($user?->hasPermissionTo('mbkm.manage_study_program')) {
            $programId = $user->lecturer?->homebase_study_program_id;

            if (!$programId) {
                return [];
            }

            return Student::where('study_program_id', $programId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        // A plain lecturer sees the students they actually supervise.
        if ($this->isMbkmLecturerOnly($request)) {
            $lecturerId = $this->currentLecturer($request)?->id;

            if (!$lecturerId) {
                return [];
            }

            return MbkmParticipant::query()
                ->whereHas('supervisors', fn ($q) => $q->where('lecturer_id', $lecturerId))
                ->pluck('student_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return [];
    }

    /**
     * May the user see documents/records attached to a given student?
     */
    protected function maySeeStudent(Request $request, ?int $studentId): bool
    {
        $visible = $this->visibleStudentIds($request);

        if ($visible === null) {
            return true;
        }

        return $studentId !== null && in_array((int) $studentId, $visible, true);
    }

    /**
     * Resolve the student for write operations, rejecting users without a profile.
     */
    protected function requireStudent(Request $request): Student
    {
        $student = $this->currentStudent($request);

        if (!$student) {
            abort(404, 'Profil mahasiswa tidak ditemukan untuk akun ini.');
        }

        return $student;
    }
}
