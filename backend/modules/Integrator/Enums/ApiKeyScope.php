<?php

namespace Modules\Integrator\Enums;

/**
 * Scopes that can be granted to an integration API key.
 *
 * A key may only reach an endpoint when it holds the scope the endpoint asks for.
 * There is intentionally no wildcard scope: an integrator that needs student PII
 * must be granted `students.pii` explicitly, and every request is logged.
 */
enum ApiKeyScope: string
{
    case REFERENCE_READ = 'reference.read';
    case ACADEMIC_READ = 'academic.read';
    case STUDENTS_READ = 'students.read';
    case STUDENTS_PII = 'students.pii';
    case LECTURERS_READ = 'lecturers.read';
    case COURSES_READ = 'courses.read';
    case CURRICULA_READ = 'curricula.read';
    case CLASSES_READ = 'classes.read';
    case ENROLLMENTS_READ = 'enrollments.read';
    case GRADES_READ = 'grades.read';
    case ACTIVITIES_READ = 'activities.read';
    case GRADUATION_READ = 'graduation.read';

    /**
     * Raw values, used by validation rules and the frontend scope picker.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::REFERENCE_READ => 'Reference Data (PT, Prodi, Periode, Skala Nilai)',
            self::ACADEMIC_READ => 'Academic Structure (Fakultas, Tahun Ajaran, Semester)',
            self::STUDENTS_READ => 'Students (masked PII)',
            self::STUDENTS_PII => 'Student PII (NIK, alamat, kontak)',
            self::LECTURERS_READ => 'Lecturers',
            self::COURSES_READ => 'Courses (Mata Kuliah)',
            self::CURRICULA_READ => 'Curricula (Kurikulum & Mata Kuliah Kurikulum)',
            self::CLASSES_READ => 'Classes (Kelas Kuliah, Pengampu, Jadwal)',
            self::ENROLLMENTS_READ => 'Enrollments (KRS / Peserta Kelas)',
            self::GRADES_READ => 'Grades (Nilai Perkuliahan)',
            self::ACTIVITIES_READ => 'Activities (Skripsi & MBKM)',
            self::GRADUATION_READ => 'Graduation (Yudisium / Lulusan)',
        };
    }

    /**
     * All scopes as value => label pairs.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::cases() as $case) {
            $labels[$case->value] = $case->label();
        }

        return $labels;
    }

    /**
     * Scopes that expose sensitive personal data and therefore deserve extra care.
     *
     * @return array<int, string>
     */
    public static function sensitive(): array
    {
        return [self::STUDENTS_PII->value];
    }

    /**
     * Scopes grouped for the management UI.
     *
     * @return array<string, array<int, string>>
     */
    public static function groups(): array
    {
        return [
            'Referensi' => [
                self::REFERENCE_READ->value,
                self::ACADEMIC_READ->value,
            ],
            'Mahasiswa & Dosen' => [
                self::STUDENTS_READ->value,
                self::STUDENTS_PII->value,
                self::LECTURERS_READ->value,
            ],
            'Kurikulum & Perkuliahan' => [
                self::COURSES_READ->value,
                self::CURRICULA_READ->value,
                self::CLASSES_READ->value,
                self::ENROLLMENTS_READ->value,
            ],
            'Nilai & Kelulusan' => [
                self::GRADES_READ->value,
                self::ACTIVITIES_READ->value,
                self::GRADUATION_READ->value,
            ],
        ];
    }
}
