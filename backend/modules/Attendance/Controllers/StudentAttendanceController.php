<?php

namespace Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Models\TeachingSession;
use Modules\Attendance\Requests\BatchAttendanceRequest;
use Modules\Attendance\Requests\RecordSingleAttendanceRequest;
use Modules\Attendance\Requests\SelfCheckInRequest;
use Modules\Attendance\Resources\StudentAttendanceResource;
use Modules\Attendance\Services\AttendanceService;
use Modules\Attendance\Support\AttendanceAccess;
use Modules\Student\Models\Student;

class StudentAttendanceController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected AttendanceService $attendanceService
    ) {}

    /**
     * Daftar absen satu sesi: roster kelas digabung dengan baris yang sudah tersimpan.
     */
    public function sessionStudents(Request $request, TeachingSession $teaching_session): JsonResponse
    {
        if (!AttendanceAccess::mayManageSession($request->user(), $teaching_session)) {
            return $this->errorResponse('Hanya dosen pengampu atau staf akademik yang dapat melihat daftar absen ini.', 403);
        }

        return $this->successResponse(
            data: $this->attendanceService->getSessionAttendanceSheet($teaching_session),
            message: 'Session student attendances retrieved successfully.'
        );
    }

    public function recordBatch(BatchAttendanceRequest $request, TeachingSession $teaching_session): JsonResponse
    {
        if (!AttendanceAccess::mayManageSession($request->user(), $teaching_session)) {
            return $this->errorResponse('Hanya dosen pengampu sesi ini atau staf akademik yang dapat mencatat presensi.', 403);
        }

        $this->attendanceService->recordBatch(
            session: $teaching_session,
            rows: $request->input('attendances'),
            userId: $request->user()?->id,
            correctionReason: $request->input('correction_reason')
        );

        return $this->successResponse(
            data: $this->attendanceService->getSessionAttendanceSheet($teaching_session),
            message: 'Attendances recorded successfully.'
        );
    }

    public function updateSingle(
        RecordSingleAttendanceRequest $request,
        TeachingSession $teaching_session,
        Student $student
    ): JsonResponse {
        if (!AttendanceAccess::mayManageSession($request->user(), $teaching_session)) {
            return $this->errorResponse('Hanya dosen pengampu sesi ini atau staf akademik yang dapat mengoreksi presensi.', 403);
        }

        $attendance = $this->attendanceService->recordSingle(
            session: $teaching_session,
            student: $student,
            status: AttendanceStatus::from($request->validated('status')),
            notes: $request->validated('notes'),
            userId: $request->user()?->id,
            correctionReason: $request->validated('correction_reason')
        );

        return $this->successResponse(
            data: new StudentAttendanceResource($attendance),
            message: 'Student attendance updated successfully.'
        );
    }

    /**
     * Presensi mandiri. Identitas mahasiswa diambil dari akun yang login — tanpa
     * profil mahasiswa milik sendiri, permintaan ditolak (tidak ada fallback).
     */
    public function selfCheckIn(SelfCheckInRequest $request): JsonResponse
    {
        $student = AttendanceAccess::currentStudent($request->user());

        if (!$student) {
            return $this->errorResponse('Akun Anda tidak terhubung dengan profil mahasiswa.', 403);
        }

        $session = TeachingSession::findOrFail((int) $request->input('teaching_session_id'));

        $attendance = $this->attendanceService->selfCheckIn(
            session: $session,
            code: (string) $request->input('check_in_code'),
            student: $student,
            userId: $request->user()->id
        );

        return $this->successResponse(
            data: new StudentAttendanceResource($attendance),
            message: 'Presensi mandiri berhasil dicatat! Anda tercatat HADIR.'
        );
    }

    public function myAttendance(Request $request): JsonResponse
    {
        $student = AttendanceAccess::currentStudent($request->user());

        if (!$student) {
            return $this->errorResponse('Akun Anda tidak terhubung dengan profil mahasiswa.', 403);
        }

        $semesterId = $request->query('semester_id') ? (int) $request->query('semester_id') : null;

        return $this->successResponse(
            data: $this->attendanceService->getStudentRecap($student, $semesterId),
            message: 'Student attendance retrieved successfully.'
        );
    }

    public function studentRecap(Student $student, Request $request): JsonResponse
    {
        if (!AttendanceAccess::maySeeStudent($request->user(), $student)) {
            return $this->errorResponse('Anda tidak berwenang melihat rekap kehadiran mahasiswa ini.', 403);
        }

        $semesterId = $request->query('semester_id') ? (int) $request->query('semester_id') : null;

        return $this->successResponse(
            data: $this->attendanceService->getStudentRecap($student, $semesterId),
            message: 'Student attendance recap retrieved successfully.'
        );
    }
}
