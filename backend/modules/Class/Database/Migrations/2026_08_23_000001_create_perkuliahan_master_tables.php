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
        // 1. Jenis Sesi Perkuliahan
        if (!Schema::hasTable('lecture_session_types')) {
            Schema::create('lecture_session_types', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('short_name', 50);
                $table->string('category', 50); // Kuliah, UTS, UAS, Praktikum, Seminar, etc.
                $table->string('credit_type', 50)->default('Tatap Muka'); // Tatap Muka, Praktikum, etc.
                $table->boolean('counts_attendance')->default(true); // Terhitung Presensi
                $table->string('status', 20)->default('active')->index();
                $table->timestamps();
            });
        }

        // 2. Program Kuliah
        if (!Schema::hasTable('lecture_programs')) {
            Schema::create('lecture_programs', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->string('status', 20)->default('active')->index();
                $table->timestamps();
            });
        }

        // 3. Unsur Nilai
        if (!Schema::hasTable('grading_components')) {
            Schema::create('grading_components', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('short_name', 50);
                $table->string('evaluation_method', 150); // Hasil Proyek, Aktivitas Partisipatif, Kognitif
                $table->string('component_group', 100)->nullable(); // Presensi, Tugas, Ujian
                $table->decimal('default_weight', 5, 2)->nullable();
                $table->string('status', 20)->default('active')->index();
                $table->timestamps();
            });
        }

        // 4. Kelompok Mahasiswa
        if (!Schema::hasTable('student_groups')) {
            Schema::create('student_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('study_program_id')->nullable()->constrained('study_programs')->nullOnDelete();
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->integer('student_count')->default(0);
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
        Schema::dropIfExists('student_groups');
        Schema::dropIfExists('grading_components');
        Schema::dropIfExists('lecture_programs');
        Schema::dropIfExists('lecture_session_types');
    }
};
