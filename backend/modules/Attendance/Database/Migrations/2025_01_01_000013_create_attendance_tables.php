<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teaching_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_class_id')->constrained('academic_classes')->cascadeOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('class_schedules')->nullOnDelete();
            $table->foreignId('lecturer_id')->constrained('lecturers')->cascadeOnDelete();
            $table->unsignedTinyInteger('meeting_number'); // 1 s.d. 16
            $table->date('session_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('topic', 255)->nullable(); // Topik bahasan
            $table->text('notes')->nullable(); // Berita Acara Perkuliahan / Catatan
            $table->string('teaching_method')->default('offline'); // offline, online, hybrid
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('status')->default('scheduled'); // scheduled, open, closed, cancelled
            $table->string('check_in_code', 10)->nullable(); // Token presensi mandiri (6 digit)
            $table->dateTime('check_in_expires_at')->nullable();
            $table->timestamps();

            $table->unique(['academic_class_id', 'meeting_number'], 'class_meeting_unique');
            $table->index(['academic_class_id', 'session_date']);
            $table->index('lecturer_id');
            $table->index('status');
        });

        Schema::create('student_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_session_id')->constrained('teaching_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_class_id')->constrained('academic_classes')->cascadeOnDelete();
            $table->string('status')->default('present'); // present, permit, sick, absent
            $table->string('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('recorded_at')->nullable();
            $table->timestamps();

            $table->unique(['teaching_session_id', 'student_id'], 'session_student_unique');
            $table->index(['academic_class_id', 'student_id']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_attendances');
        Schema::dropIfExists('teaching_sessions');
    }
};
