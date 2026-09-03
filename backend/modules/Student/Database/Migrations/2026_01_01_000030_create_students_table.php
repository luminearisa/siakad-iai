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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('study_program_id')->constrained()->cascadeOnDelete();
            $table->string('student_number', 50)->unique();
            $table->string('national_student_number', 50)->nullable()->index(); // NISN
            $table->string('national_id', 50)->nullable()->index(); // NIK
            $table->string('full_name');
            $table->string('nickname', 50)->nullable();
            $table->string('gender', 10)->default('male');
            $table->string('birth_place', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('religion', 30)->nullable();
            $table->string('marital_status', 30)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->year('admission_year')->nullable()->index();
            $table->date('entry_date')->nullable();
            $table->date('graduation_date')->nullable();
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
        Schema::dropIfExists('students');
    }
};
