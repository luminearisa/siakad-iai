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
        Schema::create('student_enrollment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('academic_classes')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('credits')->default(2);
            $table->string('status', 30)->default('enrolled')->index(); // enrolled, dropped, cancelled
            $table->timestamp('finalized_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['enrollment_id', 'class_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_enrollment_items');
    }
};
