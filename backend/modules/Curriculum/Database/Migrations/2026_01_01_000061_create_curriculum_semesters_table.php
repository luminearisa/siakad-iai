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
        Schema::create('curriculum_semesters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_id')->constrained('curricula')->cascadeOnDelete();
            $table->unsignedTinyInteger('semester_number');
            $table->string('name', 50)->nullable();
            $table->unsignedSmallInteger('recommended_credits')->nullable();
            $table->timestamps();

            $table->unique(['curriculum_id', 'semester_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curriculum_semesters');
    }
};
