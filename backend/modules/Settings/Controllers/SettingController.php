<?php

namespace Modules\Settings\Controllers;

use App\Http\Controllers\Controller;
use App\Support\QueryFilter;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Settings\Models\Setting;
use Modules\Settings\Requests\BatchUpdateSettingRequest;
use Modules\Settings\Requests\UpdateSettingRequest;
use Modules\Settings\Resources\SettingResource;
use Modules\Settings\Services\SettingService;

class SettingController extends Controller
{
    use HasApiResponse;

    public function __construct(
        protected SettingService $settingService
    ) {}

    /**
     * List all settings with search and filter by group.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Setting::query();

        $paginator = QueryFilter::apply(
            query: $query,
            request: $request,
            searchableColumns: ['key', 'description'],
            filterableColumns: ['group', 'type'],
            defaultSort: 'group',
            defaultDirection: 'asc',
            defaultPerPage: 50
        );

        return $this->paginatedResponse(
            paginator: $paginator,
            message: 'Settings retrieved successfully.',
            resourceClass: SettingResource::class
        );
    }

    /**
     * Show setting by key or id.
     */
    public function show(string $key): JsonResponse
    {
        $setting = is_numeric($key)
            ? Setting::findOrFail($key)
            : Setting::where('key', $key)->firstOrFail();

        return $this->successResponse(
            data: new SettingResource($setting),
            message: 'Setting retrieved successfully.'
        );
    }

    /**
     * Update a single setting.
     */
    public function update(UpdateSettingRequest $request, string $key): JsonResponse
    {
        $setting = is_numeric($key)
            ? Setting::findOrFail($key)
            : Setting::where('key', $key)->firstOrFail();

        $updated = $this->settingService->set(
            key: $setting->key,
            value: $request->validated('value'),
            type: $request->validated('type'),
            description: $request->validated('description')
        );

        return $this->successResponse(
            data: new SettingResource($updated),
            message: 'Setting updated successfully.'
        );
    }

    /**
     * Batch update multiple settings.
     */
    public function batchUpdate(BatchUpdateSettingRequest $request): JsonResponse
    {
        $updated = $this->settingService->batchUpdate($request->validated('settings'));

        return $this->successResponse(
            data: SettingResource::collection($updated),
            message: 'Settings batch updated successfully.'
        );
    }
}
