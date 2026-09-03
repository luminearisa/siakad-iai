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
        // 1. Tahun Kurikulum
        if (!Schema::hasTable('curriculum_years')) {
            Schema::create('curriculum_years', function (Blueprint $table) {
                $table->id();
                $table->year('year')->unique();
                $table->string('name', 100);
                $table->date('start_date');
                $table->date('end_date');
                $table->string('status', 20)->default('active')->index();
                $table->timestamps();
            });
        }

        // 2. Batas SKS
        if (!Schema::hasTable('credit_limits')) {
            Schema::create('credit_limits', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->json('rules')->nullable(); // [{"min_gpa": 3.0, "max_gpa": 4.0, "max_sks": 24}, ...]
                $table->string('status', 20)->default('active')->index();
                $table->timestamps();
            });
        }

        // 3. Skala Nilai
        if (!Schema::hasTable('grade_scales')) {
            Schema::create('grade_scales', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->string('status', 20)->default('active')->index();
                $table->timestamps();
            });
        }

        // 4. Detail Item Skala Nilai (A, B+, B, C, D, E)
        if (!Schema::hasTable('grade_scale_items')) {
            Schema::create('grade_scale_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('grade_scale_id')->constrained('grade_scales')->cascadeOnDelete();
                $table->string('grade_letter', 10);
                $table->decimal('grade_point', 4, 2);
                $table->decimal('min_score', 5, 2);
                $table->decimal('max_score', 5, 2);
                $table->boolean('is_whitewash')->default(false); // Nilai pemutihan
                $table->timestamps();
            });
        }

        // 5. Update Curricula with foreign keys
        Schema::table('curricula', function (Blueprint $table) {
            if (!Schema::hasColumn('curricula', 'curriculum_year_id')) {
                $table->foreignId('curriculum_year_id')->nullable()->after('study_program_id')->constrained('curriculum_years')->nullOnDelete();
            }
            if (!Schema::hasColumn('curricula', 'credit_limit_id')) {
                $table->foreignId('credit_limit_id')->nullable()->after('curriculum_year_id')->constrained('credit_limits')->nullOnDelete();
            }
            if (!Schema::hasColumn('curricula', 'grade_scale_id')) {
                $table->foreignId('grade_scale_id')->nullable()->after('credit_limit_id')->constrained('grade_scales')->nullOnDelete();
            }
        });

        // 6. Update Curriculum Subjects with type, is_package, min_grade
        Schema::table('curriculum_subjects', function (Blueprint $table) {
            if (!Schema::hasColumn('curriculum_subjects', 'subject_type')) {
                $table->string('subject_type', 30)->default('wajib')->after('course_id'); // wajib, pilihan
            }
            if (!Schema::hasColumn('curriculum_subjects', 'is_package')) {
                $table->boolean('is_package')->default(true)->after('subject_type'); // Paket / Non-Paket
            }
            if (!Schema::hasColumn('curriculum_subjects', 'minimum_grade')) {
                $table->string('minimum_grade', 10)->default('D')->after('is_package'); // D, C, C+, B, B+, A
            }
            if (!Schema::hasColumn('curriculum_subjects', 'prerequisites_text')) {
                $table->string('prerequisites_text', 255)->nullable()->after('minimum_grade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('curriculum_subjects', function (Blueprint $table) {
            $table->dropColumn(['subject_type', 'is_package', 'minimum_grade', 'prerequisites_text']);
        });

        Schema::table('curricula', function (Blueprint $table) {
            $table->dropForeign(['curriculum_year_id']);
            $table->dropForeign(['credit_limit_id']);
            $table->dropForeign(['grade_scale_id']);
            $table->dropColumn(['curriculum_year_id', 'credit_limit_id', 'grade_scale_id']);
        });

        Schema::dropIfExists('grade_scale_items');
        Schema::dropIfExists('grade_scales');
        Schema::dropIfExists('credit_limits');
        Schema::dropIfExists('curriculum_years');
    }
};
