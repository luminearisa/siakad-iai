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
        // 1. Assessment Schemes
        Schema::create('assessment_schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_class_id')->constrained('academic_classes')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->decimal('total_weight', 5, 2)->default(0);
            $table->boolean('is_active')->default(false)->index();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['academic_class_id', 'status']);
            $table->index(['academic_class_id', 'is_active']);
        });

        // 2. Assessment Components
        Schema::create('assessment_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_class_id')->constrained('academic_classes')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->string('type', 30)->default('assignment')->index();
            $table->decimal('max_score', 5, 2)->default(100.00);
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sequence')->default(1);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['academic_class_id', 'code']);
            $table->index(['academic_class_id', 'sequence']);
        });

        // 3. Assessment Scheme Items (Pivot Component -> Scheme with Weight)
        Schema::create('assessment_scheme_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_scheme_id')->constrained('assessment_schemes')->cascadeOnDelete();
            $table->foreignId('assessment_component_id')->constrained('assessment_components')->cascadeOnDelete();
            $table->decimal('weight', 5, 2);
            $table->timestamps();

            $table->unique(['assessment_scheme_id', 'assessment_component_id'], 'scheme_component_unique');
        });

        // 4. Student Grades
        Schema::create('student_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_class_id')->constrained('academic_classes')->cascadeOnDelete();
            $table->foreignId('assessment_component_id')->constrained('assessment_components')->cascadeOnDelete();
            $table->decimal('score', 5, 2)->default(0.00);
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('graded_at')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'academic_class_id', 'assessment_component_id'], 'student_class_comp_unique');
            $table->index(['academic_class_id', 'status']);
        });

        // 5. Grade Revisions (Audit trail for final score modifications)
        Schema::create('grade_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_grade_id')->constrained('student_grades')->cascadeOnDelete();
            $table->decimal('old_score', 5, 2);
            $table->decimal('new_score', 5, 2);
            $table->text('reason');
            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
            $table->dateTime('changed_at');
            $table->timestamps();

            $table->index(['student_grade_id', 'changed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_revisions');
        Schema::dropIfExists('student_grades');
        Schema::dropIfExists('assessment_scheme_items');
        Schema::dropIfExists('assessment_components');
        Schema::dropIfExists('assessment_schemes');
    }
};
