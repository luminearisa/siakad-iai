<?php

namespace Modules\Course\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Course\Enums\CourseStatus;
use Modules\Course\Enums\CourseType;
use Modules\Course\Models\Course;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $courses = [
            // 1. Mata Kuliah Wajib Nasional & Institut (MKU)
            [
                'code' => 'MKU-101',
                'name' => 'Pancasila dan Kewarganegaraan',
                'short_name' => 'Pancasila',
                'description' => 'Mata kuliah umum penguatan ideologi kebangsaan dan wawasan konstitusi nasional.',
                'credits' => 2,
                'theory_credits' => 2,
                'practical_credits' => 0,
                'type' => CourseType::THEORY,
                'category' => 'general',
                'status' => CourseStatus::ACTIVE,
            ],
            [
                'code' => 'MKU-102',
                'name' => 'Bahasa Indonesia & Tata Tulis Ilmiah',
                'short_name' => 'B. Indonesia',
                'description' => 'Mata kuliah pengembangan penulisan artikel ilmiah, proposal, dan skripsi.',
                'credits' => 2,
                'theory_credits' => 2,
                'practical_credits' => 0,
                'type' => CourseType::THEORY,
                'category' => 'general',
                'status' => CourseStatus::ACTIVE,
            ],
            [
                'code' => 'MKU-103',
                'name' => 'Bahasa Arab I (Qawaid & Nahwu Dasar)',
                'short_name' => 'B. Arab 1',
                'description' => 'Dasar-dasar tata bahasa Arab fushah untuk literasi teks keislaman.',
                'credits' => 2,
                'theory_credits' => 2,
                'practical_credits' => 0,
                'type' => CourseType::THEORY,
                'category' => 'institutional',
                'status' => CourseStatus::ACTIVE,
            ],
            [
                'code' => 'MKU-104',
                'name' => 'Bahasa Arab II (Muhadatsah & Qira\'ah)',
                'short_name' => 'B. Arab 2',
                'description' => 'Lanjutan tata bahasa Arab, percakapan tematik, dan telaah kutubut turats.',
                'credits' => 2,
                'theory_credits' => 2,
                'practical_credits' => 0,
                'type' => CourseType::THEORY,
                'category' => 'institutional',
                'status' => CourseStatus::ACTIVE,
            ],
            [
                'code' => 'MKU-105',
                'name' => 'Studi Al-Qur\'an & Ulumul Qur\'an',
                'short_name' => 'Ulumul Qur\'an',
                'description' => 'Pengantar kajian nuzulul qur\'an, makki-madani, asbabun nuzul, dan kaidah tafsir.',
                'credits' => 2,
                'theory_credits' => 2,
                'practical_credits' => 0,
                'type' => CourseType::THEORY,
                'category' => 'institutional',
                'status' => CourseStatus::ACTIVE,
            ],
            [
                'code' => 'MKU-106',
                'name' => 'Studi Hadits & Ulumul Hadits',
                'short_name' => 'Ulumul Hadits',
                'description' => 'Metodologi kritik sanad dan matan, klasifikasi shahih, hasan, dhaif, dan takhrij.',
                'credits' => 2,
                'theory_credits' => 2,
                'practical_credits' => 0,
                'type' => CourseType::THEORY,
                'category' => 'institutional',
                'status' => CourseStatus::ACTIVE,
            ],

            // 2. Program Studi PAI (Pendidikan Agama Islam)
            [
                'code' => 'PAI-201',
                'name' => 'Ilmu Pendidikan Islam',
                'short_name' => 'IPI',
                'description' => 'Fondasi filosofis, ontologis, epistemologis, dan aksiologis pedagogi islam.',
                'credits' => 3,
                'theory_credits' => 3,
                'practical_credits' => 0,
                'type' => CourseType::THEORY,
                'category' => 'program',
                'status' => CourseStatus::ACTIVE,
            ],
            [
                'code' => 'PAI-202',
                'name' => 'Psikologi Perkembangan Peserta Didik',
                'short_name' => 'Psikologi Perkembangan',
                'description' => 'Karakteristik kognitif, afektif, psikomotorik, dan sosial siswa madrasah/sekolah.',
                'credits' => 2,
                'theory_credits' => 2,
                'practical_credits' => 0,
                'type' => CourseType::THEORY,
                'category' => 'program',
                'status' => CourseStatus::ACTIVE,
            ],
            [
                'code' => 'PAI-203',
                'name' => 'Metodologi Pembelajaran PAI Berbasis TPACK',
                'short_name' => 'Metodologi PAI',
                'description' => 'Model, pendekatan, dan strategi belajar PAI abad 21 berbantuan multimedia.',
                'credits' => 3,
                'theory_credits' => 2,
                'practical_credits' => 1,
                'type' => CourseType::MIXED,
                'category' => 'program',
                'status' => CourseStatus::ACTIVE,
            ],
            [
                'code' => 'PAI-301',
                'name' => 'Microteaching & Keterampilan Dasar Mengajar',
                'short_name' => 'Microteaching',
                'description' => 'Praktik mengajar skala terbatas di laboratorium microteaching berteknologi audio-visual.',
                'credits' => 2,
                'theory_credits' => 0,
                'practical_credits' => 2,
                'type' => CourseType::PRACTICAL,
                'category' => 'program',
                'status' => CourseStatus::ACTIVE,
            ],
            [
                'code' => 'PAI-302',
                'name' => 'Evaluasi & Asesmen Pembelajaran PAI',
                'short_name' => 'Evaluasi PAI',
                'description' => 'Penyusunan instrumen HOTS, rubrik asesmen autentik, dan analisis butir soal.',
                'credits' => 3,
                'theory_credits' => 2,
                'practical_credits' => 1,
                'type' => CourseType::MIXED,
                'category' => 'program',
                'status' => CourseStatus::ACTIVE,
            ],

            // 3. Program Studi HKI (Hukum Keluarga Islam)
            [
                'code' => 'HKI-201',
                'name' => 'Pengantar Hukum Islam & Ushul Fiqh',
                'short_name' => 'PHI & Ushul Fiqh',
                'description' => 'Konsep qawaid fiqhiyyah, adillatut tasyri\', qiyas, ijma\', dan istihsan.',
                'credits' => 3,
                'theory_credits' => 3,
                'practical_credits' => 0,
                'type' => CourseType::THEORY,
                'category' => 'program',
                'status' => CourseStatus::ACTIVE,
            ],
            [
                'code' => 'HKI-202',
                'name' => 'Fiqh Munakahat & Kompilasi Hukum Islam',
                'short_name' => 'Fiqh Munakahat',
                'description' => 'Hukum perkawinan, hak nafkah, hadhanah, dan peradilan agama di Indonesia.',
                'credits' => 3,
                'theory_credits' => 3,
                'practical_credits' => 0,
                'type' => CourseType::THEORY,
                'category' => 'program',
                'status' => CourseStatus::ACTIVE,
            ],
            [
                'code' => 'HKI-301',
                'name' => 'Praktik Peradilan Semu (Moot Court Syariah)',
                'short_name' => 'Peradilan Semu',
                'description' => 'Simulasi persidangan sengketa waris, perkawinan, dan ekonomi syariah di ruang sidang.',
                'credits' => 2,
                'theory_credits' => 0,
                'practical_credits' => 2,
                'type' => CourseType::PRACTICAL,
                'category' => 'program',
                'status' => CourseStatus::ACTIVE,
            ],

            // 4. Program Studi ES (Ekonomi Syariah)
            [
                'code' => 'ES-201',
                'name' => 'Pengantar Ekonomi Mikro & Makro Islam',
                'short_name' => 'Ekonomi Islam',
                'description' => 'Prinsip distribusi keadilan, larangan riba, maysir, gharar, dan instrumen moneter islam.',
                'credits' => 3,
                'theory_credits' => 3,
                'practical_credits' => 0,
                'type' => CourseType::THEORY,
                'category' => 'program',
                'status' => CourseStatus::ACTIVE,
            ],
            [
                'code' => 'ES-202',
                'name' => 'Fiqh Muamalah Kontemporer & Akad Finansial',
                'short_name' => 'Fiqh Muamalah',
                'description' => 'Kajian akad mudharabah, musyarakah, murabahah, ijarah, wakalah, dan fintech syariah.',
                'credits' => 3,
                'theory_credits' => 3,
                'practical_credits' => 0,
                'type' => CourseType::THEORY,
                'category' => 'program',
                'status' => CourseStatus::ACTIVE,
            ],
        ];

        foreach ($courses as $c) {
            Course::firstOrCreate(['code' => $c['code']], $c);
        }

        // Set Prerequisites:
        $arab1 = Course::where('code', 'MKU-103')->first();
        $arab2 = Course::where('code', 'MKU-104')->first();
        if ($arab1 && $arab2) {
            $arab2->prerequisites()->syncWithoutDetaching([
                $arab1->id => ['minimum_grade' => 'C'],
            ]);
        }

        $metodologi = Course::where('code', 'PAI-203')->first();
        $microteaching = Course::where('code', 'PAI-301')->first();
        if ($metodologi && $microteaching) {
            $microteaching->prerequisites()->syncWithoutDetaching([
                $metodologi->id => ['minimum_grade' => 'B'],
            ]);
        }

        $phi = Course::where('code', 'HKI-201')->first();
        $munakahat = Course::where('code', 'HKI-202')->first();
        if ($phi && $munakahat) {
            $munakahat->prerequisites()->syncWithoutDetaching([
                $phi->id => ['minimum_grade' => 'C'],
            ]);
        }
    }
}
