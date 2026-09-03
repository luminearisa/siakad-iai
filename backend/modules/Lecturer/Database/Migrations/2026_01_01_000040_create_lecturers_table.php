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
        Schema::create('lecturers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('homebase_study_program_id')->nullable()->constrained('study_programs')->nullOnDelete();
            $table->string('lecturer_number', 50)->nullable()->unique();
            $table->string('nidn', 50)->nullable()->unique();
            $table->string('nidk', 50)->nullable()->unique();
            $table->string('nip', 50)->nullable()->unique();
            $table->string('full_name');
            $table->string('gender', 10)->default('male');
            $table->string('birth_place', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('academic_degree', 100)->nullable();
            $table->string('functional_position', 100)->nullable(); // Asisten Ahli, Lektor, Lektor Kepala, Guru Besar
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->date('join_date')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lecturers');
    }
};
