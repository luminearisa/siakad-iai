<?php

namespace Modules\MBKM\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\MBKM\Models\MbkmProgramType;

/**
 * Master/reference data for MBKM program types. Program "jenis" is data, never a
 * hardcoded condition, so this endpoint is what the UI reads to build its lists.
 */
class MbkmProgramTypeController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = MbkmProgramType::query()->withCount('programs');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['name', 'code'],
            filterableColumns: [],
            defaultSort: 'sort_order',
            defaultDirection: 'asc',
            defaultPerPage: 100
        );

        return $this->paginatedResponse($paginator, 'Jenis program MBKM berhasil dimuat.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:mbkm_program_types,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $type = MbkmProgramType::create($validated);

        return $this->successResponse($type, 'Jenis program MBKM berhasil ditambahkan.', 201);
    }

    public function show(MbkmProgramType $programType): JsonResponse
    {
        return $this->successResponse($programType->loadCount('programs'), 'Detail jenis program MBKM.');
    }

    public function update(Request $request, MbkmProgramType $programType): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('mbkm_program_types', 'code')->ignore($programType->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $programType->update($validated);

        return $this->successResponse($programType->fresh(), 'Jenis program MBKM berhasil diperbarui.');
    }

    public function destroy(MbkmProgramType $programType): JsonResponse
    {
        if ($programType->programs()->exists()) {
            return $this->errorResponse('Jenis program masih dipakai oleh program MBKM sehingga tidak dapat dihapus.', 422);
        }

        $programType->delete();

        return $this->successResponse(null, 'Jenis program MBKM berhasil dihapus.');
    }
}
