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
        if (Schema::hasTable('lecturers') && !Schema::hasColumn('lecturers', 'academic_advising_quota')) {
            Schema::table('lecturers', function (Blueprint $table) {
                $table->unsignedSmallInteger('academic_advising_quota')->default(0)->after('functional_position');
                $table->unsignedSmallInteger('thesis_supervisor_quota')->default(0)->after('academic_advising_quota');
                $table->unsignedSmallInteger('thesis_examiner_quota')->default(0)->after('thesis_supervisor_quota');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('lecturers') && Schema::hasColumn('lecturers', 'academic_advising_quota')) {
            Schema::table('lecturers', function (Blueprint $table) {
                $table->dropColumn(['academic_advising_quota', 'thesis_supervisor_quota', 'thesis_examiner_quota']);
            });
        }
    }
};
