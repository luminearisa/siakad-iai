<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow audit trail for KRS.
 *
 * Batal-tambah (dropping a class from an already approved/locked KRS) must leave
 * a readable trail instead of silently deleting the row, so every status
 * transition of an enrollment — and of its items — is recorded here. Complements
 * the generic AuditService, which only captures raw attribute diffs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('enrollment_status_histories')) {
            return;
        }

        Schema::create('enrollment_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
            $table->foreignId('enrollment_item_id')->nullable()->constrained('student_enrollment_items')->nullOnDelete();
            $table->string('action', 60);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['enrollment_id', 'id'], 'enrollment_status_histories_enrollment_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_status_histories');
    }
};
