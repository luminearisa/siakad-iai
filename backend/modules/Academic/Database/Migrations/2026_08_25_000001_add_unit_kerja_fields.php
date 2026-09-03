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
        if (Schema::hasTable('faculties') && !Schema::hasColumn('faculties', 'name_en')) {
            Schema::table('faculties', function (Blueprint $table) {
                $table->string('name_en')->nullable()->after('name');
            });
        }

        if (Schema::hasTable('study_programs') && !Schema::hasColumn('study_programs', 'short_name')) {
            Schema::table('study_programs', function (Blueprint $table) {
                $table->string('short_name', 50)->nullable()->after('code');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('faculties') && Schema::hasColumn('faculties', 'name_en')) {
            Schema::table('faculties', function (Blueprint $table) {
                $table->dropColumn('name_en');
            });
        }

        if (Schema::hasTable('study_programs') && Schema::hasColumn('study_programs', 'short_name')) {
            Schema::table('study_programs', function (Blueprint $table) {
                $table->dropColumn('short_name');
            });
        }
    }
};
