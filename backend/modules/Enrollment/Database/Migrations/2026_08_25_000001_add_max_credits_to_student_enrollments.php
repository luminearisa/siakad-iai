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
        if (Schema::hasTable('student_enrollments') && !Schema::hasColumn('student_enrollments', 'max_credits')) {
            Schema::table('student_enrollments', function (Blueprint $table) {
                $table->unsignedSmallInteger('max_credits')->default(24)->after('total_credits');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('student_enrollments') && Schema::hasColumn('student_enrollments', 'max_credits')) {
            Schema::table('student_enrollments', function (Blueprint $table) {
                $table->dropColumn('max_credits');
            });
        }
    }
};
