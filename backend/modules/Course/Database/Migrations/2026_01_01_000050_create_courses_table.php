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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('short_name', 50)->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('credits')->default(2);
            $table->unsignedSmallInteger('theory_credits')->default(2);
            $table->unsignedSmallInteger('practical_credits')->default(0);
            $table->string('type', 20)->default('theory')->index(); // theory, practical, mixed
            $table->string('category', 50)->nullable()->index(); // general, institutional, faculty, program
            $table->string('status', 20)->default('active')->index();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
