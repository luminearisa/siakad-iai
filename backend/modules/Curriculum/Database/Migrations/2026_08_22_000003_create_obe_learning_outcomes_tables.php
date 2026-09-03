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
        // 1. Profil Lulusan (PL)
        if (!Schema::hasTable('graduate_profiles')) {
            Schema::create('graduate_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('study_program_id')->nullable()->constrained('study_programs')->nullOnDelete();
                $table->string('code', 50);
                $table->string('name', 255);
                $table->string('profession', 255)->nullable();
                $table->text('description')->nullable();
                $table->string('status', 20)->default('active')->index();
                $table->timestamps();
            });
        }

        // 2. Capaian Pembelajaran Lulusan (CPL)
        if (!Schema::hasTable('learning_outcomes')) {
            Schema::create('learning_outcomes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('study_program_id')->nullable()->constrained('study_programs')->nullOnDelete();
                $table->string('code', 50);
                $table->text('name');
                $table->string('category', 50)->default('sikap')->index(); // sikap, pengetahuan, keterampilan_umum, keterampilan_khusus
                $table->text('description')->nullable();
                $table->string('status', 20)->default('active')->index();
                $table->timestamps();
            });
        }

        // 3. Capaian Pembelajaran Mata Kuliah (CPMK)
        if (!Schema::hasTable('course_learning_outcomes')) {
            Schema::create('course_learning_outcomes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('study_program_id')->nullable()->constrained('study_programs')->nullOnDelete();
                $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
                $table->foreignId('learning_outcome_id')->nullable()->constrained('learning_outcomes')->nullOnDelete();
                $table->string('code', 50);
                $table->text('name');
                $table->text('description')->nullable();
                $table->string('status', 20)->default('active')->index();
                $table->timestamps();
            });
        }

        // 4. Sub-CPMK
        if (!Schema::hasTable('sub_course_learning_outcomes')) {
            Schema::create('sub_course_learning_outcomes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_learning_outcome_id')->nullable()->constrained('course_learning_outcomes')->cascadeOnDelete();
                $table->string('code', 50);
                $table->text('name');
                $table->text('description')->nullable();
                $table->string('status', 20)->default('active')->index();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_course_learning_outcomes');
        Schema::dropIfExists('course_learning_outcomes');
        Schema::dropIfExists('learning_outcomes');
        Schema::dropIfExists('graduate_profiles');
    }
};
