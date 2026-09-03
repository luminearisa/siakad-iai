<?php

namespace Modules\Assessment\Services;

use Illuminate\Support\Facades\DB;
use Modules\Assessment\Enums\SchemeStatus;
use Modules\Assessment\Models\AssessmentComponent;
use Modules\Assessment\Models\AssessmentScheme;
use Modules\Assessment\Models\AssessmentSchemeItem;
use Modules\Class\Models\AcademicClass;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class AssessmentSchemeService
{
    /**
     * Create an assessment scheme for an academic class.
     */
    public function createScheme(AcademicClass $class, array $data): AssessmentScheme
    {
        return DB::transaction(function () use ($class, $data) {
            $scheme = AssessmentScheme::create([
                'academic_class_id' => $class->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => SchemeStatus::DRAFT,
                'total_weight' => 0.00,
                'is_active' => false,
            ]);

            if (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    $this->addComponentToScheme(
                        $scheme,
                        (int) $item['assessment_component_id'],
                        (float) $item['weight']
                    );
                }
            }

            $scheme->recalculateTotalWeight();

            return $scheme->fresh(['items.component']);
        });
    }

    /**
     * Update a draft assessment scheme.
     */
    public function updateScheme(AssessmentScheme $scheme, array $data): AssessmentScheme
    {
        if ($scheme->status === SchemeStatus::ARCHIVED) {
            throw new UnprocessableEntityHttpException('Skema penilaian yang diarsipkan tidak dapat diubah.');
        }

        return DB::transaction(function () use ($scheme, $data) {
            $scheme->update([
                'name' => $data['name'] ?? $scheme->name,
                'description' => array_key_exists('description', $data) ? $data['description'] : $scheme->description,
            ]);

            if (isset($data['items']) && is_array($data['items'])) {
                // Synchronize items
                $scheme->items()->delete();
                foreach ($data['items'] as $item) {
                    $this->addComponentToScheme(
                        $scheme,
                        (int) $item['assessment_component_id'],
                        (float) $item['weight']
                    );
                }
                $scheme->recalculateTotalWeight();
            }

            return $scheme->fresh(['items.component']);
        });
    }

    /**
     * Add or update component in an assessment scheme.
     */
    public function addComponentToScheme(AssessmentScheme $scheme, int $componentId, float $weight): AssessmentSchemeItem
    {
        $component = AssessmentComponent::findOrFail($componentId);

        if ($component->academic_class_id !== $scheme->academic_class_id) {
            throw new UnprocessableEntityHttpException(
                "Komponen '{$component->name}' tidak berasal dari kelas akademik yang sama dengan skema penilaian."
            );
        }

        if ($weight <= 0 || $weight > 100) {
            throw new UnprocessableEntityHttpException('Bobot penilaian per komponen harus bernilai antara 0.01% dan 100%.');
        }

        // Calculate potential total weight
        $existingWeight = (float) $scheme->items()
            ->where('assessment_component_id', '!=', $componentId)
            ->sum('weight');

        if (($existingWeight + $weight) > 100.01) {
            throw new UnprocessableEntityHttpException(
                "Total bobot tidak boleh melebihi 100% (total baru: " . ($existingWeight + $weight) . "%)."
            );
        }

        $item = AssessmentSchemeItem::updateOrCreate(
            [
                'assessment_scheme_id' => $scheme->id,
                'assessment_component_id' => $componentId,
            ],
            [
                'weight' => $weight,
            ]
        );

        $scheme->recalculateTotalWeight();

        return $item;
    }

    /**
     * Remove component from an assessment scheme.
     */
    public function removeComponentFromScheme(AssessmentScheme $scheme, int $componentId): void
    {
        $scheme->items()->where('assessment_component_id', $componentId)->delete();
        $scheme->recalculateTotalWeight();
    }

    /**
     * Activate an assessment scheme.
     * Rules: Total weight must equal 100%. Deactivate any other active scheme for the class.
     */
    public function activateScheme(AssessmentScheme $scheme): AssessmentScheme
    {
        $totalWeight = $scheme->recalculateTotalWeight();

        if (abs($totalWeight - 100.00) > 0.01) {
            throw new UnprocessableEntityHttpException(
                "Skema penilaian hanya dapat diaktifkan jika total bobot tepat 100% (total saat ini: {$totalWeight}%)."
            );
        }

        return DB::transaction(function () use ($scheme) {
            // Deactivate existing active schemes for this class
            AssessmentScheme::where('academic_class_id', $scheme->academic_class_id)
                ->where('id', '!=', $scheme->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'status' => SchemeStatus::ARCHIVED,
                ]);

            $scheme->update([
                'status' => SchemeStatus::ACTIVE,
                'is_active' => true,
            ]);

            return $scheme->fresh(['items.component']);
        });
    }

    /**
     * Archive an assessment scheme.
     */
    public function archiveScheme(AssessmentScheme $scheme): AssessmentScheme
    {
        $scheme->update([
            'status' => SchemeStatus::ARCHIVED,
            'is_active' => false,
        ]);

        return $scheme->fresh(['items.component']);
    }

    /**
     * Delete an assessment scheme (if draft).
     */
    public function deleteScheme(AssessmentScheme $scheme): void
    {
        if ($scheme->is_active || $scheme->status === SchemeStatus::ACTIVE) {
            throw new UnprocessableEntityHttpException('Skema penilaian yang sedang aktif tidak dapat dihapus.');
        }

        $scheme->delete();
    }
}
