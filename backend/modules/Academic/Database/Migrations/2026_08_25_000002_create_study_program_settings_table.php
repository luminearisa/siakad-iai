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
        if (!Schema::hasTable('study_program_settings')) {
            Schema::create('study_program_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('study_program_id')->unique()->constrained('study_programs')->cascadeOnDelete();
                $table->decimal('min_gpa_graduation', 3, 2)->default(0.00);
                $table->unsignedSmallInteger('min_final_exam_guidance')->default(0);
                $table->unsignedSmallInteger('final_exam_advisors_count')->default(2);
                $table->unsignedSmallInteger('final_exam_examiners_count')->default(2);
                $table->boolean('is_thesis_required')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_program_settings');
    }
};
