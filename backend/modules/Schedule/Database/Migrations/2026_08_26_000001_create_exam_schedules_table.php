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
        Schema::create('exam_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_class_id')->constrained('academic_classes')->cascadeOnDelete();
            $table->string('exam_type', 20)->default('uts'); // uts, uas
            $table->date('exam_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('proctor_lecturer_id')->nullable()->constrained('lecturers')->nullOnDelete();
            $table->boolean('is_announced')->default(false);
            $table->dateTime('announced_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['academic_class_id', 'exam_type'], 'class_exam_type_unique');
            $table->index(['exam_type', 'exam_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_schedules');
    }
};
