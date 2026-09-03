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
        // 1. Jenis Mata Kuliah (Tatap Muka, Praktikum, Hybrid, Tugas Akhir, etc.)
        if (!Schema::hasTable('course_types')) {
            Schema::create('course_types', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->unique();
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->string('status', 20)->default('active')->index();
                $table->timestamps();
            });
        }

        // 2. Kelompok Mata Kuliah (MPK, MKK, MKB, MPB, MBB, MKU, MKDK)
        if (!Schema::hasTable('course_groups')) {
            Schema::create('course_groups', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->unique();
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->string('status', 20)->default('active')->index();
                $table->timestamps();
            });
        }

        // 3. Update Courses Table with foreign keys & detailed SKS breakdown
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('study_program_id')->nullable()->after('id')->constrained('study_programs')->nullOnDelete();
            $table->foreignId('course_type_id')->nullable()->after('study_program_id')->constrained('course_types')->nullOnDelete();
            $table->foreignId('course_group_id')->nullable()->after('course_type_id')->constrained('course_groups')->nullOnDelete();
            $table->unsignedSmallInteger('field_practical_credits')->default(0)->after('practical_credits');
            $table->unsignedSmallInteger('simulation_credits')->default(0)->after('field_practical_credits');
            $table->unsignedSmallInteger('seminar_credits')->default(0)->after('simulation_credits');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['study_program_id']);
            $table->dropForeign(['course_type_id']);
            $table->dropForeign(['course_group_id']);
            $table->dropColumn([
                'study_program_id',
                'course_type_id',
                'course_group_id',
                'field_practical_credits',
                'simulation_credits',
                'seminar_credits',
            ]);
        });

        Schema::dropIfExists('course_groups');
        Schema::dropIfExists('course_types');
    }
};
