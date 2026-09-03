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
        Schema::create('curricula', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_program_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('version', 20)->default('1.0');
            $table->text('description')->nullable();
            $table->year('start_year')->nullable();
            $table->year('end_year')->nullable();
            $table->string('status', 20)->default('draft')->index(); // draft, active, inactive, archived
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curricula');
    }
};
