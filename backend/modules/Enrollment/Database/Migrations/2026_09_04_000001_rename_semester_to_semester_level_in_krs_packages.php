<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rename 'semester' (semester tingkat 1–14) menjadi 'semester_level'
     * untuk membedakannya dari 'semester_id' yang merujuk ke tabel semesters.
     */
    public function up(): void
    {
        Schema::table('krs_packages', function (Blueprint $table) {
            $table->renameColumn('semester', 'semester_level');
        });
    }

    public function down(): void
    {
        Schema::table('krs_packages', function (Blueprint $table) {
            $table->renameColumn('semester_level', 'semester');
        });
    }
};
