<?php

namespace Modules\Student\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Student\Models\Student;
use Modules\Student\Requests\AddStudentEducationRequest;
use Modules\Student\Requests\AddStudentFamilyRequest;
use Modules\Student\Requests\ChangeStudentStatusRequest;
use Modules\Student\Requests\CreateStudentRequest;
use Modules\Student\Requests\UpdateStudentRequest;
use Modules\Student\Resources\StudentEducationResource;
use Modules\Student\Resources\StudentFamilyResource;
use Modules\Student\Resources\StudentResource;
use Modules\Student\Services\StudentService;

class StudentController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected StudentService $studentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Student::with(['studyProgram.faculty', 'user']);

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['full_name', 'student_number', 'national_student_number', 'email'],
            filterableColumns: ['status', 'study_program_id', 'gender', 'admission_year', 'studyProgram.faculty_id'],
            defaultSort: 'id',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Students retrieved successfully.',
            resourceClass: StudentResource::class
        );
    }

    public function store(CreateStudentRequest $request): JsonResponse
    {
        $student = $this->studentService->create($request->validated());

        return $this->successResponse(
            data: new StudentResource($student),
            message: 'Student created successfully.',
            code: 201
        );
    }

    public function show(Student $student): JsonResponse
    {
        return $this->successResponse(
            data: new StudentResource($student->load(['studyProgram.faculty.institution', 'families', 'educations', 'user'])),
            message: 'Student retrieved successfully.'
        );
    }

    public function update(UpdateStudentRequest $request, Student $student): JsonResponse
    {
        $updatedStudent = $this->studentService->update($student, $request->validated());

        return $this->successResponse(
            data: new StudentResource($updatedStudent),
            message: 'Student updated successfully.'
        );
    }

    public function destroy(Request $request, Student $student): JsonResponse
    {
        if (!$request->user()?->hasPermissionTo('students.delete')) {
            return $this->errorResponse('Unauthorized to delete student.', 403);
        }

        $student->delete();

        return $this->successResponse(
            data: null,
            message: 'Student deleted successfully.'
        );
    }

    public function changeStatus(ChangeStudentStatusRequest $request, Student $student): JsonResponse
    {
        $updated = $this->studentService->changeStatus(
            student: $student,
            status: $request->validated('status'),
            notes: $request->validated('notes')
        );

        return $this->successResponse(
            data: new StudentResource($updated->fresh(['studyProgram.faculty'])),
            message: 'Student status updated successfully.'
        );
    }

    public function addFamily(AddStudentFamilyRequest $request, Student $student): JsonResponse
    {
        $family = $student->families()->create($request->validated());

        return $this->successResponse(
            data: new StudentFamilyResource($family),
            message: 'Student family member added successfully.',
            code: 201
        );
    }

    public function addEducation(AddStudentEducationRequest $request, Student $student): JsonResponse
    {
        $education = $student->educations()->create($request->validated());

        return $this->successResponse(
            data: new StudentEducationResource($education),
            message: 'Student education history added successfully.',
            code: 201
        );
    }

    public function createAccount(Request $request, Student $student): JsonResponse
    {
        if ($student->user_id && $student->user) {
            return $this->errorResponse('Mahasiswa sudah memiliki akun pengguna.', 422);
        }

        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = \Modules\Identity\Models\User::create([
            'name' => $student->full_name,
            'email' => $validated['email'],
            'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
            'status' => \Modules\Identity\Enums\UserStatus::ACTIVE,
        ]);

        $user->assignRole('mahasiswa');
        $student->update(['user_id' => $user->id]);

        \Modules\Audit\Services\AuditService::log(
            action: 'created',
            module: 'Student',
            description: "Akun login untuk mahasiswa {$student->full_name} ({$student->student_number}) berhasil dibuat.",
            entity: $student,
            oldValues: null,
            newValues: ['user_id' => $user->id, 'email' => $user->email]
        );

        return $this->successResponse(
            data: new StudentResource($student->fresh(['studyProgram.faculty', 'families', 'educations', 'user'])),
            message: 'Akun portal mahasiswa berhasil dibuat.'
        );
    }

    public function resetPassword(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'required|string|min:6',
        ]);

        if (!$student->user_id || !$student->user) {
            // If student doesn't have an account yet, create it on the fly
            $email = $student->email ?? ($student->student_number . '@student.ac.id');
            $existingUser = \Modules\Identity\Models\User::where('email', $email)->first();
            if ($existingUser) {
                $user = $existingUser;
                $user->update([
                    'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
                    'status' => \Modules\Identity\Enums\UserStatus::ACTIVE,
                ]);
            } else {
                $user = \Modules\Identity\Models\User::create([
                    'name' => $student->full_name,
                    'email' => $email,
                    'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
                    'status' => \Modules\Identity\Enums\UserStatus::ACTIVE,
                ]);
                $user->assignRole('mahasiswa');
            }
            $student->update(['user_id' => $user->id]);
        } else {
            $student->user->update([
                'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
            ]);
        }

        \Modules\Audit\Services\AuditService::log(
            action: 'updated',
            module: 'Student',
            description: "Password akun login mahasiswa {$student->full_name} ({$student->student_number}) berhasil direset.",
            entity: $student,
            oldValues: null,
            newValues: ['password_reset' => true]
        );

        return $this->successResponse(
            data: new StudentResource($student->fresh(['studyProgram.faculty', 'families', 'educations', 'user'])),
            message: 'Password akun mahasiswa berhasil direset.'
        );
    }

    public function toggleAccountStatus(Student $student): JsonResponse
    {
        if (!$student->user_id || !$student->user) {
            return $this->errorResponse('Mahasiswa belum memiliki akun pengguna.', 422);
        }

        $newStatus = $student->user->status === \Modules\Identity\Enums\UserStatus::ACTIVE
            ? \Modules\Identity\Enums\UserStatus::INACTIVE
            : \Modules\Identity\Enums\UserStatus::ACTIVE;

        $student->user->update(['status' => $newStatus]);

        return $this->successResponse(
            data: new StudentResource($student->fresh(['studyProgram.faculty', 'families', 'educations', 'user'])),
            message: 'Status akun mahasiswa berhasil diperbarui.'
        );
    }
}
