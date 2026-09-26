<?php

namespace Modules\Academic\Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Academic\Enums\AcademicStatus;
use Modules\Academic\Enums\DegreeLevel;
use Modules\Academic\Enums\SemesterType;
use Modules\Academic\Models\AcademicYear;
use Modules\Academic\Models\Faculty;
use Modules\Academic\Models\Institution;
use Modules\Academic\Models\Semester;
use Modules\Academic\Models\StudyProgram;

class AcademicSeeder extends Seeder
{
    /** Bulan dimulainya tahun ajaran baru (1 September). */
    private const ACADEMIC_YEAR_START_MONTH = 9;

    public function run(): void
    {
        // 1. Create Institution
        $institution = Institution::firstOrCreate(
            ['code' => 'IAI-001'],
            [
                'name' => 'Institut Agama Islam Al-Irsyad Jakarta',
                'short_name' => 'IAI Al-Irsyad Jakarta',
                'address' => 'Jl. Kramat Raya No. 23, Senen, Jakarta Pusat, DKI Jakarta 10450',
                'phone' => '+62-21-3909123',
                'website' => 'https://alirsyad.ac.id',
                'logo_path' => 'https://alirsyad.ac.id/uploads/settings/img_6a4bccce2e6408.82817006.webp',
                'status' => AcademicStatus::ACTIVE,
            ]
        );

        // 2. Create Faculties
        $ftik = Faculty::firstOrCreate(
            ['code' => 'FTIK'],
            [
                'institution_id' => $institution->id,
                'name' => 'Fakultas Tarbiyah dan Ilmu Keguruan',
                'status' => AcademicStatus::ACTIVE,
            ]
        );

        $fsh = Faculty::firstOrCreate(
            ['code' => 'FSH'],
            [
                'institution_id' => $institution->id,
                'name' => 'Fakultas Syariah dan Hukum',
                'status' => AcademicStatus::ACTIVE,
            ]
        );

        $febi = Faculty::firstOrCreate(
            ['code' => 'FEBI'],
            [
                'institution_id' => $institution->id,
                'name' => 'Fakultas Ekonomi dan Bisnis Islam',
                'status' => AcademicStatus::ACTIVE,
            ]
        );

        $fud = Faculty::firstOrCreate(
            ['code' => 'FUD'],
            [
                'institution_id' => $institution->id,
                'name' => 'Fakultas Ushuluddin dan Dakwah',
                'status' => AcademicStatus::ACTIVE,
            ]
        );

        // 3. Create Study Programs
        $studyPrograms = [
            // FTIK
            [
                'code' => 'PAI',
                'faculty_id' => $ftik->id,
                'name' => 'Pendidikan Agama Islam',
                'degree' => DegreeLevel::S1,
            ],
            [
                'code' => 'PBA',
                'faculty_id' => $ftik->id,
                'name' => 'Pendidikan Bahasa Arab',
                'degree' => DegreeLevel::S1,
            ],
            [
                'code' => 'MPI',
                'faculty_id' => $ftik->id,
                'name' => 'Manajemen Pendidikan Islam',
                'degree' => DegreeLevel::S1,
            ],
            [
                'code' => 'PGMI',
                'faculty_id' => $ftik->id,
                'name' => 'Pendidikan Guru Madrasah Ibtidaiyah',
                'degree' => DegreeLevel::S1,
            ],
            // FSH
            [
                'code' => 'HKI',
                'faculty_id' => $fsh->id,
                'name' => 'Hukum Keluarga Islam (Ahwal Syakhshiyyah)',
                'degree' => DegreeLevel::S1,
            ],
            [
                'code' => 'HES',
                'faculty_id' => $fsh->id,
                'name' => 'Hukum Ekonomi Syariah (Muamalah)',
                'degree' => DegreeLevel::S1,
            ],
            // FEBI
            [
                'code' => 'ES',
                'faculty_id' => $febi->id,
                'name' => 'Ekonomi Syariah',
                'degree' => DegreeLevel::S1,
            ],
            [
                'code' => 'PBS',
                'faculty_id' => $febi->id,
                'name' => 'Perbankan Syariah',
                'degree' => DegreeLevel::S1,
            ],
            // FUD
            [
                'code' => 'IAT',
                'faculty_id' => $fud->id,
                'name' => 'Ilmu Al-Qur\'an dan Tafsir',
                'degree' => DegreeLevel::S1,
            ],
            [
                'code' => 'KPI',
                'faculty_id' => $fud->id,
                'name' => 'Komunikasi dan Penyiaran Islam',
                'degree' => DegreeLevel::S1,
            ],
        ];

        foreach ($studyPrograms as $sp) {
            StudyProgram::firstOrCreate(
                ['code' => $sp['code']],
                [
                    'faculty_id' => $sp['faculty_id'],
                    'name' => $sp['name'],
                    'degree' => $sp['degree'],
                    'status' => AcademicStatus::ACTIVE,
                ]
            );
        }

        // 4. Academic Years & Semesters (tahun ajaran berjalan mengikuti tanggal hari ini)
        $this->seedAcademicPeriods();
    }

    /**
     * Seed tahun ajaran + semester beserta seluruh jendela tanggal akademiknya.
     *
     * Gap #12 — sebelumnya krs_start_date/krs_end_date (dan jendela UTS/UAS/kuliah)
     * dibiarkan NULL sehingga gerbang jadwal KRS tidak pernah aktif. Sekarang setiap
     * semester punya jendela yang diturunkan dari start_date/end_date-nya sendiri dan
     * konsisten dengan validasi SemesterRequest.
     *
     * Seeder ini idempoten: memakai updateOrCreate pada kunci alami dan dijalankan
     * di dalam satu transaksi, sehingga aman dijalankan berulang tanpa duplikasi.
     */
    private function seedAcademicPeriods(): void
    {
        $today = now()->startOfDay();

        // Tahun ajaran baru di perguruan tinggi dimulai 1 September.
        $currentStartYear = $today->month >= self::ACADEMIC_YEAR_START_MONTH
            ? $today->year
            : $today->year - 1;

        // Tahun ajaran berjalan dibuat lebih dahulu agar tetap menjadi baris pertama —
        // seeder modul lain (MBKM, Graduation) mengikat periodenya ke Semester::first().
        $startYears = [$currentStartYear, $currentStartYear - 1];

        DB::transaction(function () use ($startYears, $today): void {
            // Reset status lebih dulu supaya unique index "satu periode aktif" tidak
            // bentrok ketika seeder dijalankan ulang.
            Semester::query()->update(['status' => AcademicStatus::INACTIVE->value]);
            AcademicYear::query()->update(['status' => AcademicStatus::INACTIVE->value]);

            $activeYear = null;
            $activeSemester = null;

            foreach ($startYears as $startYear) {
                $yearStart = Carbon::create($startYear, self::ACADEMIC_YEAR_START_MONTH, 1)->startOfDay();
                $yearEnd = Carbon::create($startYear + 1, 8, 31)->startOfDay();

                $year = AcademicYear::updateOrCreate(
                    ['name' => $this->yearName($startYear)],
                    [
                        'start_date' => $yearStart->toDateString(),
                        'end_date' => $yearEnd->toDateString(),
                        'status' => AcademicStatus::INACTIVE,
                    ]
                );

                $ganjil = $this->upsertSemester($year, [
                    'name' => 'Ganjil ' . $this->yearName($startYear),
                    'type' => SemesterType::GANJIL,
                    'start_date' => Carbon::create($startYear, 9, 1)->startOfDay(),
                    'end_date' => Carbon::create($startYear + 1, 1, 31)->startOfDay(),
                ], $today);

                $genap = $this->upsertSemester($year, [
                    'name' => 'Genap ' . $this->yearName($startYear),
                    'type' => SemesterType::GENAP,
                    'start_date' => Carbon::create($startYear + 1, 2, 1)->startOfDay(),
                    'end_date' => Carbon::create($startYear + 1, 7, 31)->startOfDay(),
                ], $today);

                if (! $today->betweenIncluded($yearStart, $yearEnd)) {
                    continue;
                }

                $activeYear = $year;
                $activeSemester = collect([$ganjil, $genap])->first(
                    fn (Semester $semester) => $today->betweenIncluded(
                        $semester->start_date->startOfDay(),
                        $semester->end_date->startOfDay()
                    )
                ) ?? $ganjil;
            }

            // Tepat satu tahun ajaran dan satu semester aktif.
            $activeYear?->update(['status' => AcademicStatus::ACTIVE]);
            $activeSemester?->update(['status' => AcademicStatus::ACTIVE]);
        });
    }

    /**
     * @param  array{name: string, type: SemesterType, start_date: Carbon, end_date: Carbon}  $definition
     */
    private function upsertSemester(AcademicYear $year, array $definition, Carbon $today): Semester
    {
        return Semester::updateOrCreate(
            [
                'academic_year_id' => $year->id,
                'name' => $definition['name'],
            ],
            array_merge(
                [
                    'type' => $definition['type'],
                    'start_date' => $definition['start_date']->toDateString(),
                    'end_date' => $definition['end_date']->toDateString(),
                    'min_attendance_uts_percentage' => 50,
                    'min_attendance_uas_percentage' => 80,
                    'total_teaching_weeks' => 16,
                    'status' => AcademicStatus::INACTIVE,
                ],
                $this->buildAcademicWindows($definition['start_date'], $definition['end_date'], $today)
            )
        );
    }

    /**
     * Turunkan seluruh jendela akademik dari rentang semester itu sendiri.
     *
     * @return array<string, string>
     */
    private function buildAcademicWindows(Carbon $start, Carbon $end, Carbon $today): array
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();
        $length = max(1, (int) $start->diffInDays($end));

        // KRS dibuka bersamaan dengan awal semester dan ditutup ~3 minggu kemudian.
        $krsStart = $start->copy();
        $krsEnd = $this->clamp($start->copy()->addDays(21), $start, $end);

        // Untuk semester yang sedang berjalan, pastikan jendela KRS masih mencakup
        // hari ini supaya data seed langsung dapat dipakai (batal-tambah/perpanjangan).
        if ($today->betweenIncluded($start, $end) && $today->greaterThan($krsEnd)) {
            $krsEnd = $this->clamp($today->copy()->addDays(14), $start, $end);
        }

        // KPRS (perubahan KRS) menyusul tepat setelah KRS ditutup.
        $kprsStart = $this->clamp($krsEnd->copy()->addDay(), $start, $end);
        $kprsEnd = $this->clamp($kprsStart->copy()->addDays(7), $start, $end);

        // UTS di tengah periode, UAS pada pekan terakhir semester.
        $utsStart = $this->clamp($start->copy()->addDays((int) floor($length * 0.45)), $start, $end);
        $utsEnd = $this->clamp($utsStart->copy()->addDays(7), $start, $end);
        $uasEnd = $end->copy();
        $uasStart = $this->clamp($end->copy()->subDays(7), $start, $end);

        // Perkuliahan berjalan sejak awal semester hingga sehari sebelum UAS.
        $lectureStart = $start->copy();
        $lectureEnd = $this->clamp($uasStart->copy()->subDay(), $start, $end);

        return [
            'krs_start_date' => $krsStart->toDateString(),
            'krs_end_date' => $krsEnd->toDateString(),
            'kprs_start_date' => $kprsStart->toDateString(),
            'kprs_end_date' => $kprsEnd->toDateString(),
            'lecture_start_date' => $lectureStart->toDateString(),
            'lecture_end_date' => $lectureEnd->toDateString(),
            'uts_start_date' => $utsStart->toDateString(),
            'uts_end_date' => $utsEnd->toDateString(),
            'uas_start_date' => $uasStart->toDateString(),
            'uas_end_date' => $uasEnd->toDateString(),
        ];
    }

    private function clamp(Carbon $date, Carbon $min, Carbon $max): Carbon
    {
        if ($date->lessThan($min)) {
            return $min->copy();
        }

        if ($date->greaterThan($max)) {
            return $max->copy();
        }

        return $date->copy();
    }

    private function yearName(int $startYear): string
    {
        return $startYear . '/' . ($startYear + 1);
    }
}
