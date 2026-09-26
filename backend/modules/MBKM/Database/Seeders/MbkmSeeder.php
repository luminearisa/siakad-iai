<?php

namespace Modules\MBKM\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\MBKM\Enums\ProgramStatus;
use Modules\MBKM\Enums\RequirementType;
use Modules\MBKM\Models\MbkmAssessmentComponent;
use Modules\MBKM\Models\MbkmCooperation;
use Modules\MBKM\Models\MbkmPartner;
use Modules\MBKM\Models\MbkmProgram;
use Modules\MBKM\Models\MbkmProgramType;
use Modules\MBKM\Models\MbkmSelectionCriteria;

/**
 * Seeds the MBKM reference data (program types) plus one ready-to-use example
 * program so the whole workflow can be exercised immediately after install.
 */
class MbkmSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'PMM', 'name' => 'Pertukaran Mahasiswa Merdeka', 'sort_order' => 1],
            ['code' => 'MAGANG', 'name' => 'Magang / Praktik Kerja', 'sort_order' => 2],
            ['code' => 'STUDI_INDEPENDEN', 'name' => 'Studi Independen', 'sort_order' => 3],
            ['code' => 'KAMPUS_MENGAJAR', 'name' => 'Kampus Mengajar', 'sort_order' => 4],
            ['code' => 'ASISTENSI_MENGAJAR', 'name' => 'Asistensi Mengajar', 'sort_order' => 5],
            ['code' => 'RISET', 'name' => 'Penelitian / Riset', 'sort_order' => 6],
            ['code' => 'PROYEK_KEMANUSIAAN', 'name' => 'Proyek Kemanusiaan', 'sort_order' => 7],
            ['code' => 'KEWIRAUSAHAAN', 'name' => 'Kewirausahaan', 'sort_order' => 8],
            ['code' => 'PROYEK_INDEPENDEN', 'name' => 'Proyek Independen', 'sort_order' => 9],
            ['code' => 'KKN', 'name' => 'KKN / Membangun Desa', 'sort_order' => 10],
            ['code' => 'INTERNASIONAL', 'name' => 'Program Internasional', 'sort_order' => 11],
            ['code' => 'MANDIRI', 'name' => 'Program Mandiri Institusi', 'sort_order' => 12],
            ['code' => 'LAINNYA', 'name' => 'Jenis Lain', 'sort_order' => 99],
        ];

        foreach ($types as $type) {
            MbkmProgramType::firstOrCreate(['code' => $type['code']], $type + ['is_active' => true]);
        }

        $partner = MbkmPartner::firstOrCreate(
            ['code' => 'PT-CONTOH'],
            [
                'name' => 'PT Teknologi Contoh Nusantara',
                'type' => 'company',
                'address' => 'Jl. Pendidikan No. 1',
                'city' => 'Bandung',
                'province' => 'Jawa Barat',
                'country' => 'Indonesia',
                'email' => 'hrd@contoh-nusantara.test',
                'website' => 'https://contoh-nusantara.test',
                'contact_person_name' => 'Rina Kurnia',
                'contact_person_position' => 'HR Manager',
                'contact_person_email' => 'rina@contoh-nusantara.test',
                'contact_person_phone' => '08123456789',
                'status' => 'active',
            ]
        );

        $typeMagang = MbkmProgramType::where('code', 'MAGANG')->first();

        $program = MbkmProgram::firstOrCreate(
            ['code' => 'MBKM-MAGANG-2026'],
            [
                'program_type_id' => $typeMagang?->id,
                'name' => 'Magang Bersertifikat 2026',
                'description' => 'Program magang bersertifikat dengan konversi SKS ke mata kuliah program studi.',
                'organizer_type' => 'study_program',
                'organizer_name' => 'Program Studi Teknik Informatika',
                'semester_id' => \Modules\Academic\Models\Semester::query()->value('id'),
                'quota' => 30,
                'min_semester' => 5,
                'min_gpa' => 2.75,
                'min_credits' => 80,
                'max_recognized_credits' => 20,
                'location_mode' => 'off_campus',
                'requires_documents' => true,
                'requires_learning_agreement' => true,
                'requires_attendance' => true,
                'requires_logbook' => true,
                'logbook_period' => 'weekly',
                'requires_assessment' => true,
                'requires_final_report' => true,
                'requires_recognition' => true,
                'min_attendance_percentage' => 75,
                'status' => ProgramStatus::DRAFT,
                'requirements_text' => 'Mahasiswa aktif, minimal semester 5, IPK minimal 2.75.',
            ]
        );

        MbkmCooperation::firstOrCreate(
            ['number' => 'MoU/001/MBKM/2026'],
            [
                'partner_id' => $partner->id,
                'program_id' => $program->id,
                'type' => 'mou',
                'title' => 'MoU Kerja Sama Magang MBKM',
                'start_date' => now()->startOfYear()->toDateString(),
                'end_date' => now()->addYears(3)->toDateString(),
                'status' => 'active',
            ]
        );

        $program->locations()->firstOrCreate(
            ['name' => 'Kantor Pusat Mitra'],
            [
                'location_mode' => 'off_campus',
                'address' => 'Jl. Pendidikan No. 1',
                'city' => 'Bandung',
                'province' => 'Jawa Barat',
                'country' => 'Indonesia',
            ]
        );

        $program->requirements()->firstOrCreate(
            ['code' => 'KTM'],
            [
                'type' => RequirementType::DOCUMENT,
                'name' => 'Kartu Tanda Mahasiswa (KTM)',
                'is_mandatory' => true,
                'is_document' => true,
                'sort_order' => 1,
            ]
        );

        $program->requirements()->firstOrCreate(
            ['code' => 'CV'],
            [
                'type' => RequirementType::DOCUMENT,
                'name' => 'Curriculum Vitae (CV)',
                'is_mandatory' => true,
                'is_document' => true,
                'sort_order' => 2,
            ]
        );

        $program->requirements()->firstOrCreate(
            ['code' => 'GPA_MIN'],
            [
                'type' => RequirementType::ACADEMIC,
                'name' => 'IPK minimal 2.75',
                'is_mandatory' => true,
                'rule' => ['field' => 'gpa', 'operator' => '>=', 'value' => 2.75],
                'sort_order' => 3,
            ]
        );

        if ($program->selectionCriteria()->count() === 0) {
            foreach ([
                ['name' => 'Kelengkapan Dokumen', 'weight' => 30, 'max_score' => 100],
                ['name' => 'Wawancara', 'weight' => 40, 'max_score' => 100],
                ['name' => 'Indeks Prestasi', 'weight' => 30, 'max_score' => 100],
            ] as $index => $criterion) {
                MbkmSelectionCriteria::create([
                    'program_id' => $program->id,
                    'name' => $criterion['name'],
                    'weight' => $criterion['weight'],
                    'max_score' => $criterion['max_score'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
            }
        }

        // Assessment component weights are program configuration, never hardcoded.
        if ($program->assessmentComponents()->count() === 0) {
            foreach ([
                ['code' => 'PERF', 'name' => 'Performance', 'type' => 'performance', 'weight' => 30, 'assessor_type' => 'field_supervisor'],
                ['code' => 'LOGB', 'name' => 'Logbook', 'type' => 'logbook', 'weight' => 10, 'assessor_type' => 'internal_supervisor'],
                ['code' => 'PROJ', 'name' => 'Final Project', 'type' => 'final_project', 'weight' => 30, 'assessor_type' => 'internal_supervisor'],
                ['code' => 'PART', 'name' => 'Partner Assessment', 'type' => 'partner', 'weight' => 20, 'assessor_type' => 'partner'],
                ['code' => 'PRES', 'name' => 'Presentation', 'type' => 'presentation', 'weight' => 10, 'assessor_type' => 'committee'],
            ] as $index => $component) {
                MbkmAssessmentComponent::create([
                    'program_id' => $program->id,
                    'code' => $component['code'],
                    'name' => $component['name'],
                    'type' => $component['type'],
                    'weight' => $component['weight'],
                    'max_score' => 100,
                    'assessor_type' => $component['assessor_type'],
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
            }
        }
    }
}
