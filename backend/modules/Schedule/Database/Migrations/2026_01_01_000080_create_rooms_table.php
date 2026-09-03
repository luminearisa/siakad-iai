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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('building', 100)->nullable();
            $table->smallInteger('floor')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('room_type', 50)->nullable(); // classroom, laboratory, auditorium, seminar
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
        Schema::dropIfExists('rooms');
    }
};
