<?php

namespace Modules\Settings\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Settings\Models\Setting;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'institution_name',
                'value' => 'Institut Agama Islam Nusantara',
                'type' => 'string',
                'group' => 'general',
                'description' => 'Official name of the academic institution.',
            ],
            [
                'key' => 'student_number_format',
                'value' => '{YEAR}{FACULTY_CODE}{PROG_CODE}{SEQ:4}',
                'type' => 'string',
                'group' => 'academic',
                'description' => 'Template pattern for generating student identification numbers (NIM).',
            ],
            [
                'key' => 'default_grading_scale',
                'value' => json_encode([
                    'A' => ['min' => 85, 'max' => 100, 'point' => 4.0],
                    'B+' => ['min' => 75, 'max' => 84.99, 'point' => 3.5],
                    'B' => ['min' => 65, 'max' => 74.99, 'point' => 3.0],
                    'C+' => ['min' => 60, 'max' => 64.99, 'point' => 2.5],
                    'C' => ['min' => 55, 'max' => 59.99, 'point' => 2.0],
                    'D' => ['min' => 40, 'max' => 54.99, 'point' => 1.0],
                    'E' => ['min' => 0, 'max' => 39.99, 'point' => 0.0],
                ]),
                'type' => 'json',
                'group' => 'academic',
                'description' => 'Standard grading scale with grade points.',
            ],
            [
                'key' => 'max_sks',
                'value' => '24',
                'type' => 'integer',
                'group' => 'academic',
                'description' => 'Maximum allowed semester credit units (SKS) per student.',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
