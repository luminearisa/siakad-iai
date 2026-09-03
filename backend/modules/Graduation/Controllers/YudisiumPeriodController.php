<?php

namespace Modules\Graduation\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Graduation\Models\YudisiumPeriod;

class YudisiumPeriodController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = YudisiumPeriod::with('semester')
            ->withCount('participants')
            ->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $s = $request->query('search');
            $query->where('name', 'like', "%{$s}%");
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = (int) $request->query('per_page', 10);
        $periods = $query->paginate($perPage);

        return $this->successResponse(
            data: $periods->items(),
            message: 'Daftar periode yudisium berhasil dimuat.',
            meta: [
                'current_page' => $periods->currentPage(),
                'last_page' => $periods->lastPage(),
                'per_page' => $periods->perPage(),
                'total' => $periods->total(),
            ]
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'name' => ['required', 'string', 'max:255'],
            'registration_start_date' => ['required', 'date'],
            'registration_end_date' => ['required', 'date', 'after_or_equal:registration_start_date'],
            'yudisium_date' => ['required', 'date', 'after_or_equal:registration_end_date'],
            'quota' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $period = YudisiumPeriod::create($validated);

        return $this->successResponse(
            data: $period->load('semester'),
            message: 'Periode yudisium berhasil ditambahkan.',
            code: 201
        );
    }

    public function show(YudisiumPeriod $period): JsonResponse
    {
        return $this->successResponse(
            data: $period->load(['semester', 'participants.student']),
            message: 'Detail periode yudisium berhasil dimuat.'
        );
    }

    public function update(Request $request, YudisiumPeriod $period): JsonResponse
    {
        $validated = $request->validate([
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'name' => ['required', 'string', 'max:255'],
            'registration_start_date' => ['required', 'date'],
            'registration_end_date' => ['required', 'date', 'after_or_equal:registration_start_date'],
            'yudisium_date' => ['required', 'date', 'after_or_equal:registration_end_date'],
            'quota' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $period->update($validated);

        return $this->successResponse(
            data: $period->load('semester'),
            message: 'Periode yudisium berhasil diperbarui.'
        );
    }

    public function destroy(YudisiumPeriod $period): JsonResponse
    {
        $period->delete();

        return $this->successResponse(
            data: null,
            message: 'Periode yudisium berhasil dihapus.'
        );
    }
}
