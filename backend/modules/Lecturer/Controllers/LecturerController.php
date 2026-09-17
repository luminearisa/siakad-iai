<?php

namespace Modules\Lecturer\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Audit\Services\AuditService;
use Modules\Identity\Enums\UserStatus;
use Modules\Identity\Models\User;
use Modules\Lecturer\Models\Lecturer;
use Modules\Lecturer\Requests\ChangeLecturerStatusRequest;
use Modules\Lecturer\Requests\CreateLecturerRequest;
use Modules\Lecturer\Requests\UpdateLecturerRequest;
use Modules\Lecturer\Resources\LecturerResource;
use Modules\Lecturer\Services\LecturerService;

class LecturerController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected LecturerService $lecturerService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Lecturer::with(['homebaseStudyProgram.faculty', 'user']);

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['full_name', 'nidn', 'nip', 'email'],
            filterableColumns: ['status', 'homebase_study_program_id', 'gender', 'functional_position'],
            defaultSort: 'id',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Lecturers retrieved successfully.',
            resourceClass: LecturerResource::class
        );
    }

    public function store(CreateLecturerRequest $request): JsonResponse
    {
        $lecturer = $this->lecturerService->create($request->validated());

        return $this->successResponse(
            data: new LecturerResource($lecturer),
            message: 'Lecturer created successfully.',
            code: 201
        );
    }

    public function show(Lecturer $lecturer): JsonResponse
    {
        return $this->successResponse(
            data: new LecturerResource($lecturer->load(['homebaseStudyProgram.faculty.institution', 'educations', 'expertises', 'user'])),
            message: 'Lecturer retrieved successfully.'
        );
    }

    public function update(UpdateLecturerRequest $request, Lecturer $lecturer): JsonResponse
    {
        $updatedLecturer = $this->lecturerService->update($lecturer, $request->validated());

        return $this->successResponse(
            data: new LecturerResource($updatedLecturer),
            message: 'Lecturer updated successfully.'
        );
    }

    public function destroy(Request $request, Lecturer $lecturer): JsonResponse
    {
        if (!$request->user()?->hasPermissionTo('lecturers.delete')) {
            return $this->errorResponse('Unauthorized to delete lecturer.', 403);
        }

        $lecturer->delete();

        return $this->successResponse(
            data: null,
            message: 'Lecturer deleted successfully.'
        );
    }

    public function changeStatus(ChangeLecturerStatusRequest $request, Lecturer $lecturer): JsonResponse
    {
        $updated = $this->lecturerService->changeStatus(
            lecturer: $lecturer,
            status: $request->validated('status'),
            notes: $request->validated('notes')
        );

        return $this->successResponse(
            data: new LecturerResource($updated->fresh(['homebaseStudyProgram.faculty'])),
            message: 'Lecturer status updated successfully.'
        );
    }

    public function updateQuotas(Request $request, Lecturer $lecturer): JsonResponse
    {
        $validated = $request->validate([
            'academic_advising_quota' => ['required', 'integer', 'min:0', 'max:100'],
            'thesis_supervisor_quota' => ['required', 'integer', 'min:0', 'max:100'],
            'thesis_examiner_quota' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $lecturer->update($validated);

        return $this->successResponse(
            data: new LecturerResource($lecturer->fresh(['homebaseStudyProgram.faculty'])),
            message: 'Kuota pembimbing dosen berhasil diperbarui.'
        );
    }

    /**
     * Build the default login email for a lecturer when none is provided.
     */
    protected function defaultEmailFor(Lecturer $lecturer): string
    {
        if (!empty($lecturer->email)) {
            return $lecturer->email;
        }

        $identifier = $lecturer->lecturer_number
            ?: $lecturer->nidn
            ?: $lecturer->nip
            ?: 'dosen' . $lecturer->id;

        return strtolower(preg_replace('/[^A-Za-z0-9]/', '', $identifier)) . '@dosen.ac.id';
    }

    /**
     * Load the lecturer with every relation the resource needs.
     */
    protected function freshLecturer(Lecturer $lecturer): LecturerResource
    {
        return new LecturerResource(
            $lecturer->fresh(['homebaseStudyProgram.faculty', 'educations', 'expertises', 'user'])
        );
    }

    public function createAccount(Request $request, Lecturer $lecturer): JsonResponse
    {
        if ($lecturer->user_id && $lecturer->user) {
            return $this->errorResponse('Dosen sudah memiliki akun pengguna.', 422);
        }

        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $lecturer->full_name,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => UserStatus::ACTIVE,
        ]);

        $user->assignRole('dosen');
        $lecturer->update(['user_id' => $user->id]);

        AuditService::log(
            action: 'created',
            module: 'Lecturer',
            description: "Akun login untuk dosen {$lecturer->full_name} berhasil dibuat.",
            entity: $lecturer,
            oldValues: null,
            newValues: ['user_id' => $user->id, 'email' => $user->email]
        );

        return $this->successResponse(
            data: $this->freshLecturer($lecturer),
            message: 'Akun portal dosen berhasil dibuat.'
        );
    }

    public function resetPassword(Request $request, Lecturer $lecturer): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'required|string|min:6',
        ]);

        if (!$lecturer->user_id || !$lecturer->user) {
            // If lecturer doesn't have an account yet, provision it on the fly.
            $email = $this->defaultEmailFor($lecturer);
            $existingUser = User::where('email', $email)->first();

            if ($existingUser) {
                $user = $existingUser;
                $user->update([
                    'password' => Hash::make($validated['password']),
                    'status' => UserStatus::ACTIVE,
                ]);
            } else {
                $user = User::create([
                    'name' => $lecturer->full_name,
                    'email' => $email,
                    'password' => Hash::make($validated['password']),
                    'status' => UserStatus::ACTIVE,
                ]);
            }

            if (!$user->hasRole('dosen')) {
                $user->assignRole('dosen');
            }

            $lecturer->update(['user_id' => $user->id]);
        } else {
            $lecturer->user->update([
                'password' => Hash::make($validated['password']),
            ]);
        }

        AuditService::log(
            action: 'updated',
            module: 'Lecturer',
            description: "Password akun login dosen {$lecturer->full_name} berhasil direset.",
            entity: $lecturer,
            oldValues: null,
            newValues: ['password_reset' => true]
        );

        return $this->successResponse(
            data: $this->freshLecturer($lecturer),
            message: 'Password akun dosen berhasil direset.'
        );
    }

    public function toggleAccountStatus(Lecturer $lecturer): JsonResponse
    {
        if (!$lecturer->user_id || !$lecturer->user) {
            return $this->errorResponse('Dosen belum memiliki akun pengguna.', 422);
        }

        $newStatus = $lecturer->user->status === UserStatus::ACTIVE
            ? UserStatus::INACTIVE
            : UserStatus::ACTIVE;

        $lecturer->user->update(['status' => $newStatus]);

        AuditService::log(
            action: 'updated',
            module: 'Lecturer',
            description: "Status akun login dosen {$lecturer->full_name} diubah menjadi {$newStatus->value}.",
            entity: $lecturer,
            oldValues: null,
            newValues: ['user_status' => $newStatus->value]
        );

        return $this->successResponse(
            data: $this->freshLecturer($lecturer),
            message: 'Status akun dosen berhasil diperbarui.'
        );
    }
}
