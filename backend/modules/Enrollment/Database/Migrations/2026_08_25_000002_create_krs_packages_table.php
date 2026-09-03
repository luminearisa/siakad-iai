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
        Schema::create('krs_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('study_program_id')->constrained('study_programs')->cascadeOnDelete();
            $table->unsignedTinyInteger('semester')->default(1);
            $table->unsignedSmallInteger('total_credits')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('krs_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('krs_package_id')->constrained('krs_packages')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->unsignedSmallInteger('credits')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('krs_package_items');
        Schema::dropIfExists('krs_packages');
    }
};
