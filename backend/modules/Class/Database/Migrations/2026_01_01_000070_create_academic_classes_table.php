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
        Schema::create('academic_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_program_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50)->index();
            $table->string('name')->nullable();
            $table->string('section', 20)->default('A');
            $table->unsignedSmallInteger('capacity')->default(30);
            $table->unsignedSmallInteger('enrolled_count')->default(0);
            $table->string('status', 20)->default('draft')->index(); // draft, open, closed, cancelled, completed
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['semester_id', 'course_id', 'section']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_classes');
    }
};
