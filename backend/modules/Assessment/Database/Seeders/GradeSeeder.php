<?php

namespace Modules\Assessment\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Assessment\Enums\GradeStatus;
use Modules\Assessment\Models\AssessmentComponent;
use Modules\Assessment\Models\GradeRevision;
use Modules\Assessment\Models\StudentGrade;
use Modules\Class\Models\AcademicClass;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Enrollment\Models\StudentEnrollmentItem;
use Modules\Identity\Models\User;
use Modules\Student\Models\Student;

class GradeSeeder extends Seeder
{
    public function run(): void
    {
        $dosenUser = User::where('email', 'dosen@siakad.ac.id')->first() ?? User::first();
        $adminUser = User::where('email', 'admin@siakad.ac.id')->first() ?? User::first();
        $classes = AcademicClass::all();

        foreach ($classes as $class) {
            $components = AssessmentComponent::where('academic_class_id', $class->id)->get();
            
            // Find enrolled students
            $enrolledEnrollmentIds = StudentEnrollmentItem::where('class_id', $class->id)
                ->pluck('enrollment_id');
            $students = Student::whereIn('id', StudentEnrollment::whereIn('id', $enrolledEnrollmentIds)->pluck('student_id'))->get();

            if ($students->isEmpty()) {
                $students = Student::take(3)->get();
            }

            foreach ($students as $index => $student) {
                // Vary scores by student index for realism
                $baseScore = match ($index) {
                    0 => 88.0,
                    1 => 84.0,
                    2 => 79.0,
                    default => 82.0,
                };

                foreach ($components as $comp) {
                    $score = match ($comp->code) {
                        'TUGAS' => min(100.0, $baseScore + 4.0),
                        'KUIS' => min(100.0, $baseScore + 2.0),
                        'UTS' => $baseScore,
                        'UAS' => min(100.0, $baseScore + 3.0),
                        'PARTICIPATION' => 95.00,
                        default => $baseScore,
                    };

                    $status = $class->id === 1 ? GradeStatus::DRAFT : GradeStatus::FINAL;

                    $grade = StudentGrade::firstOrCreate(
                        [
                            'student_id' => $student->id,
                            'academic_class_id' => $class->id,
                            'assessment_component_id' => $comp->id,
                        ],
                        [
                            'score' => $score,
                            'graded_by' => $dosenUser?->id,
                            'graded_at' => now()->subDays(3),
                            'status' => $status,
                            'notes' => 'Nilai resmi.',
                        ]
                    );

                    // Example Grade Revision on UAS for student 1
                    if ($index === 0 && $comp->code === 'UAS' && $grade->revisions()->count() === 0) {
                        GradeRevision::create([
                            'student_grade_id' => $grade->id,
                            'old_score' => 86.00,
                            'new_score' => $score,
                            'reason' => 'Koreksi verifikasi butir soal esai nomor 4.',
                            'changed_by' => $adminUser?->id ?: 1,
                            'changed_at' => now()->subDay(),
                        ]);
                    }
                }
            }
        }
    }
}
