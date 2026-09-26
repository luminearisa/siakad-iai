<?php

namespace Modules\Academic\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Academic\Enums\AcademicStatus;
use Modules\Academic\Enums\SemesterType;
use Modules\Academic\Models\AcademicYear;

class SemesterRequest extends FormRequest
{
    /**
     * Sub-jendela akademik yang wajib berada di dalam rentang semester.
     *
     * @var array<string, string>
     */
    private const SUB_WINDOWS = [
        'krs' => 'pengisian KRS',
        'kprs' => 'perubahan KRS (KPRS)',
        'lecture' => 'perkuliahan',
        'uts' => 'Ujian Tengah Semester (UTS)',
        'uas' => 'Ujian Akhir Semester (UAS)',
    ];

    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('semesters.manage') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('semester')?->id ?? $this->route('semester');

        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(SemesterType::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'krs_start_date' => ['nullable', 'date'],
            'krs_end_date' => ['nullable', 'date', 'after_or_equal:krs_start_date'],
            'kprs_start_date' => ['nullable', 'date'],
            'kprs_end_date' => ['nullable', 'date', 'after_or_equal:kprs_start_date'],
            'lecture_start_date' => ['nullable', 'date'],
            'lecture_end_date' => ['nullable', 'date'],
            'uts_start_date' => ['nullable', 'date'],
            'uts_end_date' => ['nullable', 'date'],
            'uas_start_date' => ['nullable', 'date'],
            'uas_end_date' => ['nullable', 'date'],
            'min_attendance_uts_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_attendance_uas_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'total_teaching_weeks' => ['nullable', 'integer', 'min:1', 'max:52'],
            'status' => ['nullable', Rule::enum(AcademicStatus::class)],
        ];
    }

    /**
     * Gap #11 — validasi tanggal semester terhadap tahun ajaran induk serta
     * validasi silang seluruh sub-jendela (KRS, KPRS, kuliah, UTS, UAS).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $range = $this->semesterRange($validator);

            if ($range === null) {
                return;
            }

            [$start, $end] = $range;

            $this->validateInsideAcademicYear($validator, $start, $end);
            $this->validateSubWindows($validator, $start, $end);
        });
    }

    /**
     * Rentang semester dari payload (untuk update: fallback ke data tersimpan).
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function semesterRange(Validator $validator): ?array
    {
        $existing = $validator->errors()->keys();

        if (array_intersect(['academic_year_id', 'start_date', 'end_date'], $existing) !== []) {
            return null;
        }

        $start = $this->dateInput('start_date');
        $end = $this->dateInput('end_date');

        if ($start === null || $end === null || $end->lt($start)) {
            return null;
        }

        return [$start, $end];
    }

    private function validateInsideAcademicYear(Validator $validator, Carbon $start, Carbon $end): void
    {
        $academicYear = AcademicYear::find($this->input('academic_year_id'));

        if ($academicYear === null) {
            return;
        }

        $yearStart = Carbon::parse($academicYear->start_date)->startOfDay();
        $yearEnd = Carbon::parse($academicYear->end_date)->startOfDay();

        if ($start->lt($yearStart) || $end->gt($yearEnd)) {
            $validator->errors()->add(
                'start_date',
                sprintf(
                    'Rentang semester (%s s.d. %s) harus berada di dalam periode tahun ajaran %s (%s s.d. %s).',
                    $start->format('d/m/Y'),
                    $end->format('d/m/Y'),
                    $academicYear->name,
                    $yearStart->format('d/m/Y'),
                    $yearEnd->format('d/m/Y')
                )
            );
        }
    }

    private function validateSubWindows(Validator $validator, Carbon $start, Carbon $end): void
    {
        $existing = $validator->errors()->keys();

        foreach (self::SUB_WINDOWS as $prefix => $label) {
            $startField = $prefix . '_start_date';
            $endField = $prefix . '_end_date';

            if (array_intersect([$startField, $endField], $existing) !== []) {
                continue;
            }

            $windowStart = $this->dateInput($startField);
            $windowEnd = $this->dateInput($endField);

            if ($windowStart === null && $windowEnd === null) {
                continue;
            }

            if ($windowStart !== null && $windowEnd !== null && $windowEnd->lt($windowStart)) {
                $validator->errors()->add(
                    $endField,
                    "Tanggal akhir {$label} tidak boleh lebih awal dari tanggal mulainya."
                );
            }

            $this->assertInsideSemester($validator, $startField, $windowStart, $label, 'mulai', $start, $end);
            $this->assertInsideSemester($validator, $endField, $windowEnd, $label, 'akhir', $start, $end);
        }

        // Perkuliahan harus mendahului UTS/UAS bila keduanya diisi.
        $lectureStart = $this->dateInput('lecture_start_date');

        foreach (['uts' => self::SUB_WINDOWS['uts'], 'uas' => self::SUB_WINDOWS['uas']] as $prefix => $label) {
            $windowStart = $this->dateInput($prefix . '_start_date');

            if ($lectureStart !== null && $windowStart !== null && $windowStart->lt($lectureStart)) {
                $validator->errors()->add(
                    $prefix . '_start_date',
                    "Jadwal {$label} tidak boleh dimulai sebelum periode perkuliahan ({$lectureStart->format('d/m/Y')})."
                );
            }
        }
    }

    private function assertInsideSemester(
        Validator $validator,
        string $field,
        ?Carbon $value,
        string $label,
        string $position,
        Carbon $start,
        Carbon $end
    ): void {
        if ($value === null) {
            return;
        }

        if ($value->lt($start) || $value->gt($end)) {
            $validator->errors()->add(
                $field,
                sprintf(
                    'Tanggal %s %s (%s) harus berada di dalam rentang semester (%s s.d. %s).',
                    $position,
                    $label,
                    $value->format('d/m/Y'),
                    $start->format('d/m/Y'),
                    $end->format('d/m/Y')
                )
            );
        }
    }

    private function dateInput(string $field): ?Carbon
    {
        $value = $this->input($field);

        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
