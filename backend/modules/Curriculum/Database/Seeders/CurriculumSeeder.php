<?php

namespace Modules\Curriculum\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Academic\Models\StudyProgram;
use Modules\Course\Models\Course;
use Modules\Curriculum\Enums\CurriculumStatus;
use Modules\Curriculum\Models\Curriculum;

class CurriculumSeeder extends Seeder
{
    public function run(): void
    {
        $pai = StudyProgram::where('code', 'PAI')->first();
        if (!$pai) {
            return;
        }

        // 1. Create Curriculum Kurikulum Merdeka PAI 2026
        $curriculum = Curriculum::firstOrCreate(
            ['code' => 'KUR-PAI-2026'],
            [
                'study_program_id' => $pai->id,
                'name' => 'Kurikulum OBE PAI 2026',
                'version' => '2026.1',
                'description' => 'Kurikulum Berbasis Outcome-Based Education Program Studi Pendidikan Agama Islam',
                'start_year' => 2026,
                'end_year' => 2030,
                'status' => CurriculumStatus::ACTIVE,
                'effective_date' => '2026-09-01',
            ]
        );

        // 2. Create Semesters if not exists
        for ($i = 1; $i <= 8; $i++) {
            $curriculum->semesters()->firstOrCreate(
                ['semester_number' => $i],
                [
                    'name' => "Semester {$i}",
                    'recommended_credits' => 20,
                ]
            );
        }

        // 3. Assign Courses to Semesters
        $sem1 = $curriculum->semesters()->where('semester_number', 1)->first();
        $sem2 = $curriculum->semesters()->where('semester_number', 2)->first();
        $sem3 = $curriculum->semesters()->where('semester_number', 3)->first();
        $sem5 = $curriculum->semesters()->where('semester_number', 5)->first();

        $mku101 = Course::where('code', 'MKU-101')->first();
        $mku102 = Course::where('code', 'MKU-102')->first();
        $mku103 = Course::where('code', 'MKU-103')->first();
        $mku104 = Course::where('code', 'MKU-104')->first();
        $mku105 = Course::where('code', 'MKU-105')->first();
        $mku106 = Course::where('code', 'MKU-106')->first();
        $pai201 = Course::where('code', 'PAI-201')->first();
        $pai202 = Course::where('code', 'PAI-202')->first();
        $pai203 = Course::where('code', 'PAI-203')->first();
        $pai301 = Course::where('code', 'PAI-301')->first();
        $pai302 = Course::where('code', 'PAI-302')->first();

        // Semester 1 subjects
        if ($sem1) {
            if ($mku101) $sem1->subjects()->firstOrCreate(['course_id' => $mku101->id], ['is_mandatory' => true]);
            if ($mku102) $sem1->subjects()->firstOrCreate(['course_id' => $mku102->id], ['is_mandatory' => true]);
            if ($mku103) $sem1->subjects()->firstOrCreate(['course_id' => $mku103->id], ['is_mandatory' => true]);
            if ($mku105) $sem1->subjects()->firstOrCreate(['course_id' => $mku105->id], ['is_mandatory' => true]);
            if ($pai201) $sem1->subjects()->firstOrCreate(['course_id' => $pai201->id], ['is_mandatory' => true]);
        }

        // Semester 2 subjects
        if ($sem2) {
            if ($mku104) $sem2->subjects()->firstOrCreate(['course_id' => $mku104->id], ['is_mandatory' => true]);
            if ($mku106) $sem2->subjects()->firstOrCreate(['course_id' => $mku106->id], ['is_mandatory' => true]);
            if ($pai202) $sem2->subjects()->firstOrCreate(['course_id' => $pai202->id], ['is_mandatory' => true]);
        }

        // Semester 3 subjects
        if ($sem3) {
            if ($pai203) $sem3->subjects()->firstOrCreate(['course_id' => $pai203->id], ['is_mandatory' => true]);
        }

        // Semester 5 subjects
        if ($sem5) {
            if ($pai301) $sem5->subjects()->firstOrCreate(['course_id' => $pai301->id], ['is_mandatory' => true]);
            if ($pai302) $sem5->subjects()->firstOrCreate(['course_id' => $pai302->id], ['is_mandatory' => true]);
        }
    }
}
