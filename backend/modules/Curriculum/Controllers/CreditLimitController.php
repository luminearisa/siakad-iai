<?php

namespace Modules\Curriculum\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Curriculum\Models\CreditLimit;

class CreditLimitController extends Controller
{
    use HasApiResponse;

    public function index(Request $request): JsonResponse
    {
        $limits = CreditLimit::query()
            ->withCount('curricula')
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy('id', 'asc')
            ->get();

        return $this->successResponse(
            data: $limits,
            message: 'Credit limits retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'rules' => ['nullable', 'array'],
            'rules.*.min_gpa' => ['required_with:rules', 'numeric', 'min:0', 'max:4'],
            'rules.*.max_gpa' => ['required_with:rules', 'numeric', 'min:0', 'max:4'],
            'rules.*.max_sks' => ['required_with:rules', 'integer', 'min:1', 'max:30'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $limit = CreditLimit::create($validated);

        return $this->successResponse(
            data: $limit,
            message: 'Credit limit created successfully.',
            code: 201
        );
    }

    public function show(CreditLimit $creditLimit): JsonResponse
    {
        return $this->successResponse(
            data: $creditLimit->loadCount('curricula'),
            message: 'Credit limit retrieved successfully.'
        );
    }

    public function update(Request $request, CreditLimit $creditLimit): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'rules' => ['nullable', 'array'],
            'rules.*.min_gpa' => ['required_with:rules', 'numeric', 'min:0', 'max:4'],
            'rules.*.max_gpa' => ['required_with:rules', 'numeric', 'min:0', 'max:4'],
            'rules.*.max_sks' => ['required_with:rules', 'integer', 'min:1', 'max:30'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $creditLimit->update($validated);

        return $this->successResponse(
            data: $creditLimit,
            message: 'Credit limit updated successfully.'
        );
    }

    public function destroy(CreditLimit $creditLimit): JsonResponse
    {
        $creditLimit->delete();

        return $this->successResponse(
            data: null,
            message: 'Credit limit deleted successfully.'
        );
    }
}
