<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('theses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('study_program_id')->nullable()->constrained('study_programs')->nullOnDelete();
            $table->foreignId('start_semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->foreignId('completion_semester_id')->nullable()->constrained('semesters')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('submission_date')->nullable();
            $table->date('completion_date')->nullable();
            $table->string('status', 30)->default('active'); // active, completed, inactive, pending_approval
            $table->text('title_id');
            $table->text('title_en')->nullable();
            $table->text('topic_id')->nullable();
            $table->text('topic_en')->nullable();
            $table->string('proposal_file_path')->nullable();
            $table->string('final_file_path')->nullable();
            $table->date('sk_date')->nullable();
            $table->string('sk_number')->nullable();
            $table->decimal('final_grade', 5, 2)->nullable();
            $table->string('final_grade_letter', 5)->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });

        Schema::create('thesis_supervisors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thesis_id')->constrained('theses')->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained('lecturers')->cascadeOnDelete();
            $table->unsignedTinyInteger('order')->default(1); // 1 = Pembimbing 1, 2 = Pembimbing 2
            $table->string('role', 50)->default('primary');
            $table->string('status', 30)->default('assigned');
            $table->timestamps();

            $table->unique(['thesis_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thesis_supervisors');
        Schema::dropIfExists('theses');
    }
};
