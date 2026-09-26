<?php

namespace Modules\MBKM\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\MBKM\Models\MbkmCooperation;

/**
 * Cooperation deeds (MoU / MoA / IA / PKS / surat kerja sama) between the
 * institution and an MBKM partner. Files reuse the module's single document store.
 */
class MbkmCooperationController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = MbkmCooperation::query()->with(['partner', 'program', 'documents']);

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['title', 'number'],
            filterableColumns: ['partner_id', 'program_id', 'type', 'status'],
            defaultSort: 'id',
            defaultDirection: 'desc'
        );

        return $this->paginatedResponse($paginator, 'Data kerja sama MBKM berhasil dimuat.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $validated['created_by'] = $request->user()?->id;

        $cooperation = MbkmCooperation::create($validated);

        return $this->successResponse($cooperation->load(['partner', 'program']), 'Kerja sama berhasil ditambahkan.', 201);
    }

    public function show(MbkmCooperation $cooperation): JsonResponse
    {
        return $this->successResponse($cooperation->load(['partner', 'program', 'documents']), 'Detail kerja sama.');
    }

    public function update(Request $request, MbkmCooperation $cooperation): JsonResponse
    {
        $cooperation->update($this->validatePayload($request));

        return $this->successResponse($cooperation->fresh(['partner', 'program']), 'Kerja sama berhasil diperbarui.');
    }

    public function destroy(MbkmCooperation $cooperation): JsonResponse
    {
        $cooperation->delete();

        return $this->successResponse(null, 'Kerja sama berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatePayload(Request $request): array
    {
        return $request->validate([
            'partner_id' => ['required', 'integer', 'exists:mbkm_partners,id'],
            'program_id' => ['nullable', 'integer', 'exists:mbkm_programs,id'],
            'type' => ['required', 'string', 'in:mou,moa,ia,pks,letter,other'],
            'number' => ['nullable', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'string', 'in:active,expired,terminated,draft'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
