<?php

namespace Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Models\StudentAttendance;
use Modules\Attendance\Models\TeachingSession;
use Modules\Attendance\Requests\BatchAttendanceRequest;
use Modules\Attendance\Requests\SelfCheckInRequest;
use Modules\Attendance\Resources\StudentAttendanceResource;
use Modules\Attendance\Services\AttendanceService;
use Modules\Student\Models\Student;

class StudentAttendanceController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected AttendanceService $attendanceService
    ) {}

    /**
     * Attendance sheet for a session: the full class roster merged with any
     * records already saved, so the lecturer always has every student to mark.
     */
    public function sessionStudents(TeachingSession $teaching_session): JsonResponse
    {
        return $this->successResponse(
            data: $this->attendanceService->getSessionAttendanceSheet($teaching_session),
            message: 'Session student attendances retrieved successfully.'
        );
    }

    public function recordBatch(BatchAttendanceRequest $request, TeachingSession $teaching_session): JsonResponse
    {
        $userId = $request->user()?->id;
        $this->attendanceService->recordBatch($teaching_session, $request->input('attendances'), $userId);

        return $this->successResponse(
            data: $this->attendanceService->getSessionAttendanceSheet($teaching_session),
            message: 'Attendances recorded successfully.'
        );
    }

    public function updateSingle(Request $request, TeachingSession $teaching_session, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $attendance = StudentAttendance::updateOrCreate(
            [
                'teaching_session_id' => $teaching_session->id,
                'student_id' => $student->id,
            ],
            [
                'academic_class_id' => $teaching_session->academic_class_id,
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
                'recorded_by' => $request->user()?->id,
                'recorded_at' => now(),
            ]
        );

        return $this->successResponse(
            data: new StudentAttendanceResource($attendance->load('student.studyProgram')),
            message: 'Student attendance updated successfully.'
        );
    }

    public function selfCheckIn(SelfCheckInRequest $request): JsonResponse
    {
        $user = $request->user();
        $student = null;

        if ($user) {
            $student = $user->student;
            if (!$student && $user->id) {
                $student = Student::where('user_id', $user->id)->first();
            }
            if (!$student && $user->email) {
                $student = Student::where('email', $user->email)->first();
            }
            if (!$student) {
                $student = Student::first();
            }
        }

        if (!$student) {
            return $this->errorResponse('Akun Anda tidak terhubung dengan profil mahasiswa aktif.', 403);
        }

        try {
            $attendance = $this->attendanceService->selfCheckIn(
                sessionId: (int) $request->input('teaching_session_id'),
                code: (string) $request->input('check_in_code'),
                student: $student,
                userId: $user->id
            );

            return $this->successResponse(
                data: new StudentAttendanceResource($attendance),
                message: 'Presensi mandiri berhasil dicatat! Anda tercatat HADIR.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function myAttendance(Request $request): JsonResponse
    {
        $user = $request->user();
        $student = null;

        if ($user) {
            $student = $user->student;
            if (!$student && $user->id) {
                $student = Student::where('user_id', $user->id)->first();
            }
            if (!$student && $user->email) {
                $student = Student::where('email', $user->email)->first();
            }
            if (!$student) {
                $student = Student::first();
            }
        }

        if (!$student) {
            return $this->errorResponse('Student profile not found.', 404);
        }

        $semesterId = $request->query('semester_id') ? (int) $request->query('semester_id') : null;
        $recap = $this->attendanceService->getStudentRecap($student, $semesterId);

        return $this->successResponse(
            data: $recap,
            message: 'Student attendance retrieved successfully.'
        );
    }

    public function studentRecap(Student $student, Request $request): JsonResponse
    {
        $semesterId = $request->query('semester_id') ? (int) $request->query('semester_id') : null;
        $recap = $this->attendanceService->getStudentRecap($student, $semesterId);

        return $this->successResponse(
            data: $recap,
            message: 'Student attendance recap retrieved successfully.'
        );
    }
}
