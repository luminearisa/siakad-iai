<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            $table->date('krs_start_date')->nullable()->after('end_date');
            $table->date('krs_end_date')->nullable()->after('krs_start_date');
            $table->date('kprs_start_date')->nullable()->after('krs_end_date');
            $table->date('kprs_end_date')->nullable()->after('kprs_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('semesters', function (Blueprint $table) {
            $table->dropColumn([
                'krs_start_date',
                'krs_end_date',
                'kprs_start_date',
                'kprs_end_date',
            ]);
        });
    }
};
