<?php

namespace Modules\Integrator\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for the read-only integration endpoints.
 *
 * All filters are optional; the rules exist so that a malformed filter (a stray
 * date, a 10.000 row page) is rejected with 422 instead of silently returning
 * wrong data during an overnight feeder sync.
 */
class IntegrationQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:500'],
            'search' => ['sometimes', 'string', 'max:150'],
            'sort' => ['sometimes', 'string', 'max:50'],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'updated_since' => ['sometimes', 'date'],

            'study_program_id' => ['sometimes', 'integer', 'exists:study_programs,id'],
            'semester_id' => ['sometimes', 'integer', 'exists:semesters,id'],
            'class_id' => ['sometimes', 'integer', 'exists:academic_classes,id'],
            'course_id' => ['sometimes', 'integer', 'exists:courses,id'],
            'student_id' => ['sometimes', 'integer', 'exists:students,id'],
            'student_number' => ['sometimes', 'string', 'max:30'],
            'yudisium_period_id' => ['sometimes', 'integer', 'exists:yudisium_periods,id'],
            'program_id' => ['sometimes', 'integer', 'exists:mbkm_programs,id'],
            'admission_year' => ['sometimes', 'integer', 'min:1900', 'max:2200'],

            'status' => ['sometimes', 'string', 'max:30'],
            'type' => ['sometimes', Rule::in(['thesis', 'mbkm'])],
            'with_components' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Filters that are meaningful for the given endpoint, so an accidental
     * `?status=...` on an endpoint without status does not silently do nothing.
     *
     * @return array<int, string>
     */
    public function activeFilters(): array
    {
        return array_values(array_filter(
            array_keys($this->query()),
            fn (string $key) => ! in_array($key, ['page', 'per_page', 'search', 'sort', 'direction', 'updated_since', 'with_components'], true)
        ));
    }
}
