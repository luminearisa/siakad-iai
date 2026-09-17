<?php

namespace Modules\Course\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Course\Models\Course;
use Modules\Course\Models\CourseSurveyQuestion;
use Modules\Course\Models\CourseSurveyTemplate;
use Modules\Course\Models\CourseSurveyTopic;

class CourseSurveyTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Template Standar EDOM (Perkuliahan Teori)
        $theoryTemplate = CourseSurveyTemplate::firstOrCreate(
            ['name' => 'Survey Evaluasi Perkuliahan Teori (EDOM)'],
            [
                'description' => 'Instrumen evaluasi dosen oleh mahasiswa untuk mata kuliah perkuliahan teori, mencakup kompetensi pedagogik, kedisiplinan, dan interaksi.',
                'is_active' => true,
            ]
        );

        // Judul 1: Pedagogik & Materi
        $topic1 = CourseSurveyTopic::firstOrCreate(
            [
                'survey_template_id' => $theoryTemplate->id,
                'title' => 'Kompetensi Pedagogik & Penguasaan Materi',
            ],
            [
                'description' => 'Evaluasi kejelasan penjelasan konsep dan penguasaan materi perkuliahan oleh dosen.',
                'order_number' => 1,
            ]
        );

        CourseSurveyQuestion::firstOrCreate(
            ['topic_id' => $topic1->id, 'question' => 'Dosen menyampaikan silabus, rencana perkuliahan (RPS), dan kriteria penilaian di awal semester.'],
            [
                'question_type' => 'yes_no',
                'is_required' => true,
                'order_number' => 1,
            ]
        );

        CourseSurveyQuestion::firstOrCreate(
            ['topic_id' => $topic1->id, 'question' => 'Dosen menguasai materi perkuliahan dengan baik dan mampu menjelaskannya secara sistematis.'],
            [
                'question_type' => 'scale',
                'scale_min' => 1,
                'scale_max' => 5,
                'scale_min_label' => 'Sangat Kurang',
                'scale_max_label' => 'Sangat Baik',
                'is_required' => true,
                'order_number' => 2,
            ]
        );

        CourseSurveyQuestion::firstOrCreate(
            ['topic_id' => $topic1->id, 'question' => 'Materi dan bahan ajar yang diberikan up-to-date dan relevan dengan capaian pembelajaran.'],
            [
                'question_type' => 'scale',
                'scale_min' => 1,
                'scale_max' => 5,
                'scale_min_label' => 'Sangat Kurang',
                'scale_max_label' => 'Sangat Baik',
                'is_required' => true,
                'order_number' => 3,
            ]
        );

        // Judul 2: Kedisiplinan & Sikap
        $topic2 = CourseSurveyTopic::firstOrCreate(
            [
                'survey_template_id' => $theoryTemplate->id,
                'title' => 'Kedisiplinan & Manajemen Waktu',
            ],
            [
                'description' => 'Evaluasi komitmen waktu dan kehadiran dosen dalam mengajar.',
                'order_number' => 2,
            ]
        );

        CourseSurveyQuestion::firstOrCreate(
            ['topic_id' => $topic2->id, 'question' => 'Dosen selalu hadir tepat waktu sesuai jadwal perkuliahan yang ditentukan.'],
            [
                'question_type' => 'scale',
                'scale_min' => 1,
                'scale_max' => 5,
                'scale_min_label' => 'Tidak Pernah',
                'scale_max_label' => 'Selalu Tepat Waktu',
                'is_required' => true,
                'order_number' => 1,
            ]
        );

        CourseSurveyQuestion::firstOrCreate(
            ['topic_id' => $topic2->id, 'question' => 'Apakah dosen memberikan pemberitahuan terlebih dahulu jika berhalangan hadir atau mengubah jadwal?'],
            [
                'question_type' => 'yes_no',
                'is_required' => true,
                'order_number' => 2,
            ]
        );

        // 2. Template Praktikum / Laboratorium
        $labTemplate = CourseSurveyTemplate::firstOrCreate(
            ['name' => 'Survey Perkuliahan Praktikum & Laboratorium'],
            [
                'description' => 'Instrumen evaluasi untuk mata kuliah yang menyertakan sesi praktikum mandiri maupun kelompok di laboratorium.',
                'is_active' => true,
            ]
        );

        $labTopic = CourseSurveyTopic::firstOrCreate(
            [
                'survey_template_id' => $labTemplate->id,
                'title' => 'Bimbingan & Ketersediaan Fasilitas Praktikum',
            ],
            [
                'description' => 'Evaluasi kelengkapan modul praktikum, pendampingan asisten/dosen, serta kesiapan fasilitas.',
                'order_number' => 1,
            ]
        );

        CourseSurveyQuestion::firstOrCreate(
            ['topic_id' => $labTopic->id, 'question' => 'Apakah modul dan panduan praktikum dibagikan sebelum kegiatan dimulai?'],
            [
                'question_type' => 'yes_no',
                'is_required' => true,
                'order_number' => 1,
            ]
        );

        CourseSurveyQuestion::firstOrCreate(
            ['topic_id' => $labTopic->id, 'question' => 'Dosen atau instruktur membimbing dan mendampingi mahasiswa secara intensif selama praktikum berlangsung.'],
            [
                'question_type' => 'scale',
                'scale_min' => 1,
                'scale_max' => 5,
                'scale_min_label' => 'Sangat Kurang',
                'scale_max_label' => 'Sangat Baik',
                'is_required' => true,
                'order_number' => 2,
            ]
        );

        // Assign template teori to some existing courses if available
        $courses = Course::take(5)->get();
        if ($courses->isNotEmpty()) {
            $theoryTemplate->courses()->syncWithoutDetaching($courses->pluck('id')->toArray());
        }
    }
}
