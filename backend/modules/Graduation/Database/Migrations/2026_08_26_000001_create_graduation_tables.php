<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yudisium_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->string('name'); // e.g. "Yudisium 75", "Yudisium Saat ini"
            $table->date('registration_start_date');
            $table->date('registration_end_date');
            $table->date('yudisium_date');
            $table->integer('quota')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('yudisium_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_program_id')->nullable()->constrained('study_programs')->nullOnDelete();
            $table->string('name'); // e.g. "Bebas Pustaka", "Naskah Publikasi Ilmiah", "Sertifikat TOEFL"
            $table->string('code')->nullable();
            $table->boolean('is_document')->default(true);
            $table->boolean('is_mandatory')->default(true);
            $table->integer('min_credits')->default(144);
            $table->decimal('min_gpa', 3, 2)->default(2.00);
            $table->timestamps();
        });

        Schema::create('yudisium_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yudisium_period_id')->constrained('yudisium_periods')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('application_date')->nullable();
            $table->string('status')->default('submitted'); // submitted, under_review, needs_revision, re_review, ready, passed, rejected
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->integer('total_credits')->default(0);
            $table->decimal('gpa', 3, 2)->default(0.00);
            $table->integer('study_duration_days')->default(0);
            $table->foreignId('thesis_id')->nullable()->constrained('theses')->nullOnDelete();
            $table->string('sk_number')->nullable();
            $table->date('sk_date')->nullable();
            $table->boolean('is_certificate_taken')->default(false);
            $table->dateTime('certificate_taken_at')->nullable();
            $table->string('certificate_taken_by')->nullable();
            $table->timestamps();
        });

        Schema::create('yudisium_document_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yudisium_participant_id')->constrained('yudisium_participants')->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained('yudisium_requirements')->cascadeOnDelete();
            $table->string('file_path')->nullable();
            $table->string('status')->default('pending'); // pending, valid, invalid
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yudisium_document_submissions');
        Schema::dropIfExists('yudisium_participants');
        Schema::dropIfExists('yudisium_requirements');
        Schema::dropIfExists('yudisium_periods');
    }
};
