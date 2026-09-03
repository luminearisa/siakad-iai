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
        // 1. Campuses / Kampus
        if (!Schema::hasTable('campuses')) {
            Schema::create('campuses', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name', 150);
                $table->text('address')->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('status', 20)->default('active')->index();
                $table->timestamps();
            });
        }

        // 2. Buildings / Gedung
        if (!Schema::hasTable('buildings')) {
            Schema::create('buildings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campus_id')->nullable()->constrained('campuses')->nullOnDelete();
                $table->string('code', 50)->unique();
                $table->string('name', 150);
                $table->text('address')->nullable();
                $table->integer('total_floors')->nullable()->default(1);
                $table->integer('total_rooms')->nullable()->default(0);
                $table->string('status', 20)->default('active')->index();
                $table->timestamps();
            });
        }

        // 3. Alter Rooms table to add building_id and location if not present
        if (Schema::hasTable('rooms')) {
            Schema::table('rooms', function (Blueprint $table) {
                if (!Schema::hasColumn('rooms', 'building_id')) {
                    $table->foreignId('building_id')->nullable()->after('id')->constrained('buildings')->nullOnDelete();
                }
                if (!Schema::hasColumn('rooms', 'location')) {
                    $table->string('location', 100)->nullable()->after('capacity');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('rooms')) {
            Schema::table('rooms', function (Blueprint $table) {
                if (Schema::hasColumn('rooms', 'building_id')) {
                    $table->dropConstrainedForeignId('building_id');
                }
                if (Schema::hasColumn('rooms', 'location')) {
                    $table->dropColumn('location');
                }
            });
        }

        Schema::dropIfExists('buildings');
        Schema::dropIfExists('campuses');
    }
};
