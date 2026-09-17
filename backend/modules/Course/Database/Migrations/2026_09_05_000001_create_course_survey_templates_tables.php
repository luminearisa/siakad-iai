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
        // 1. Master Template Survey
        Schema::create('course_survey_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Judul / Topik dalam Template Survey
        Schema::create('course_survey_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_template_id')->constrained('course_survey_templates')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('order_number')->default(0);
            $table->timestamps();
        });

        // 3. Pertanyaan dalam Judul
        Schema::create('course_survey_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('course_survey_topics')->cascadeOnDelete();
            $table->text('question');
            $table->string('question_type')->default('scale'); // 'yes_no' or 'scale'
            $table->integer('scale_min')->default(1);
            $table->integer('scale_max')->default(5);
            $table->string('scale_min_label')->nullable()->default('Sangat Kurang');
            $table->string('scale_max_label')->nullable()->default('Sangat Baik');
            $table->boolean('is_required')->default(true);
            $table->integer('order_number')->default(0);
            $table->timestamps();
        });

        // 4. Assignment Pivot: Mata Kuliah <-> Template Survey
        Schema::create('course_survey_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('survey_template_id')->constrained('course_survey_templates')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['course_id', 'survey_template_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_survey_assignments');
        Schema::dropIfExists('course_survey_questions');
        Schema::dropIfExists('course_survey_topics');
        Schema::dropIfExists('course_survey_templates');
    }
};
