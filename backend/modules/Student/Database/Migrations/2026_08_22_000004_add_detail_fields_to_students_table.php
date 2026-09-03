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
        Schema::table('students', function (Blueprint $table) {
            if (!Schema::hasColumn('students', 'mother_name')) {
                $table->string('mother_name', 255)->nullable()->after('national_id');
            }
            if (!Schema::hasColumn('students', 'province')) {
                $table->string('province', 100)->nullable()->after('address');
            }
            if (!Schema::hasColumn('students', 'city')) {
                $table->string('city', 100)->nullable()->after('province');
            }
            if (!Schema::hasColumn('students', 'district')) {
                $table->string('district', 100)->nullable()->after('city');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['mother_name', 'province', 'city', 'district']);
        });
    }
};
