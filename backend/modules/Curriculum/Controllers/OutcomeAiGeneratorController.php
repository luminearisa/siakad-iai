<?php

namespace Modules\Curriculum\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Academic\Models\StudyProgram;

class OutcomeAiGeneratorController extends Controller
{
    use HasApiResponse;

    public function generate(Request $request): JsonResponse
    {
        $type = $request->input('type', 'pl');
        $prodiId = $request->input('study_program_id');
        $topic = $request->input('topic', 'Pendidikan Agama Islam');

        $prodi = $prodiId ? StudyProgram::find($prodiId) : null;
        $prodiName = $prodi ? $prodi->name : 'Pendidikan Agama Islam';

        $suggestions = match ($type) {
            'pl' => [
                [
                    'code' => 'PL-01',
                    'name' => 'Pendidik & Praktisi ' . $prodiName,
                    'profession' => 'Guru / Pendidik / Praktisi Profesional',
                    'description' => 'Mampu merencanakan, mengimplementasikan, dan mengevaluasi pembelajaran dan keahlian di bidang ' . $prodiName . ' berbasis teknologi dan berakhlak mulia.',
                ],
                [
                    'code' => 'PL-02',
                    'name' => 'Pengembang Bahan Ajar & Kurikulum ' . $prodiName,
                    'profession' => 'Pengembang Kurikulum & Edukasi Digital',
                    'description' => 'Mampu mendesain dan menginovasi media pembelajaran interaktif serta perangkat kurikulum relevan dengan kebutuhan industri 4.0.',
                ],
                [
                    'code' => 'PL-03',
                    'name' => 'Edupreneur & Konsultan ' . $prodiName,
                    'profession' => 'Konsultan / Entrepreneur Lembaga',
                    'description' => 'Mampu menginisiasi dan memimpin unit usaha edukatif secara profesional, berintegritas syariah, dan berorientasi kebermanfaatan sosial.',
                ],
            ],
            'cpl' => [
                [
                    'code' => 'S-01',
                    'category' => 'sikap',
                    'name' => 'Bertakwa kepada Tuhan Yang Maha Esa dan mampu menunjukkan sikap religius serta menjunjung tinggi etika keislaman.',
                    'description' => 'Internalisasi nilai ketakwaan, kejujuran, dan integritas moral dalam profesi.',
                ],
                [
                    'code' => 'P-01',
                    'category' => 'pengetahuan',
                    'name' => 'Menguasai konsep teoretis, filosofis, dan metodologis di bidang keilmuan ' . $prodiName . ' secara komprehensif.',
                    'description' => 'Penguasaan konsep keilmuan inti dan kebaruan IPTEK.',
                ],
                [
                    'code' => 'KU-01',
                    'category' => 'keterampilan_umum',
                    'name' => 'Mampu menerapkan pemikiran logis, kritis, sistematis, dan inovatif dalam konteks pengembangan keilmuan terapan.',
                    'description' => 'Keterampilan pemecahan masalah (problem solving) dan kolaborasi tim.',
                ],
                [
                    'code' => 'KK-01',
                    'category' => 'keterampilan_khusus',
                    'name' => 'Mampu merancang dan mengevaluasi program kerja serta media berbasis teknologi terpadu pada rumpun ' . $prodiName . '.',
                    'description' => 'Keahlian teknis spesifik lulusan program studi.',
                ],
            ],
            'cpmk' => [
                [
                    'code' => 'CPMK-01',
                    'name' => 'Mampu menganalisis prinsip fundamental dan teori mutakhir dalam konteks pembelajaran ' . $topic . '.',
                    'description' => 'Taksonomi C4 (Analisis) dan integrasi nilai-nilai keislaman.',
                ],
                [
                    'code' => 'CPMK-02',
                    'name' => 'Mampu merancang instrumen dan strategi implementasi praktis terkait materi ' . $topic . ' secara mandiri.',
                    'description' => 'Taksonomi C6 (Kreasi/Perancangan) berbasis luaran OBE.',
                ],
            ],
            'sub_cpmk' => [
                [
                    'code' => 'Sub-CPMK 1.1',
                    'name' => 'Mampu menjelaskan definisi, ruang lingkup, dan urgensi materi ' . $topic . ' secara tepat.',
                    'description' => 'Indikator: Ketepatan penjelasan konsep dan penguasaan referensi otoritatif.',
                ],
                [
                    'code' => 'Sub-CPMK 1.2',
                    'name' => 'Mampu memformulasikan skema pemecahan kasus nyata pada topik ' . $topic . '.',
                    'description' => 'Indikator: Kualitas analisis data kasus dan ketepatan solusi yang diajukan.',
                ],
            ],
            default => [],
        };

        return $this->successResponse(
            data: $suggestions,
            message: 'Rekomendasi taksonomi OBE cerdas berhasil dibuat oleh AI Assistant.'
        );
    }
}
