<?php

namespace Modules\MBKM\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\MBKM\Models\MbkmPartner;

class MbkmPartnerController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = MbkmPartner::query()->withCount(['cooperations', 'placements']);

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['name', 'code', 'city', 'email'],
            filterableColumns: ['type', 'status', 'country'],
            defaultSort: 'name',
            defaultDirection: 'asc'
        );

        return $this->paginatedResponse($paginator, 'Data mitra MBKM berhasil dimuat.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $partner = MbkmPartner::create($validated);

        return $this->successResponse($partner, 'Mitra MBKM berhasil ditambahkan.', 201);
    }

    public function show(MbkmPartner $partner): JsonResponse
    {
        return $this->successResponse(
            $partner->load(['cooperations.program', 'cooperations.documents']),
            'Detail mitra MBKM.'
        );
    }

    public function update(Request $request, MbkmPartner $partner): JsonResponse
    {
        $validated = $this->validatePayload($request, $partner->id);

        $partner->update($validated);

        return $this->successResponse($partner->fresh(), 'Mitra MBKM berhasil diperbarui.');
    }

    public function destroy(MbkmPartner $partner): JsonResponse
    {
        if ($partner->placements()->exists()) {
            return $this->errorResponse('Mitra masih dipakai pada penempatan peserta sehingga tidak dapat dihapus.', 422);
        }

        $partner->delete();

        return $this->successResponse(null, 'Mitra MBKM berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('mbkm_partners', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:company,university,school,government,organization,nonprofit,startup,research_center,laboratory,international,other'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'contact_person_name' => ['nullable', 'string', 'max:255'],
            'contact_person_position' => ['nullable', 'string', 'max:255'],
            'contact_person_email' => ['nullable', 'email', 'max:255'],
            'contact_person_phone' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'in:active,inactive,expired'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
