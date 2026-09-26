<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Academic\Models\Semester;
use Modules\Academic\Models\StudyProgram;
use Modules\Curriculum\Enums\CurriculumStatus;
use Modules\Curriculum\Models\Curriculum;
use Modules\Curriculum\Models\CurriculumSubject;
use Modules\Lecturer\Models\Lecturer;
use Modules\MBKM\Enums\ApplicationStatus;
use Modules\MBKM\Enums\CompletionStatus;
use Modules\MBKM\Enums\DocumentStatus;
use Modules\MBKM\Enums\LogbookStatus;
use Modules\MBKM\Enums\ParticipantStatus;
use Modules\MBKM\Enums\ProgramStatus;
use Modules\MBKM\Enums\AssessorType;
use Modules\MBKM\Enums\RecognitionStatus;
use Modules\MBKM\Enums\WithdrawalType;
use Modules\MBKM\Models\MbkmActivityLog;
use Modules\MBKM\Models\MbkmApplication;
use Modules\MBKM\Models\MbkmAssessment;
use Modules\MBKM\Models\MbkmAssessmentComponent;
use Modules\MBKM\Models\MbkmDocument;
use Modules\MBKM\Models\MbkmExtensionRequest;
use Modules\MBKM\Models\MbkmParticipant;
use Modules\MBKM\Models\MbkmProgram;
use Modules\MBKM\Models\MbkmProgramType;
use Modules\MBKM\Models\MbkmRecognition;
use Modules\MBKM\Models\MbkmWithdrawalRequest;
use Modules\MBKM\Services\MbkmRecognitionService;
use Modules\Student\Models\Student;
use Modules\Identity\Models\Permission;
use Modules\Identity\Models\Role;
use Modules\Identity\Models\User;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * End-to-end MBKM workflow test.
 *
 * Walks the whole chain:
 * program -> application -> verification -> selection -> participant ->
 * placement -> supervisor -> learning agreement -> logbook -> attendance ->
 * assessment -> final score -> recognition -> academic result (KRS/KHS) ->
 * completion.
 */
class MbkmWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $adminToken;
    protected Student $student;
    protected string $studentToken;
    protected Lecturer $lecturer;
    protected string $lecturerToken;
    protected Semester $semester;
    protected MbkmProgramType $programType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@siakad.ac.id')->firstOrFail();
        $this->adminToken = $this->admin->createToken('admin')->plainTextToken;

        $this->student = Student::where('student_number', '202501001')->firstOrFail();
        $this->studentToken = $this->student->user->createToken('student')->plainTextToken;

        $this->lecturer = Lecturer::where('nidn', '0011223301')->firstOrFail();
        $this->lecturerToken = $this->lecturer->user->createToken('lecturer')->plainTextToken;

        $this->semester = Semester::firstOrFail();
        $this->programType = MbkmProgramType::where('code', 'MAGANG')->firstOrFail();
    }

    protected function asAdmin()
    {
        // Sanctum caches the resolved user on the guard across requests within a
        // single test; forget it so switching identity actually takes effect.
        auth()->forgetGuards();

        return $this->withHeader('Authorization', "Bearer {$this->adminToken}");
    }

    protected function asStudent()
    {
        auth()->forgetGuards();

        return $this->withHeader('Authorization', "Bearer {$this->studentToken}");
    }

    protected function asLecturer()
    {
        auth()->forgetGuards();

        return $this->withHeader('Authorization', "Bearer {$this->lecturerToken}");
    }

    /**
     * Create a fully configured, published program through the API.
     */
    protected function createProgram(array $overrides = []): MbkmProgram
    {
        $payload = array_merge([
            'program_type_id' => $this->programType->id,
            'code' => 'MBKM-TEST-' . uniqid(),
            'name' => 'Magang MBKM Uji',
            'organizer_type' => 'study_program',
            'semester_id' => $this->semester->id,
            'registration_start_date' => now()->subDays(5)->toDateString(),
            'registration_end_date' => now()->addDays(30)->toDateString(),
            'start_date' => now()->addDays(40)->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'quota' => 5,
            'max_recognized_credits' => 12,
            'location_mode' => 'off_campus',
            'requires_documents' => true,
            'requires_learning_agreement' => true,
            'requires_attendance' => true,
            'requires_logbook' => true,
            'logbook_period' => 'weekly',
            'requires_assessment' => true,
            'requires_final_report' => false,
            'requires_recognition' => true,
            'min_attendance_percentage' => 70,
        ], $overrides);

        $response = $this->asAdmin()->postJson('/api/v1/mbkm/programs', $payload);
        $response->assertStatus(201);

        return MbkmProgram::findOrFail($response->json('data.id'));
    }

    protected function configureAssessmentWeights(MbkmProgram $program): void
    {
        $this->asAdmin()->putJson("/api/v1/mbkm/programs/{$program->id}/assessment-components", [
            'components' => [
                ['code' => 'PERF', 'name' => 'Performance', 'weight' => 30, 'assessor_type' => 'field_supervisor'],
                ['code' => 'LOGB', 'name' => 'Logbook', 'weight' => 10, 'assessor_type' => 'internal_supervisor'],
                ['code' => 'PROJ', 'name' => 'Final Project', 'weight' => 30, 'assessor_type' => 'internal_supervisor'],
                ['code' => 'PART', 'name' => 'Partner Assessment', 'weight' => 20, 'assessor_type' => 'partner'],
                ['code' => 'PRES', 'name' => 'Presentation', 'weight' => 10, 'assessor_type' => 'committee'],
            ],
        ])->assertStatus(200);
    }

    protected function addDocumentRequirements(MbkmProgram $program): void
    {
        foreach ([['KTM', 'Kartu Tanda Mahasiswa'], ['CV', 'Curriculum Vitae']] as $index => [$code, $name]) {
            $this->asAdmin()->postJson("/api/v1/mbkm/programs/{$program->id}/requirements", [
                'type' => 'document',
                'code' => $code,
                'name' => $name,
                'is_mandatory' => true,
                'is_document' => true,
                'sort_order' => $index,
            ])->assertStatus(201);
        }
    }

    public function test_full_mbkm_workflow_from_program_to_academic_result(): void
    {
        // ---------------------------------------------------------------
        // 1. Admin creates + configures + publishes the program
        // ---------------------------------------------------------------
        $program = $this->createProgram();
        $this->assertSame(ProgramStatus::DRAFT, $program->status);

        $this->configureAssessmentWeights($program);
        $this->addDocumentRequirements($program);

        $this->asAdmin()->postJson("/api/v1/mbkm/programs/{$program->id}/locations", [
            'name' => 'Kantor Mitra',
            'location_mode' => 'off_campus',
            'city' => 'Bandung',
        ])->assertStatus(201);

        $partner = $this->asAdmin()->postJson('/api/v1/mbkm/partners', [
            'code' => 'PT-UJI',
            'name' => 'PT Uji Coba',
            'type' => 'company',
        ])->assertStatus(201)->json('data');

        $this->asAdmin()->postJson('/api/v1/mbkm/cooperations', [
            'partner_id' => $partner['id'],
            'program_id' => $program->id,
            'type' => 'mou',
            'title' => 'MoU Uji',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ])->assertStatus(201);

        $this->asAdmin()->postJson("/api/v1/mbkm/programs/{$program->id}/transition", [
            'status' => ProgramStatus::PUBLISHED->value,
        ])->assertStatus(200);

        // ---------------------------------------------------------------
        // 2. Student sees the catalog with an explainable eligibility
        // ---------------------------------------------------------------
        $catalog = $this->asStudent()->getJson('/api/v1/mbkm/catalog')->assertStatus(200);
        $row = collect($catalog->json('data'))->firstWhere('program.id', $program->id);
        $this->assertNotNull($row, 'Program harus muncul di katalog mahasiswa.');
        $this->assertTrue($row['is_eligible'], 'Mahasiswa seharusnya eligible: ' . json_encode($row['eligibility_reasons']));
        $this->assertNotEmpty($row['eligibility_checks']);

        // ---------------------------------------------------------------
        // 3. Student applies, uploads mandatory documents, submits
        // ---------------------------------------------------------------
        $applicationId = $this->asStudent()->postJson('/api/v1/mbkm/applications', [
            'program_id' => $program->id,
            'motivation_statement' => 'Saya ingin mengikuti magang MBKM.',
        ])->assertStatus(201)->json('data.id');

        // Submitting without documents must fail (server-side validation).
        $this->asStudent()->postJson("/api/v1/mbkm/applications/{$applicationId}/submit")
            ->assertStatus(422);

        foreach (['KTM', 'CV'] as $category) {
            $this->asStudent()->postJson('/api/v1/mbkm/documents', [
                'documentable_type' => 'application',
                'documentable_id' => $applicationId,
                'category' => $category,
                'file' => \Illuminate\Http\UploadedFile::fake()->create("{$category}.pdf", 100, 'application/pdf'),
            ])->assertStatus(201);
        }

        $this->asStudent()->postJson("/api/v1/mbkm/applications/{$applicationId}/submit")
            ->assertStatus(200)
            ->assertJsonPath('data.status', ApplicationStatus::SUBMITTED->value);

        // Duplicate active application must be refused.
        $this->asStudent()->postJson('/api/v1/mbkm/applications', ['program_id' => $program->id])
            ->assertStatus(201);
        $this->assertSame(
            1,
            MbkmApplication::where('program_id', $program->id)->where('student_id', $this->student->id)->count(),
            'Pendaftaran duplikat tidak boleh dibuat.'
        );

        // ---------------------------------------------------------------
        // 4. Admin verifies -> program enters selection -> decides
        // ---------------------------------------------------------------
        $this->asAdmin()->postJson("/api/v1/mbkm/applications/{$applicationId}/verify", [
            'decision' => ApplicationStatus::VERIFIED->value,
        ])->assertStatus(200);

        $this->asAdmin()->postJson("/api/v1/mbkm/programs/{$program->id}/transition", [
            'status' => ProgramStatus::REGISTRATION_CLOSED->value,
        ])->assertStatus(200);
        $this->asAdmin()->postJson("/api/v1/mbkm/programs/{$program->id}/transition", [
            'status' => ProgramStatus::SELECTION->value,
        ])->assertStatus(200);

        $this->asAdmin()->postJson("/api/v1/mbkm/applications/{$applicationId}/decide", [
            'decision' => ApplicationStatus::SELECTED->value,
            'notes' => 'Lolos seleksi.',
        ])->assertStatus(200);

        // ---------------------------------------------------------------
        // 5. Participant assignment (quota enforced)
        // ---------------------------------------------------------------
        $participantId = $this->asAdmin()->postJson('/api/v1/mbkm/participants/assign', [
            'application_id' => $applicationId,
        ])->assertStatus(201)->json('data.id');

        $participant = MbkmParticipant::findOrFail($participantId);
        $this->assertSame(ParticipantStatus::ASSIGNED, $participant->status);

        // Assigning the same application twice must fail.
        $this->asAdmin()->postJson('/api/v1/mbkm/participants/assign', [
            'application_id' => $applicationId,
        ])->assertStatus(422);

        // ---------------------------------------------------------------
        // 6. Placement + supervisors
        // ---------------------------------------------------------------
        $this->asAdmin()->putJson("/api/v1/mbkm/participants/{$participantId}/placement", [
            'partner_id' => $partner['id'],
            'division' => 'Engineering',
            'position' => 'Intern',
        ])->assertStatus(200);

        $this->asAdmin()->postJson("/api/v1/mbkm/participants/{$participantId}/supervisors", [
            'role' => 'internal',
            'lecturer_id' => $this->lecturer->id,
        ])->assertStatus(201);

        $this->asAdmin()->postJson("/api/v1/mbkm/participants/{$participantId}/supervisors", [
            'role' => 'field',
            'external_name' => 'Rina Kurnia',
            'external_position' => 'HR Manager',
            'external_organization' => 'PT Uji Coba',
        ])->assertStatus(201);

        // ---------------------------------------------------------------
        // 7. Learning agreement: draft -> submitted -> reviewed -> approved
        // ---------------------------------------------------------------
        $this->asAdmin()->putJson("/api/v1/mbkm/participants/{$participantId}/learning-agreement", [
            'title' => 'Learning Agreement Magang',
            'items' => [
                ['activity_title' => 'Pengembangan modul aplikasi', 'credits' => 3],
            ],
        ])->assertStatus(200);

        foreach (['submitted', 'reviewed', 'approved'] as $status) {
            $this->asAdmin()->postJson("/api/v1/mbkm/participants/{$participantId}/learning-agreement/transition", [
                'status' => $status,
            ])->assertStatus(200);
        }

        // A locked/approved agreement cannot be edited through the normal endpoint.
        $this->asAdmin()->putJson("/api/v1/mbkm/participants/{$participantId}/learning-agreement", [
            'title' => 'Percobaan ubah',
        ])->assertStatus(422);

        // ---------------------------------------------------------------
        // 8. Start execution
        // ---------------------------------------------------------------
        $this->asAdmin()->postJson("/api/v1/mbkm/participants/{$participantId}/start")
            ->assertStatus(200)
            ->assertJsonPath('data.status', ParticipantStatus::ONGOING->value);

        // ---------------------------------------------------------------
        // 9. Logbook: student records, supervisor reviews
        // ---------------------------------------------------------------
        $logbookId = $this->asStudent()->postJson("/api/v1/mbkm/participants/{$participantId}/logbooks", [
            'log_date' => now()->toDateString(),
            'activity' => 'Setup lingkungan kerja',
            'duration_hours' => 8,
        ])->assertStatus(201)->json('data.id');

        $this->asStudent()->postJson("/api/v1/mbkm/logbooks/{$logbookId}/submit")->assertStatus(200);

        $this->asLecturer()->postJson("/api/v1/mbkm/logbooks/{$logbookId}/review", [
            'decision' => LogbookStatus::APPROVED->value,
            'notes' => 'Bagus.',
        ])->assertStatus(200);

        // A finalized logbook can no longer be edited.
        $this->asStudent()->putJson("/api/v1/mbkm/logbooks/{$logbookId}", [
            'log_date' => now()->toDateString(),
            'activity' => 'Diubah setelah final',
        ])->assertStatus(422);

        // ---------------------------------------------------------------
        // 10. Attendance
        // ---------------------------------------------------------------
        $this->asLecturer()->postJson("/api/v1/mbkm/participants/{$participantId}/attendances", [
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
            'duration_hours' => 8,
        ])->assertStatus(201);

        $this->asLecturer()->getJson("/api/v1/mbkm/participants/{$participantId}/attendances/summary")
            ->assertStatus(200)
            ->assertJsonPath('data.summary.present', 1);

        // ---------------------------------------------------------------
        // 11. Assessment -> weighted final score
        // ---------------------------------------------------------------
        $components = $this->asAdmin()->getJson('/api/v1/mbkm/assessment-components?program_id=' . $program->id)
            ->assertStatus(200)
            ->json('data.components');

        $this->assertCount(5, $components);
        $this->assertEqualsWithDelta(100.0, (float) collect($components)->sum('weight'), 0.01);

        foreach ($components as $component) {
            $this->asAdmin()->postJson("/api/v1/mbkm/participants/{$participantId}/assessments", [
                'component_id' => $component['id'],
                'score' => 90,
                'max_score' => 100,
            ])->assertStatus(201);
        }

        $this->asAdmin()->postJson("/api/v1/mbkm/participants/{$participantId}/finalize-score")
            ->assertStatus(200)
            ->assertJsonPath('data.final_score', 90)
            ->assertJsonPath('data.letter_grade', 'A');

        // ---------------------------------------------------------------
        // 12. Recognition -> academic result (KRS + KHS)
        // ---------------------------------------------------------------
        $curriculum = Curriculum::where('study_program_id', $this->student->study_program_id)
            ->where('status', CurriculumStatus::ACTIVE)
            ->firstOrFail();

        $subjects = CurriculumSubject::whereHas('curriculumSemester', fn ($q) => $q->where('curriculum_id', $curriculum->id))
            ->with('course')
            ->get()
            ->filter(fn ($s) => $s->course !== null)
            ->take(2)
            ->values();

        $this->assertGreaterThanOrEqual(2, $subjects->count(), 'Butuh minimal 2 mata kuliah kurikulum untuk uji rekognisi.');

        $recognitionIds = [];

        foreach ($subjects as $subject) {
            $recognitionIds[] = $this->asAdmin()->postJson("/api/v1/mbkm/participants/{$participantId}/recognitions", [
                'course_id' => $subject->course->id,
                'credits' => $subject->course->credits,
                'semester_id' => $this->semester->id,
                'source_label' => 'Aktivitas Magang',
            ])->assertStatus(201)->json('data.id');
        }

        // Duplicate recognition for the same course must be refused.
        $this->asAdmin()->postJson("/api/v1/mbkm/participants/{$participantId}/recognitions", [
            'course_id' => $subjects[0]->course->id,
            'credits' => $subjects[0]->course->credits,
        ])->assertStatus(422);

        // A course from another study program's curriculum must be refused.
        $foreignCourse = \Modules\Course\Models\Course::where('study_program_id', '!=', $this->student->study_program_id)
            ->whereNotNull('study_program_id')
            ->first();

        if ($foreignCourse) {
            $this->asAdmin()->postJson("/api/v1/mbkm/participants/{$participantId}/recognitions", [
                'course_id' => $foreignCourse->id,
                'credits' => $foreignCourse->credits,
            ])->assertStatus(422);
        }

        // Drive one recognition through the workflow to approval.
        foreach (['submitted', 'reviewed', 'approved'] as $status) {
            $this->asAdmin()->postJson("/api/v1/mbkm/recognitions/{$recognitionIds[0]}/transition", [
                'status' => $status,
            ])->assertStatus(200);
        }

        $recognition = MbkmRecognition::findOrFail($recognitionIds[0]);
        $this->assertSame(RecognitionStatus::APPROVED, $recognition->status);
        $this->assertSame('synced', $recognition->sync_status);
        $this->assertNotNull($recognition->academic_enrollment_item_id);
        $this->assertNotNull($recognition->academic_class_id);
        $this->assertSame('A', $recognition->letter_grade);

        // Approved recognition cannot be edited through the normal endpoint.
        $this->asAdmin()->putJson("/api/v1/mbkm/recognitions/{$recognitionIds[0]}", [
            'credits' => 1,
        ])->assertStatus(422);

        // The recognition really landed in the existing academic pipeline.
        $this->assertDatabaseHas('student_enrollment_items', [
            'id' => $recognition->academic_enrollment_item_id,
            'course_id' => $recognition->course_id,
        ]);
        $this->assertDatabaseHas('student_grades', [
            'student_id' => $this->student->id,
            'academic_class_id' => $recognition->academic_class_id,
        ]);

        // ...and therefore shows up in KHS through the untouched pipeline.
        $khs = $this->asStudent()->getJson('/api/v1/students/me/khs')->assertStatus(200);
        $courseCodes = collect($khs->json('data.courses'))->pluck('course_code')->all();
        $this->assertContains($recognition->course->code, $courseCodes, 'Mata kuliah rekognisi harus muncul di KHS.');

        $recognizedRow = collect($khs->json('data.courses'))->firstWhere('course_code', $recognition->course->code);
        $this->assertSame('A', $recognizedRow['letter_grade']);
        $this->assertTrue($recognizedRow['is_passed']);

        // Participant credit cache reflects the approved recognition.
        $this->assertSame((int) $recognition->credits, MbkmParticipant::findOrFail($participantId)->recognized_credits);

        // ---------------------------------------------------------------
        // 13. Completion verification
        // ---------------------------------------------------------------
        $evaluation = $this->asAdmin()->getJson("/api/v1/mbkm/participants/{$participantId}/completion")
            ->assertStatus(200);

        // The second recognition is still a draft, so recognition is satisfied
        // by the first approved row; everything else is complete.
        $this->assertTrue(
            $evaluation->json('data.is_complete'),
            'Syarat penyelesaian belum lengkap: ' . json_encode($evaluation->json('data.unmet'))
        );

        $this->asAdmin()->postJson("/api/v1/mbkm/participants/{$participantId}/completion/verify")
            ->assertStatus(200)
            ->assertJsonPath('data.status', CompletionStatus::COMPLETED->value);

        $this->assertSame(ParticipantStatus::COMPLETED, MbkmParticipant::findOrFail($participantId)->status);

        // ---------------------------------------------------------------
        // 14. Reporting + dashboard still consistent
        // ---------------------------------------------------------------
        $this->asAdmin()->getJson('/api/v1/mbkm/reports/summary')->assertStatus(200)->assertJsonPath('success', true);
        $this->asAdmin()->getJson('/api/v1/mbkm/reports/recognized_credits')->assertStatus(200)->assertJsonPath('success', true);
        $this->asAdmin()->getJson('/api/v1/mbkm/dashboard/admin')->assertStatus(200)->assertJsonPath('success', true);

        $this->asStudent()->getJson('/api/v1/mbkm/dashboard/student')
            ->assertStatus(200)
            ->assertJsonPath('data.role', 'mahasiswa');

        // Workflow history was recorded throughout.
        $this->asAdmin()->getJson("/api/v1/mbkm/participants/{$participantId}/history")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertGreaterThan(3, count($this->asAdmin()->getJson("/api/v1/mbkm/participants/{$participantId}/history")->json('data')));
    }

    public function test_program_quota_is_enforced_on_participant_assignment(): void
    {
        $program = $this->createProgram(['quota' => 1]);
        $this->configureAssessmentWeights($program);

        $this->asAdmin()->postJson("/api/v1/mbkm/programs/{$program->id}/transition", [
            'status' => ProgramStatus::PUBLISHED->value,
        ])->assertStatus(200);
        $this->asAdmin()->postJson("/api/v1/mbkm/programs/{$program->id}/transition", [
            'status' => ProgramStatus::REGISTRATION_CLOSED->value,
        ])->assertStatus(200);
        $this->asAdmin()->postJson("/api/v1/mbkm/programs/{$program->id}/transition", [
            'status' => ProgramStatus::SELECTION->value,
        ])->assertStatus(200);

        $students = Student::where('study_program_id', $this->student->study_program_id)->take(2)->get();
        $this->assertCount(2, $students);

        $applicationIds = [];

        foreach ($students as $student) {
            $application = MbkmApplication::create([
                'registration_number' => 'MBKM/QUOTA/' . $student->id,
                'program_id' => $program->id,
                'student_id' => $student->id,
                'status' => ApplicationStatus::SELECTED,
            ]);
            $applicationIds[] = $application->id;
        }

        $this->asAdmin()->postJson('/api/v1/mbkm/participants/assign', [
            'application_id' => $applicationIds[0],
        ])->assertStatus(201);

        // Second participant would exceed the quota of 1.
        $this->asAdmin()->postJson('/api/v1/mbkm/participants/assign', [
            'application_id' => $applicationIds[1],
        ])->assertStatus(422);

        $this->assertSame(1, MbkmParticipant::where('program_id', $program->id)->count());
    }

    public function test_program_lifecycle_rejects_invalid_transition(): void
    {
        $program = $this->createProgram();

        // draft -> ongoing is not an allowed transition.
        $this->asAdmin()->postJson("/api/v1/mbkm/programs/{$program->id}/transition", [
            'status' => ProgramStatus::ONGOING->value,
        ])->assertStatus(422);
    }

    public function test_recognition_credit_ceiling_is_enforced(): void
    {
        $program = $this->createProgram(['max_recognized_credits' => 3]);
        $participant = $this->makeParticipant($program);

        $curriculum = Curriculum::where('study_program_id', $this->student->study_program_id)
            ->where('status', CurriculumStatus::ACTIVE)
            ->firstOrFail();

        $subjects = CurriculumSubject::whereHas('curriculumSemester', fn ($q) => $q->where('curriculum_id', $curriculum->id))
            ->with('course')
            ->get()
            ->filter(fn ($s) => $s->course !== null && (int) $s->course->credits >= 3)
            ->take(2)
            ->values();

        if ($subjects->count() < 2) {
            $this->markTestSkipped('Butuh dua mata kuliah kurikulum dengan SKS >= 3.');
        }

        $this->asAdmin()->postJson("/api/v1/mbkm/participants/{$participant->id}/recognitions", [
            'course_id' => $subjects[0]->course->id,
            'credits' => 3,
        ])->assertStatus(201);

        // Second recognition exceeds the 3 SKS program ceiling.
        $this->asAdmin()->postJson("/api/v1/mbkm/participants/{$participant->id}/recognitions", [
            'course_id' => $subjects[1]->course->id,
            'credits' => 3,
        ])->assertStatus(422);
    }

    public function test_completion_reports_unmet_requirements(): void
    {
        $program = $this->createProgram(['requires_final_report' => true]);
        $this->configureAssessmentWeights($program);
        $participant = $this->makeParticipant($program);

        $this->asAdmin()->postJson("/api/v1/mbkm/participants/{$participant->id}/start")->assertStatus(200);

        $evaluation = $this->asAdmin()->getJson("/api/v1/mbkm/participants/{$participant->id}/completion")
            ->assertStatus(200);

        $this->assertFalse($evaluation->json('data.is_complete'));
        $codes = collect($evaluation->json('data.requirements'))->pluck('code')->all();
        $this->assertContains('final_report', $codes);
        $this->assertContains('logbook', $codes);

        // Verifying without force must not mark the participant completed.
        $this->asAdmin()->postJson("/api/v1/mbkm/participants/{$participant->id}/completion/verify")
            ->assertStatus(200)
            ->assertJsonPath('data.status', CompletionStatus::REQUIREMENTS_UNMET->value);

        $this->assertNotSame(ParticipantStatus::COMPLETED, MbkmParticipant::findOrFail($participant->id)->status);
    }

    /**
     * Helper: build a selected application + participant directly (bypassing the
     * full selection UI) so focused tests stay short.
     */
    protected function makeParticipant(MbkmProgram $program): MbkmParticipant
    {
        $application = MbkmApplication::create([
            'registration_number' => 'MBKM/HELPER/' . uniqid(),
            'program_id' => $program->id,
            'student_id' => $this->student->id,
            'status' => ApplicationStatus::SELECTED,
        ]);

        return $this->asAdmin()->postJson('/api/v1/mbkm/participants/assign', [
            'application_id' => $application->id,
        ])->assertStatus(201)->json('data') ? MbkmParticipant::findOrFail(
            MbkmApplication::findOrFail($application->id)->participant->id
        ) : throw new \RuntimeException('Gagal membuat peserta uji.');
    }

    /**
     * The partner-assessment shortcut must be scoped to the participant, not
     * just gated by the module-wide `mbkm.assessment.record` permission.
     *
     * Regression: the endpoint used to skip the per-participant check entirely,
     * so any lecturer holding that permission could score any student.
     */
    public function test_partner_assessment_is_scoped_to_the_participant(): void
    {
        $program = $this->createProgram();
        $this->configureAssessmentWeights($program);
        $participant = $this->makeParticipant($program);

        $componentId = MbkmAssessmentComponent::where('program_id', $program->id)
            ->orderBy('id')
            ->value('id');

        $payload = [
            'component_id' => $componentId,
            'assessor_name' => 'HRD PT Uji Coba',
            'score' => 88,
            'feedback' => 'Kinerja baik.',
        ];

        $url = "/api/v1/mbkm/participants/{$participant->id}/assessments/partner";

        // A plain lecturer does hold mbkm.assessment.record, but does not
        // supervise this participant -> denied.
        $this->asLecturer()->postJson($url, $payload)->assertStatus(403);

        // The participant may not record a partner score for themself.
        $this->asStudent()->postJson($url, $payload)->assertStatus(403);

        $this->assertDatabaseCount('mbkm_assessments', 0);

        // A module manager may record it on the partner's behalf.
        $this->asAdmin()->postJson($url, $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.assessor_type', 'partner');

        $this->assertDatabaseCount('mbkm_assessments', 1);
    }

    /**
     * Component `type` and `assessor_type` are two different vocabularies and
     * both are validated against their enums — previously the controller
     * accepted any string, so a component could be configured with an assessor
     * type that the scoring endpoint later rejected.
     */
    public function test_assessment_component_vocabulary_is_validated(): void
    {
        $program = $this->createProgram();
        $url = "/api/v1/mbkm/programs/{$program->id}/assessment-components";

        // `presentation` is a component type, not an assessor type.
        $this->asAdmin()->putJson($url, [
            'components' => [
                ['code' => 'A', 'name' => 'Presentasi', 'weight' => 50, 'assessor_type' => 'presentation'],
                ['code' => 'B', 'name' => 'Logbook', 'weight' => 50, 'assessor_type' => 'internal_supervisor'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['components.0.assessor_type']);

        // `final_report` is not a component type either.
        $this->asAdmin()->putJson($url, [
            'components' => [
                ['code' => 'A', 'name' => 'Laporan', 'weight' => 100, 'type' => 'final_report'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['components.0.type']);

        // The real vocabulary is accepted.
        $this->configureAssessmentWeights($program);

        // Scoped to this program: the seeder already configured its own sample
        // program, so a global count would be misleading.
        $this->assertSame(
            5,
            MbkmAssessmentComponent::where('program_id', $program->id)->count()
        );
    }

    /**
     * Reports must be downloadable as CSV using the same filters as the
     * on-screen view.
     */
    public function test_report_can_be_exported_as_csv(): void
    {
        $program = $this->createProgram();

        $response = $this->asAdmin()->get('/api/v1/mbkm/reports/programs/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $csv = $response->streamedContent();

        $this->assertStringContainsString('code', $csv);
        $this->assertStringContainsString($program->code, $csv);

        // Unknown report keys are rejected before streaming starts.
        $this->asAdmin()->getJson('/api/v1/mbkm/reports/not_a_report/export')->assertStatus(404);
    }

    /**
     * The document listing endpoint is not bound to a single participant, so it
     * must scope results to the viewer. Regression: it used to return every
     * document in the module (KTM/CV/certificates of all students) to any
     * authenticated user.
     */
    public function test_document_listing_is_scoped_to_the_viewer(): void
    {
        $program = $this->createProgram();
        $mine = $this->makeParticipant($program);

        $otherStudent = Student::where('student_number', '202501002')->firstOrFail();
        $otherApplication = MbkmApplication::create([
            'registration_number' => 'MBKM/OTHER/' . uniqid(),
            'program_id' => $program->id,
            'student_id' => $otherStudent->id,
            'status' => ApplicationStatus::SELECTED,
        ]);
        $otherParticipantId = $this->asAdmin()->postJson('/api/v1/mbkm/participants/assign', [
            'application_id' => $otherApplication->id,
        ])->assertStatus(201)->json('data.id');

        $ownDoc = $this->makeDocument(MbkmParticipant::class, $mine->id, 'KTM Saya');
        $otherDoc = $this->makeDocument(MbkmParticipant::class, $otherParticipantId, 'KTM Mahasiswa Lain');

        // Student: only their own document.
        $this->asStudent()->getJson('/api/v1/mbkm/documents')
            ->assertStatus(200)
            ->assertJsonFragment(['title' => 'KTM Saya'])
            ->assertJsonMissing(['title' => 'KTM Mahasiswa Lain']);

        // A lecturer who supervises nobody sees nothing.
        $this->asLecturer()->getJson('/api/v1/mbkm/documents')
            ->assertStatus(200)
            ->assertJsonMissing(['title' => 'KTM Saya'])
            ->assertJsonMissing(['title' => 'KTM Mahasiswa Lain']);

        // A module manager sees everything.
        $this->asAdmin()->getJson('/api/v1/mbkm/documents')
            ->assertStatus(200)
            ->assertJsonFragment(['title' => 'KTM Saya'])
            ->assertJsonFragment(['title' => 'KTM Mahasiswa Lain']);

        // Verifying/deleting someone else's document is denied for the student.
        $this->asStudent()->putJson("/api/v1/mbkm/documents/{$otherDoc->id}/verify", [
            'status' => 'verified',
        ])->assertStatus(403);
    }

    /**
     * Holding a module-wide permission such as `mbkm.participants.manage` is not
     * on its own enough: an administrator scoped to one study program must not
     * be able to start/place/finalise another program's participant.
     */
    public function test_study_program_scoped_admin_cannot_touch_other_programs_participants(): void
    {
        $program = $this->createProgram();
        $participant = $this->makeParticipant($program);
        $this->makeDocument(MbkmParticipant::class, $participant->id, 'KTM Peserta Prodi');

        // Permissions are only granted through roles in this codebase, so build
        // a "kaprodi" style role and attach it to the lecturer's user.
        $scopedRole = Role::create([
            'name' => 'kaprodi_uji',
            'display_name' => 'Kaprodi (uji)',
            'is_system' => false,
        ]);
        $scopedRole->permissions()->sync(
            Permission::whereIn('name', ['mbkm.participants.manage', 'mbkm.manage_study_program'])->pluck('id')
        );
        $this->lecturer->user->assignRole($scopedRole);

        $homeProgramId = (int) $participant->student->study_program_id;
        $foreignProgramId = (int) StudyProgram::where('id', '!=', $homeProgramId)->value('id');

        // Scoped to a different study program -> denied, and the participant's
        // documents stay invisible.
        $this->lecturer->update(['homebase_study_program_id' => $foreignProgramId]);
        $this->asLecturer()->postJson("/api/v1/mbkm/participants/{$participant->id}/start")
            ->assertStatus(403);
        $this->asLecturer()->getJson('/api/v1/mbkm/documents')
            ->assertStatus(200)
            ->assertJsonMissing(['title' => 'KTM Peserta Prodi']);

        // Scoped to the participant's own study program -> allowed and visible.
        // The kaprodi does NOT supervise this participant, so this also guards
        // the branch ordering inside visibleStudentIds().
        $this->lecturer->update(['homebase_study_program_id' => $homeProgramId]);
        $this->asLecturer()->postJson("/api/v1/mbkm/participants/{$participant->id}/start")
            ->assertStatus(200);
        $this->asLecturer()->getJson('/api/v1/mbkm/documents')
            ->assertStatus(200)
            ->assertJsonFragment(['title' => 'KTM Peserta Prodi']);
    }

    /**
     * A finalized logbook must stay frozen. Regression: `review()` had no
     * finalized guard, so a supervisor could push an approved (locked) entry
     * back to `revision_required` — which unlocked it for the student to edit
     * through the normal update endpoint.
     */
    public function test_finalized_logbook_cannot_be_reopened_for_editing(): void
    {
        $program = $this->createProgram();
        $participant = $this->makeParticipant($program);

        $logId = $this->asStudent()->postJson("/api/v1/mbkm/participants/{$participant->id}/logbooks", [
            'log_date' => now()->toDateString(),
            'activity' => 'Aktivitas awal',
            'duration_hours' => 4,
        ])->assertStatus(201)->json('data.id');

        // A draft may not be reviewed directly — the student must submit first.
        $this->asAdmin()->postJson("/api/v1/mbkm/logbooks/{$logId}/review", [
            'decision' => LogbookStatus::APPROVED->value,
        ])->assertStatus(422);

        $this->asStudent()->postJson("/api/v1/mbkm/logbooks/{$logId}/submit")->assertStatus(200);

        $this->asAdmin()->postJson("/api/v1/mbkm/logbooks/{$logId}/review", [
            'decision' => LogbookStatus::APPROVED->value,
        ])->assertStatus(200);

        // Re-reviewing a finalized entry is refused...
        $this->asAdmin()->postJson("/api/v1/mbkm/logbooks/{$logId}/review", [
            'decision' => LogbookStatus::REVISION_REQUIRED->value,
        ])->assertStatus(422);

        // ...so the student still cannot edit it.
        $this->asStudent()->putJson("/api/v1/mbkm/logbooks/{$logId}", [
            'log_date' => now()->toDateString(),
            'activity' => 'Diubah setelah disetujui',
        ])->assertStatus(422);

        $final = MbkmActivityLog::findOrFail($logId);
        $this->assertSame(
            LogbookStatus::APPROVED,
            $final->status instanceof LogbookStatus ? $final->status : LogbookStatus::from($final->status)
        );
    }

    /**
     * The final MBKM score must not be frozen from a partial assessment: the
     * number flows into the academic result / KHS, so an understated grade would
     * become permanent.
     */
    public function test_final_score_cannot_be_frozen_from_an_incomplete_assessment(): void
    {
        $program = $this->createProgram();
        $this->configureAssessmentWeights($program);
        $participant = $this->makeParticipant($program);

        $components = MbkmAssessmentComponent::where('program_id', $program->id)
            ->orderBy('id')
            ->get();

        $this->assertGreaterThan(1, $components->count());

        // Score only the first component.
        $this->asAdmin()->postJson("/api/v1/mbkm/participants/{$participant->id}/assessments", [
            'component_id' => $components->first()->id,
            'score' => 90,
            'max_score' => 100,
        ])->assertStatus(201);

        $this->asAdmin()->postJson("/api/v1/mbkm/participants/{$participant->id}/finalize-score")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['assessment']);

        $this->assertNull(MbkmParticipant::findOrFail($participant->id)->final_score);

        // Score the remainder, then finalisation succeeds.
        foreach ($components->slice(1) as $component) {
            $this->asAdmin()->postJson("/api/v1/mbkm/participants/{$participant->id}/assessments", [
                'component_id' => $component->id,
                'score' => 90,
                'max_score' => 100,
            ])->assertStatus(201);
        }

        $this->asAdmin()->postJson("/api/v1/mbkm/participants/{$participant->id}/finalize-score")
            ->assertStatus(200)
            ->assertJsonPath('data.final_score', 90);
    }

    /**
     * The generic participant update must not be a state-machine back door.
     *
     * Regression: `status` was written straight to the column after nothing
     * more than an `in:` check against the enum, so a caller could jump from
     * `assigned` straight to `completed` — skipping placement, learning
     * agreement, logbook, assessment and completion verification, still
     * consuming a program quota slot, and leaving no row in
     * `mbkm_status_histories` because the history service was bypassed.
     */
    public function test_participant_status_cannot_skip_the_workflow(): void
    {
        $program = $this->createProgram();
        $participant = $this->makeParticipant($program);

        $statusOf = fn (): string => MbkmParticipant::findOrFail($participant->id)->status->value;

        $this->assertSame(ParticipantStatus::ASSIGNED->value, $statusOf());

        // Workflow-only target: completion has its own endpoint.
        $this->asAdmin()->putJson("/api/v1/mbkm/participants/{$participant->id}", [
            'status' => ParticipantStatus::COMPLETED->value,
        ])->assertStatus(422)->assertJsonValidationErrors('status');

        // Workflow-only target: execution starts through `start`.
        $this->asAdmin()->putJson("/api/v1/mbkm/participants/{$participant->id}", [
            'status' => ParticipantStatus::ONGOING->value,
        ])->assertStatus(422)->assertJsonValidationErrors('status');

        // Not a legal transition from `assigned` at all.
        $this->asAdmin()->putJson("/api/v1/mbkm/participants/{$participant->id}", [
            'status' => ParticipantStatus::FAILED->value,
        ])->assertStatus(422)->assertJsonValidationErrors('status');

        $this->assertSame(ParticipantStatus::ASSIGNED->value, $statusOf());
        $this->assertDatabaseMissing('mbkm_status_histories', [
            'entity_id' => $participant->id,
            'to_status' => ParticipantStatus::COMPLETED->value,
        ]);

        // The proper endpoint still works.
        $this->asAdmin()->postJson("/api/v1/mbkm/participants/{$participant->id}/start")
            ->assertStatus(200);
        $this->assertSame(ParticipantStatus::ONGOING->value, $statusOf());

        // `failed` is the only status with no dedicated endpoint, so it is the
        // only change the administrative update may make — and it IS audited.
        $this->asAdmin()->putJson("/api/v1/mbkm/participants/{$participant->id}", [
            'status' => ParticipantStatus::FAILED->value,
        ])->assertStatus(200);

        $this->assertSame(ParticipantStatus::FAILED->value, $statusOf());

        $this->assertDatabaseHas('mbkm_status_histories', [
            'entity_id' => $participant->id,
            'action' => 'participant.status_changed',
            'from_status' => ParticipantStatus::ONGOING->value,
            'to_status' => ParticipantStatus::FAILED->value,
        ]);
    }

    /**
     * Deciding a withdrawal mutates the participant lifecycle, so it must be
     * scoped to the actor and must not be repeatable.
     *
     * Regression: the method had no per-participant check (only the module-wide
     * route permission) and no "already decided" guard, so a scoped admin could
     * withdraw a participant outside their scope and a decided request could be
     * decided again — each re-decision appending another history row.
     */
    public function test_withdrawal_decision_is_scoped_and_cannot_be_repeated(): void
    {
        $program = $this->createProgram();
        $participant = $this->makeParticipant($program);

        $withdrawal = MbkmWithdrawalRequest::create([
            'participant_id' => $participant->id,
            'type' => WithdrawalType::WITHDRAWAL,
            'reason' => 'Alasan uji coba.',
            'status' => 'pending',
            'requested_by' => $this->student->user_id,
        ]);

        // Students never decide their own withdrawal.
        $this->asStudent()->postJson("/api/v1/mbkm/withdrawals/{$withdrawal->id}/decide", [
            'decision' => 'approved',
        ])->assertStatus(403);

        // A study-program scoped manager from another prodi is denied by the
        // in-method check even though they hold the route permission.
        $scopedRole = Role::create([
            'name' => 'kaprodi_withdrawal_uji',
            'display_name' => 'Kaprodi (uji withdrawal)',
            'is_system' => false,
        ]);
        $scopedRole->permissions()->sync(
            Permission::whereIn('name', ['mbkm.participants.manage', 'mbkm.manage_study_program'])->pluck('id')
        );
        $this->lecturer->user->assignRole($scopedRole);

        $homeProgramId = (int) $participant->student->study_program_id;
        $foreignProgramId = (int) StudyProgram::where('id', '!=', $homeProgramId)->value('id');
        $this->lecturer->update(['homebase_study_program_id' => $foreignProgramId]);

        $this->asLecturer()->postJson("/api/v1/mbkm/withdrawals/{$withdrawal->id}/decide", [
            'decision' => 'approved',
        ])->assertStatus(403);

        // The scoped admin of the participant's own program may decide.
        $this->lecturer->update(['homebase_study_program_id' => $homeProgramId]);
        $this->asLecturer()->postJson("/api/v1/mbkm/withdrawals/{$withdrawal->id}/decide", [
            'decision' => 'approved',
        ])->assertStatus(200);

        $this->assertSame(
            ParticipantStatus::WITHDRAWN->value,
            MbkmParticipant::findOrFail($participant->id)->status->value
        );

        // Re-deciding is refused: it would rewrite the status a second time.
        $this->asAdmin()->postJson("/api/v1/mbkm/withdrawals/{$withdrawal->id}/decide", [
            'decision' => 'rejected',
        ])->assertStatus(422);

        $this->assertSame(
            ParticipantStatus::WITHDRAWN->value,
            MbkmParticipant::findOrFail($participant->id)->status->value
        );
    }

    /**
     * Withdrawal / extension listings must be scoped to the viewer.
     *
     * Regression: both methods only narrowed the student case, so a plain
     * lecturer who supervises nobody (and any authenticated user without an
     * MBKM role) could list every request in the institution.
     */
    public function test_withdrawal_and_extension_listing_is_scoped_to_the_viewer(): void
    {
        $program = $this->createProgram();
        $participant = $this->makeParticipant($program);

        MbkmWithdrawalRequest::create([
            'participant_id' => $participant->id,
            'type' => WithdrawalType::WITHDRAWAL,
            'reason' => 'Alasan rahasia mahasiswa.',
            'status' => 'pending',
            'requested_by' => $this->student->user_id,
        ]);

        MbkmExtensionRequest::create([
            'participant_id' => $participant->id,
            'old_end_date' => now()->toDateString(),
            'new_end_date' => now()->addMonth()->toDateString(),
            'reason' => 'Alasan perpanjangan rahasia.',
            'status' => 'pending',
            'requested_by' => $this->student->user_id,
        ]);

        // The owner sees their own requests.
        $this->asStudent()->getJson('/api/v1/mbkm/withdrawals')
            ->assertStatus(200)
            ->assertJsonFragment(['reason' => 'Alasan rahasia mahasiswa.']);

        $this->asStudent()->getJson('/api/v1/mbkm/extensions')
            ->assertStatus(200)
            ->assertJsonFragment(['reason' => 'Alasan perpanjangan rahasia.']);

        // A lecturer who supervises nobody sees nothing at all.
        $this->asLecturer()->getJson('/api/v1/mbkm/withdrawals')
            ->assertStatus(200)
            ->assertJsonMissing(['reason' => 'Alasan rahasia mahasiswa.']);

        $this->asLecturer()->getJson('/api/v1/mbkm/extensions')
            ->assertStatus(200)
            ->assertJsonMissing(['reason' => 'Alasan perpanjangan rahasia.']);

        // A module manager still sees everything.
        $this->asAdmin()->getJson('/api/v1/mbkm/withdrawals')
            ->assertStatus(200)
            ->assertJsonFragment(['reason' => 'Alasan rahasia mahasiswa.']);
    }

    /**
     * The institution-wide workflow audit feed is staff-only.
     *
     * Regression: the route was granted via `mbkm.participants.view`, which the
     * `dosen` role holds — so a plain lecturer could read every student's MBKM
     * status transitions, because the feed itself is not scoped.
     */
    public function test_global_workflow_history_is_staff_only(): void
    {
        $program = $this->createProgram();
        $this->makeParticipant($program);

        $this->asLecturer()->getJson('/api/v1/mbkm/history')->assertStatus(403);
        $this->asStudent()->getJson('/api/v1/mbkm/history')->assertStatus(403);
        $this->asAdmin()->getJson('/api/v1/mbkm/history')->assertStatus(200);
    }

    /**
     * A supporting document must belong to the participant it is filed for.
     *
     * Regression: `document_id` was validated with `exists:mbkm_documents,id`
     * only, so a student could attach another student's document to their own
     * withdrawal request and expose it to the reviewer.
     */
    public function test_withdrawal_document_must_belong_to_the_participant(): void
    {
        $program = $this->createProgram();
        $participant = $this->makeParticipant($program);

        // A document that exists in the module, but not on this participant.
        $foreign = $this->makeDocument(MbkmProgram::class, $program->id, 'Dokumen Milik Program');

        $this->asStudent()->postJson("/api/v1/mbkm/participants/{$participant->id}/withdrawals", [
            'type' => WithdrawalType::WITHDRAWAL->value,
            'reason' => 'Ingin berhenti.',
            'document_id' => $foreign->id,
        ])->assertStatus(422);

        // A document that really belongs to the participant is accepted.
        $own = $this->makeDocument(MbkmParticipant::class, $participant->id, 'Surat Permohonan');

        $this->asStudent()->postJson("/api/v1/mbkm/participants/{$participant->id}/withdrawals", [
            'type' => WithdrawalType::WITHDRAWAL->value,
            'reason' => 'Ingin berhenti.',
            'document_id' => $own->id,
        ])->assertStatus(201);
    }

    /**
     * Recognition writes must be serialised per participant.
     *
     * Regression: `assertNoDuplicate()` and `assertCreditCeiling()` are both
     * read-then-write and neither locked anything, and `transition()` validated
     * the state machine on a snapshot taken *outside* its transaction. Two
     * concurrent creates for the same course therefore both passed and both
     * inserted (the same course counted twice in the KHS, total above
     * `max_recognized_credits`), and two concurrent approvals both pushed the
     * same credits into the academic pipeline.
     *
     * A real race cannot be reproduced on the in-memory SQLite test database, so
     * this asserts the guard that makes it impossible: the row is re-read under
     * the lock and a stale model is rejected instead of applied.
     */
    public function test_recognition_transition_rejects_a_stale_model(): void
    {
        $program = $this->createProgram();
        $participant = $this->makeParticipant($program);

        $curriculum = Curriculum::where('study_program_id', $this->student->study_program_id)
            ->where('status', CurriculumStatus::ACTIVE)
            ->firstOrFail();

        $subject = CurriculumSubject::whereHas('curriculumSemester', fn ($q) => $q->where('curriculum_id', $curriculum->id))
            ->with('course')
            ->get()
            ->first(fn ($s) => $s->course !== null && (int) $s->course->credits >= 2);

        if (!$subject) {
            $this->markTestSkipped('Butuh mata kuliah kurikulum dengan SKS >= 2.');
        }

        $this->asAdmin()->postJson("/api/v1/mbkm/participants/{$participant->id}/recognitions", [
            'course_id' => $subject->course->id,
            'credits' => (int) $subject->course->credits,
        ])->assertStatus(201);

        $recognition = MbkmRecognition::where('participant_id', $participant->id)->firstOrFail();

        // The normal path still works now that the lock + re-read are in place.
        $this->asAdmin()->postJson("/api/v1/mbkm/recognitions/{$recognition->id}/transition", [
            'status' => RecognitionStatus::SUBMITTED->value,
        ])->assertStatus(200);

        // Hold a stale copy, then move the row on as a concurrent request would.
        $stale = MbkmRecognition::findOrFail($recognition->id);

        MbkmRecognition::whereKey($stale->id)->update([
            'status' => RecognitionStatus::REVIEWED->value,
        ]);

        // `submitted -> reviewed` is legal on paper, so the pre-check passes —
        // the guard that rejects it is the re-read inside the transaction.
        $this->expectException(ValidationException::class);

        app(MbkmRecognitionService::class)->transition(
            $stale,
            RecognitionStatus::REVIEWED->value,
            $this->admin
        );
    }

    /**
     * A participant may only remove their own self-assessment.
     *
     * Regression: `destroy()` only checked participant ownership, so a student
     * could delete the score their *supervisor* (or the partner) had recorded for
     * a component. Deleting the low score raised the weighted average of the rows
     * that remained — and with it the final grade written to the KHS. `store()`
     * already restricted students to `assessor_type = self`; the delete path did
     * not mirror that rule.
     */
    public function test_student_cannot_delete_another_assessors_score(): void
    {
        $program = $this->createProgram();
        $this->configureAssessmentWeights($program);
        $participant = $this->makeParticipant($program);

        $componentId = MbkmAssessmentComponent::where('program_id', $program->id)
            ->orderBy('id')
            ->value('id');

        // A supervisor-side score (assessor_type comes from the component).
        $this->asAdmin()->postJson("/api/v1/mbkm/participants/{$participant->id}/assessments", [
            'component_id' => $componentId,
            'score' => 50,
        ])->assertStatus(201);

        $supervisorRow = MbkmAssessment::where('participant_id', $participant->id)
            ->where('assessor_type', '!=', AssessorType::SELF->value)
            ->firstOrFail();

        $this->asStudent()->deleteJson("/api/v1/mbkm/assessments/{$supervisorRow->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('mbkm_assessments', ['id' => $supervisorRow->id]);

        // Their own self-assessment, however, is theirs to remove.
        $this->asStudent()->postJson("/api/v1/mbkm/participants/{$participant->id}/assessments", [
            'component_id' => $componentId,
            'score' => 80,
        ])->assertStatus(201);

        $selfRow = MbkmAssessment::where('participant_id', $participant->id)
            ->where('assessor_type', AssessorType::SELF->value)
            ->firstOrFail();

        $this->asStudent()->deleteJson("/api/v1/mbkm/assessments/{$selfRow->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('mbkm_assessments', ['id' => $selfRow->id]);
    }

    /**
     * A finalized score freezes its own components.
     *
     * Regression: neither `record()` nor `destroy()` looked at
     * `score_finalized_at`, so a component could be added, overwritten or
     * deleted after finalization — silently desynchronising `final_score` from
     * the components it claims to summarise.
     */
    public function test_assessment_is_frozen_after_the_score_is_finalized(): void
    {
        $program = $this->createProgram();
        $this->configureAssessmentWeights($program);
        $participant = $this->makeParticipant($program);

        $components = MbkmAssessmentComponent::where('program_id', $program->id)
            ->orderBy('id')
            ->get();

        foreach ($components as $component) {
            $this->asAdmin()->postJson("/api/v1/mbkm/participants/{$participant->id}/assessments", [
                'component_id' => $component->id,
                'score' => 90,
                'max_score' => 100,
            ])->assertStatus(201);
        }

        $this->asAdmin()->postJson("/api/v1/mbkm/participants/{$participant->id}/finalize-score")
            ->assertStatus(200)
            ->assertJsonPath('data.final_score', 90);

        $row = MbkmAssessment::where('participant_id', $participant->id)->firstOrFail();

        // Overwriting a component after finalization is refused.
        $this->asAdmin()->postJson("/api/v1/mbkm/participants/{$participant->id}/assessments", [
            'component_id' => $row->component_id,
            'score' => 10,
        ])->assertStatus(422);

        // ... and so is deleting one.
        $this->asAdmin()->deleteJson("/api/v1/mbkm/assessments/{$row->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('mbkm_assessments', ['id' => $row->id, 'score' => 90]);
        $this->assertSame(90.0, (float) MbkmParticipant::findOrFail($participant->id)->final_score);
    }

    // ---------------------------------------------------------------------
    // Round 8 — role-branch scoping
    //
    // Every listing endpoint used to scope with ad-hoc role branches
    // (`if (student) … elseif (plain lecturer) …`). That construction handles
    // the two roles it names and silently leaves *every other* role
    // unrestricted: `admin_akademik` (which holds no mbkm.* permission at all)
    // fell through to the admin dashboard and saw every participant in the
    // institution, and a kaprodi — who is also a `dosen`, so
    // `isMbkmLecturerOnly()` is false — matched no branch and was equally
    // unscoped. The shared `visibleStudentIds()` helper returns `null` for an
    // unrestricted manager and `[]` for everyone else, so an unhandled role
    // now sees nothing instead of everything.
    // ---------------------------------------------------------------------

    /**
     * Build a "kaprodi" style role and attach it to the shared lecturer.
     *
     * Permissions are only granted through roles in this codebase, and the
     * route middleware still has to be satisfied before the controller-level
     * scope check is reached — hence the explicit permission list.
     */
    protected function makeKaprodi(int $homeProgramId, array $permissions): void
    {
        $role = Role::create([
            'name' => 'kaprodi_uji_' . uniqid(),
            'display_name' => 'Kaprodi (uji)',
            'is_system' => false,
        ]);

        $role->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id'));

        $this->lecturer->user->assignRole($role);
        $this->lecturer->update(['homebase_study_program_id' => $homeProgramId]);
    }

    /**
     * Regression: `MbkmDashboardController::index()` used the admin dashboard as
     * its fallback for any authenticated user who was neither a student nor a
     * plain lecturer — so `admin_akademik`, which holds no mbkm.* permission at
     * all, was served institution-wide statistics.
     */
    public function test_dashboard_denies_a_role_without_mbkm_permissions(): void
    {
        $akademik = User::where('email', 'akademik@siakad.ac.id')->firstOrFail();
        $token = $akademik->createToken('akademik')->plainTextToken;
        auth()->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/mbkm/dashboard')
            ->assertStatus(403);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/mbkm/dashboard/admin')
            ->assertStatus(403);
    }

    /**
     * Regression: the four listings that carry no route permission at all
     * (`participants`, `recognitions`, `withdrawals`, `extensions`) relied
     * purely on the in-controller scope, which had no default-deny — so a role
     * with no mbkm.* permission listed every row in the institution.
     */
    public function test_listings_are_empty_for_a_role_without_mbkm_permissions(): void
    {
        $program = $this->createProgram();
        $this->makeParticipant($program);

        $akademik = User::where('email', 'akademik@siakad.ac.id')->firstOrFail();
        $token = $akademik->createToken('akademik')->plainTextToken;

        foreach (['participants', 'recognitions', 'withdrawals', 'extensions'] as $path) {
            auth()->forgetGuards();

            $this->withHeader('Authorization', "Bearer {$token}")
                ->getJson("/api/v1/mbkm/{$path}")
                ->assertStatus(200)
                ->assertJsonPath('meta.total', 0);
        }

        // Sanity: the data does exist, so the empty lists above are a scope
        // decision and not an empty database.
        $this->asAdmin()->getJson('/api/v1/mbkm/participants')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    /**
     * Regression: `verify()`, `decide()` and `score()` had no per-record scope
     * check whatsoever — they trusted the module-wide route permission
     * (`mbkm.applications.decide` and friends), so a kaprodi granted those
     * permissions could verify, score and decide applications belonging to any
     * other study program.
     */
    public function test_application_staff_actions_are_scoped_to_the_study_program(): void
    {
        $program = $this->createProgram();

        $application = MbkmApplication::create([
            'registration_number' => 'MBKM/SCOPE/' . uniqid(),
            'program_id' => $program->id,
            'student_id' => $this->student->id,
            'status' => ApplicationStatus::SUBMITTED,
        ]);

        $homeProgramId = (int) $this->student->study_program_id;
        $foreignProgramId = (int) StudyProgram::where('id', '!=', $homeProgramId)->value('id');

        $this->makeKaprodi($foreignProgramId, [
            'mbkm.applications.view',
            'mbkm.applications.verify',
            'mbkm.applications.decide',
            'mbkm.manage_study_program',
        ]);

        // Foreign study program: holding the permission is not proof of scope.
        // The guard runs before validation, so an invalid payload still yields
        // 403 rather than 422 — that ordering is itself part of the contract.
        $this->asLecturer()->postJson("/api/v1/mbkm/applications/{$application->id}/verify", [
            'decision' => ApplicationStatus::VERIFIED->value,
        ])->assertStatus(403);

        $this->asLecturer()->postJson("/api/v1/mbkm/applications/{$application->id}/decide", [
            'decision' => ApplicationStatus::SELECTED->value,
        ])->assertStatus(403);

        $this->asLecturer()->postJson("/api/v1/mbkm/applications/{$application->id}/score", [
            'criteria_id' => 1,
            'score' => 100,
        ])->assertStatus(403);

        $this->assertDatabaseHas('mbkm_applications', [
            'id' => $application->id,
            'status' => ApplicationStatus::SUBMITTED->value,
        ]);

        // Own study program: the same calls succeed.
        $this->lecturer->update(['homebase_study_program_id' => $homeProgramId]);

        $this->asLecturer()->postJson("/api/v1/mbkm/applications/{$application->id}/verify", [
            'decision' => ApplicationStatus::VERIFIED->value,
        ])->assertStatus(200);
    }

    /**
     * Regression: `mayAccessApplication()` handled only the owning student and
     * a plain supervising lecturer, then fell through to `false`. A kaprodi
     * therefore saw a list of their program's applications in which every
     * single detail view answered 403. Listing scope and record scope must
     * agree.
     */
    public function test_kaprodi_can_open_the_applications_their_list_shows(): void
    {
        $program = $this->createProgram();

        $application = MbkmApplication::create([
            'registration_number' => 'MBKM/CONSIST/' . uniqid(),
            'program_id' => $program->id,
            'student_id' => $this->student->id,
            'status' => ApplicationStatus::SUBMITTED,
        ]);

        $homeProgramId = (int) $this->student->study_program_id;
        $foreignProgramId = (int) StudyProgram::where('id', '!=', $homeProgramId)->value('id');

        $this->makeKaprodi($foreignProgramId, [
            'mbkm.applications.view',
            'mbkm.manage_study_program',
        ]);

        // Foreign program: neither the list nor the record.
        $this->asLecturer()->getJson('/api/v1/mbkm/applications')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 0);

        $this->asLecturer()->getJson("/api/v1/mbkm/applications/{$application->id}")
            ->assertStatus(403);

        $this->asLecturer()->getJson("/api/v1/mbkm/applications/{$application->id}/history")
            ->assertStatus(403);

        // Own program: the row appears in the list AND opens.
        $this->lecturer->update(['homebase_study_program_id' => $homeProgramId]);

        $this->asLecturer()->getJson('/api/v1/mbkm/applications')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonFragment(['id' => $application->id]);

        $this->asLecturer()->getJson("/api/v1/mbkm/applications/{$application->id}")
            ->assertStatus(200);

        $this->asLecturer()->getJson("/api/v1/mbkm/applications/{$application->id}/history")
            ->assertStatus(200);
    }

    /**
     * Regression: the same role-branch defect in `logbooks`, `issues` and
     * `participants`. A kaprodi is also a `dosen`, but `isMbkmLecturerOnly()`
     * is false for them, so no branch applied and they listed every logbook,
     * issue and participant in the institution.
     */
    public function test_kaprodi_listings_are_scoped_to_their_study_program(): void
    {
        $program = $this->createProgram();
        $this->makeParticipant($program);

        $homeProgramId = (int) $this->student->study_program_id;
        $foreignProgramId = (int) StudyProgram::where('id', '!=', $homeProgramId)->value('id');

        $this->makeKaprodi($foreignProgramId, [
            'mbkm.participants.view',
            'mbkm.logbook.view',
            'mbkm.applications.view',
            'mbkm.manage_study_program',
        ]);

        foreach (['participants', 'logbooks', 'issues', 'applications'] as $path) {
            $this->asLecturer()->getJson("/api/v1/mbkm/{$path}")
                ->assertStatus(200)
                ->assertJsonPath('meta.total', 0);
        }

        // Own program: the participant is visible again. Note the kaprodi does
        // NOT supervise this participant — this also guards the branch ordering
        // inside visibleStudentIds(), where the study-program branch must be
        // evaluated before the plain-lecturer branch.
        $this->lecturer->update(['homebase_study_program_id' => $homeProgramId]);

        $this->asLecturer()->getJson('/api/v1/mbkm/participants')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    /**
     * Helper: create a document row directly (upload plumbing is not the point).
     */
    protected function makeDocument(string $type, int $id, string $title): MbkmDocument
    {
        return MbkmDocument::create([
            'documentable_type' => $type,
            'documentable_id' => $id,
            'category' => 'ktm',
            'title' => $title,
            'original_name' => 'ktm.pdf',
            'file_path' => 'mbkm/documents/ktm.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'status' => DocumentStatus::UPLOADED,
        ]);
    }
}
