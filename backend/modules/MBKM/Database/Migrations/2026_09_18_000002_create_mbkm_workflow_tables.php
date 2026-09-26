<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MBKM workflow tables: application -> selection -> participant -> placement ->
 * supervisor -> learning agreement -> activity/logbook -> attendance ->
 * assessment -> recognition -> completion, plus withdrawal/extension and the
 * module-wide status history (audit trail).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Applications
        Schema::create('mbkm_applications', function (Blueprint $table) {
            $table->id();
            $table->string('registration_number', 50)->unique();
            $table->foreignId('program_id')->constrained('mbkm_programs')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->text('motivation_statement')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('verification_notes')->nullable();
            $table->decimal('selection_score', 8, 2)->nullable();
            $table->unsignedInteger('selection_rank')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_notes')->nullable();
            $table->dateTime('withdrawn_at')->nullable();
            $table->text('withdrawal_reason')->nullable();
            // Snapshot of the academic data used for eligibility at submit time.
            $table->json('academic_snapshot')->nullable();
            $table->timestamps();

            $table->index(['program_id', 'student_id']);
            $table->index(['program_id', 'status']);
        });

        // 2. Selection criteria (configurable per program)
        Schema::create('mbkm_selection_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('mbkm_programs')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('weight', 5, 2)->default(0);
            $table->decimal('max_score', 6, 2)->default(100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Selection scores (per application, per criteria, per reviewer)
        Schema::create('mbkm_selection_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('mbkm_applications')->cascadeOnDelete();
            $table->foreignId('criteria_id')->constrained('mbkm_selection_criteria')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('score', 6, 2);
            $table->text('notes')->nullable();
            $table->dateTime('scored_at')->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'criteria_id', 'reviewer_id'], 'mbkm_selection_score_unique');
        });

        // 4. Participants
        Schema::create('mbkm_participants', function (Blueprint $table) {
            $table->id();
            $table->string('participant_number', 50)->unique();
            $table->foreignId('program_id')->constrained('mbkm_programs')->restrictOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('mbkm_applications')->nullOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('original_end_date')->nullable();
            $table->string('status', 30)->default('assigned')->index();
            $table->decimal('final_score', 6, 2)->nullable();
            $table->string('letter_grade', 5)->nullable();
            $table->decimal('grade_point', 4, 2)->nullable();
            $table->unsignedSmallInteger('recognized_credits')->default(0);
            $table->dateTime('score_finalized_at')->nullable();
            $table->foreignId('score_finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('terminated_at')->nullable();
            $table->text('termination_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['program_id', 'student_id']);
            $table->index(['program_id', 'status']);
        });

        // 5. Placements (per participant, not per program)
        Schema::create('mbkm_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->unique()->constrained('mbkm_participants')->cascadeOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('mbkm_partners')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('mbkm_program_locations')->nullOnDelete();
            $table->string('division')->nullable();
            $table->string('position')->nullable();
            $table->string('batch')->nullable();
            $table->string('field_supervisor_name')->nullable();
            $table->string('field_supervisor_position')->nullable();
            $table->string('field_supervisor_email')->nullable();
            $table->string('field_supervisor_phone', 40)->nullable();
            $table->string('field_supervisor_organization')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 6. Supervisors (internal lecturer and/or external field supervisor)
        Schema::create('mbkm_supervisors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('mbkm_participants')->cascadeOnDelete();
            $table->foreignId('lecturer_id')->nullable()->constrained('lecturers')->nullOnDelete();
            $table->string('role', 30)->default('internal')->index();
            $table->string('external_name')->nullable();
            $table->string('external_position')->nullable();
            $table->string('external_email')->nullable();
            $table->string('external_phone', 40)->nullable();
            $table->string('external_organization')->nullable();
            $table->date('assigned_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['participant_id', 'role', 'lecturer_id'], 'mbkm_supervisor_unique');
        });

        // 7. Learning agreement / recognition plan
        Schema::create('mbkm_learning_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->unique()->constrained('mbkm_participants')->cascadeOnDelete();
            $table->string('title');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('locked_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('mbkm_learning_agreement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_agreement_id')->constrained('mbkm_learning_agreements')->cascadeOnDelete();
            $table->string('activity_title');
            $table->text('activity_description')->nullable();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignId('curriculum_subject_id')->nullable()->constrained('curriculum_subjects')->nullOnDelete();
            $table->unsignedSmallInteger('credits')->default(0);
            $table->string('target_grade', 5)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 8. Activity plans
        Schema::create('mbkm_activity_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('mbkm_participants')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('target_output')->nullable();
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->decimal('planned_hours', 6, 2)->nullable();
            $table->string('location')->nullable();
            $table->string('status', 20)->default('planned')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 9. Activity logs / logbook
        Schema::create('mbkm_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('mbkm_participants')->cascadeOnDelete();
            $table->foreignId('activity_plan_id')->nullable()->constrained('mbkm_activity_plans')->nullOnDelete();
            $table->date('log_date');
            $table->string('period_label', 50)->nullable();
            $table->string('activity');
            $table->text('description')->nullable();
            $table->decimal('duration_hours', 6, 2)->nullable();
            $table->text('output')->nullable();
            $table->string('location')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->unsignedTinyInteger('revision_count')->default(0);
            $table->dateTime('locked_at')->nullable();
            $table->timestamps();

            $table->index(['participant_id', 'log_date']);
        });

        // 10. MBKM attendance (separate from regular lecture attendance)
        Schema::create('mbkm_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('mbkm_participants')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('status', 20)->default('present')->index();
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->decimal('duration_hours', 6, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['participant_id', 'attendance_date']);
        });

        // 11. Issues / problem monitoring
        Schema::create('mbkm_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('mbkm_participants')->cascadeOnDelete();
            $table->string('category', 50)->default('general')->index();
            $table->string('severity', 20)->default('medium')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('lecturers')->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 12. Assessment components (configurable weight per program)
        Schema::create('mbkm_assessment_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('mbkm_programs')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('type', 40)->default('performance');
            $table->decimal('weight', 5, 2)->default(0);
            $table->decimal('max_score', 6, 2)->default(100);
            $table->string('assessor_type', 40)->default('internal_supervisor');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['program_id', 'code']);
        });

        // 13. Assessments (score per participant per component)
        Schema::create('mbkm_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('mbkm_participants')->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('mbkm_assessment_components')->cascadeOnDelete();
            $table->string('assessor_type', 40)->default('internal_supervisor')->index();
            $table->foreignId('assessor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assessor_lecturer_id')->nullable()->constrained('lecturers')->nullOnDelete();
            $table->string('assessor_name')->nullable();
            $table->decimal('score', 6, 2);
            $table->decimal('max_score', 6, 2)->default(100);
            $table->text('feedback')->nullable();
            $table->text('recommendation')->nullable();
            $table->string('status', 20)->default('submitted')->index();
            $table->dateTime('assessed_at')->nullable();
            $table->timestamps();

            $table->unique(['participant_id', 'component_id', 'assessor_type', 'assessor_user_id'], 'mbkm_assessment_unique');
        });

        // 14. Recognition / credit conversion
        Schema::create('mbkm_recognitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('mbkm_participants')->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('mbkm_programs')->restrictOnDelete();
            $table->foreignId('activity_log_id')->nullable()->constrained('mbkm_activity_logs')->nullOnDelete();
            $table->string('source_label')->nullable();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignId('curriculum_id')->nullable()->constrained('curricula')->nullOnDelete();
            $table->foreignId('curriculum_subject_id')->nullable()->constrained('curriculum_subjects')->nullOnDelete();
            $table->unsignedSmallInteger('credits')->default(0);
            $table->string('recognition_type', 30)->default('course_conversion');
            $table->decimal('score', 6, 2)->nullable();
            $table->string('letter_grade', 5)->nullable();
            $table->decimal('grade_point', 4, 2)->nullable();
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('locked_at')->nullable();
            // Integration columns into the existing academic pipeline
            $table->foreignId('academic_enrollment_id')->nullable()->constrained('student_enrollments')->nullOnDelete();
            $table->foreignId('academic_enrollment_item_id')->nullable()->constrained('student_enrollment_items')->nullOnDelete();
            $table->foreignId('academic_class_id')->nullable()->constrained('academic_classes')->nullOnDelete();
            $table->string('sync_status', 20)->default('pending')->index();
            $table->text('sync_message')->nullable();
            $table->dateTime('synced_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['participant_id', 'course_id']);
        });

        // 15. Completion verification
        Schema::create('mbkm_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->unique()->constrained('mbkm_participants')->cascadeOnDelete();
            $table->string('status', 30)->default('pending')->index();
            $table->json('requirements_snapshot')->nullable();
            $table->json('unmet_requirements')->nullable();
            $table->dateTime('checked_at')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('certificate_document_id')->nullable()->constrained('mbkm_documents')->nullOnDelete();
            $table->timestamps();
        });

        // 16. Extension requests
        Schema::create('mbkm_extension_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('mbkm_participants')->cascadeOnDelete();
            $table->date('old_end_date')->nullable();
            $table->date('new_end_date');
            $table->text('reason');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_notes')->nullable();
            $table->timestamps();
        });

        // 17. Withdrawal / cancellation / termination requests
        Schema::create('mbkm_withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('mbkm_participants')->cascadeOnDelete();
            $table->string('type', 30)->default('withdrawal')->index();
            $table->text('reason');
            $table->date('effective_date')->nullable();
            $table->foreignId('document_id')->nullable()->constrained('mbkm_documents')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_notes')->nullable();
            $table->timestamps();
        });

        // 18. Module-wide status history (workflow audit trail)
        Schema::create('mbkm_status_histories', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('action', 60);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id'], 'mbkm_status_histories_entity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mbkm_status_histories');
        Schema::dropIfExists('mbkm_withdrawal_requests');
        Schema::dropIfExists('mbkm_extension_requests');
        Schema::dropIfExists('mbkm_completions');
        Schema::dropIfExists('mbkm_recognitions');
        Schema::dropIfExists('mbkm_assessments');
        Schema::dropIfExists('mbkm_assessment_components');
        Schema::dropIfExists('mbkm_issues');
        Schema::dropIfExists('mbkm_attendances');
        Schema::dropIfExists('mbkm_activity_logs');
        Schema::dropIfExists('mbkm_activity_plans');
        Schema::dropIfExists('mbkm_learning_agreement_items');
        Schema::dropIfExists('mbkm_learning_agreements');
        Schema::dropIfExists('mbkm_supervisors');
        Schema::dropIfExists('mbkm_placements');
        Schema::dropIfExists('mbkm_participants');
        Schema::dropIfExists('mbkm_selection_scores');
        Schema::dropIfExists('mbkm_selection_criteria');
        Schema::dropIfExists('mbkm_applications');
    }
};
