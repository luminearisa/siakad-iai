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
        Schema::create('course_questionnaire_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('order_number')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('course_questionnaire_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('course_questionnaire_topics')->cascadeOnDelete();
            $table->text('question');
            $table->string('question_type')->default('likert'); // likert, multiple_choice, essay
            $table->integer('scale_min')->default(1);
            $table->integer('scale_max')->default(5);
            $table->string('scale_min_label')->nullable()->default('Sangat Kurang');
            $table->string('scale_max_label')->nullable()->default('Sangat Baik');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(true);
            $table->integer('order_number')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_questionnaire_questions');
        Schema::dropIfExists('course_questionnaire_topics');
    }
};
