<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Audit\Traits\Auditable;
use Modules\Course\Models\Course;
use Modules\Curriculum\Models\CurriculumSubject;

class MbkmLearningAgreementItem extends Model
{
    use HasFactory, Auditable;

    protected $table = 'mbkm_learning_agreement_items';

    protected $fillable = [
        'learning_agreement_id',
        'activity_title',
        'activity_description',
        'course_id',
        'curriculum_subject_id',
        'credits',
        'target_grade',
        'sort_order',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'credits' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function learningAgreement(): BelongsTo
    {
        return $this->belongsTo(MbkmLearningAgreement::class, 'learning_agreement_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function curriculumSubject(): BelongsTo
    {
        return $this->belongsTo(CurriculumSubject::class);
    }
}
