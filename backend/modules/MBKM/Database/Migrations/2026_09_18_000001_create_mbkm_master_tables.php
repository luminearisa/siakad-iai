<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MBKM master data: program types, partners, programs, locations, requirements,
 * cooperation agreements, and the unified MBKM document store.
 *
 * The MBKM module keeps its own boundary but only ever references existing
 * academic entities (study_programs, faculties, semesters, courses) — it never
 * duplicates them.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Program types (reference/master data — never hardcoded conditions)
        Schema::create('mbkm_program_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // 2. Partners (mitra) — companies, universities, schools, government, etc.
        Schema::create('mbkm_partners', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('type', 40)->default('company')->index();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('country')->default('Indonesia');
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('contact_person_name')->nullable();
            $table->string('contact_person_position')->nullable();
            $table->string('contact_person_email')->nullable();
            $table->string('contact_person_phone', 40)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 3. MBKM Programs (master)
        Schema::create('mbkm_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_type_id')->constrained('mbkm_program_types')->restrictOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // Organizer + scope of authority
            $table->string('organizer_type', 40)->default('study_program')->index();
            $table->string('organizer_name')->nullable();
            $table->foreignId('faculty_id')->nullable()->constrained('faculties')->nullOnDelete();
            $table->foreignId('study_program_id')->nullable()->constrained('study_programs')->nullOnDelete();

            // Academic period + schedule
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->date('registration_start_date')->nullable();
            $table->date('registration_end_date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Capacity + target audience
            $table->unsignedInteger('quota')->nullable();
            $table->json('target_degree_levels')->nullable();
            $table->json('target_study_program_ids')->nullable();
            $table->json('target_admission_years')->nullable();
            $table->unsignedTinyInteger('min_semester')->nullable();
            $table->unsignedTinyInteger('max_semester')->nullable();
            $table->decimal('min_gpa', 4, 2)->nullable();
            $table->unsignedSmallInteger('min_credits')->nullable();
            $table->unsignedSmallInteger('max_recognized_credits')->nullable();
            $table->unsignedTinyInteger('participation_limit')->nullable();
            $table->json('required_passed_course_ids')->nullable();

            // Delivery mode
            $table->string('location_mode', 30)->default('off_campus')->index();

            // Program policy flags — drive which workflow steps are mandatory
            $table->boolean('requires_documents')->default(false);
            $table->boolean('requires_learning_agreement')->default(false);
            $table->boolean('requires_attendance')->default(false);
            $table->boolean('requires_logbook')->default(true);
            $table->string('logbook_period', 20)->default('weekly');
            $table->boolean('requires_assessment')->default(true);
            $table->boolean('requires_final_report')->default(false);
            $table->boolean('requires_recognition')->default(true);
            $table->boolean('allow_public_participant_count')->default(true);
            $table->decimal('min_attendance_percentage', 5, 2)->nullable();

            $table->string('status', 30)->default('draft')->index();
            $table->text('requirements_text')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'semester_id']);
        });

        // 4. Program locations (one program -> many locations)
        Schema::create('mbkm_program_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('mbkm_programs')->cascadeOnDelete();
            $table->string('name');
            $table->string('location_mode', 30)->default('off_campus');
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('country')->default('Indonesia');
            $table->boolean('is_remote')->default(false);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 5. Program requirements (configurable, never hardcoded)
        Schema::create('mbkm_program_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('mbkm_programs')->cascadeOnDelete();
            $table->string('type', 30)->default('academic')->index();
            $table->string('code', 50)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('is_document')->default(false);
            // Machine-evaluable rule, e.g. {"field":"gpa","operator":">=","value":3.0}
            $table->json('rule')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // 6. Cooperation agreements (MoU / MoA / IA / PKS / ...)
        Schema::create('mbkm_cooperations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('mbkm_partners')->cascadeOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('mbkm_programs')->nullOnDelete();
            $table->string('type', 30)->default('mou')->index();
            $table->string('number')->nullable();
            $table->string('title');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 7. Unified MBKM document store (single storage for the whole module)
        Schema::create('mbkm_documents', function (Blueprint $table) {
            $table->id();
            $table->string('documentable_type');
            $table->unsignedBigInteger('documentable_id');
            $table->string('category', 40)->default('other')->index();
            $table->string('title')->nullable();
            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('status', 20)->default('uploaded')->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->date('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['documentable_type', 'documentable_id'], 'mbkm_documents_documentable_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mbkm_documents');
        Schema::dropIfExists('mbkm_cooperations');
        Schema::dropIfExists('mbkm_program_requirements');
        Schema::dropIfExists('mbkm_program_locations');
        Schema::dropIfExists('mbkm_programs');
        Schema::dropIfExists('mbkm_partners');
        Schema::dropIfExists('mbkm_program_types');
    }
};
