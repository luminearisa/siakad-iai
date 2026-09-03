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
        Schema::table('semesters', function (Blueprint $table) {
            $table->date('lecture_start_date')->nullable()->after('end_date');
            $table->date('lecture_end_date')->nullable()->after('lecture_start_date');
            $table->date('uts_start_date')->nullable()->after('lecture_end_date');
            $table->date('uts_end_date')->nullable()->after('uts_start_date');
            $table->date('uas_start_date')->nullable()->after('uts_end_date');
            $table->date('uas_end_date')->nullable()->after('uas_start_date');
            $table->decimal('min_attendance_uts_percentage', 5, 2)->default(50.00)->after('uas_end_date');
            $table->decimal('min_attendance_uas_percentage', 5, 2)->default(80.00)->after('min_attendance_uts_percentage');
            $table->unsignedTinyInteger('total_teaching_weeks')->default(16)->after('min_attendance_uas_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            $table->dropColumn([
                'lecture_start_date',
                'lecture_end_date',
                'uts_start_date',
                'uts_end_date',
                'uas_start_date',
                'uas_end_date',
                'min_attendance_uts_percentage',
                'min_attendance_uas_percentage',
                'total_teaching_weeks',
            ]);
        });
    }
};
