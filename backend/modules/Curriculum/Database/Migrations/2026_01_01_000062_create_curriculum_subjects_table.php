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
        Schema::create('curriculum_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_mandatory')->default(true);
            $table->unsignedSmallInteger('credits_override')->nullable();
            $table->string('minimum_grade', 5)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['curriculum_semester_id', 'course_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curriculum_subjects');
    }
};
