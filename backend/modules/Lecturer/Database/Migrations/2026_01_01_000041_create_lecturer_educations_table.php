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
        Schema::create('lecturer_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lecturer_id')->constrained()->cascadeOnDelete();
            $table->string('degree', 20); // S1, S2, S3, Sp-1, Sp-2
            $table->string('institution_name');
            $table->string('major', 100)->nullable();
            $table->year('graduation_year')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lecturer_educations');
    }
};
