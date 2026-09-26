<?php

namespace Modules\MBKM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Models\Semester;
use Modules\Audit\Traits\Auditable;
use Modules\Class\Models\AcademicClass;
use Modules\Course\Models\Course;
use Modules\Curriculum\Models\Curriculum;
use Modules\Curriculum\Models\CurriculumSubject;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Enrollment\Models\StudentEnrollmentItem;
use Modules\MBKM\Enums\RecognitionStatus;
use Modules\MBKM\Enums\RecognitionType;

/**
 * Recognition / credit conversion record.
 *
 * One participant activity may map to many courses (one-to-many) and — when
 * institutional policy allows — many activities may map to a single course
 * (many-to-one). The rows here are the single source of truth for the
 * conversion; the academic result is produced by pushing into the existing
 * KRS/enrollment + grade pipeline (see MbkmAcademicIntegrationService).
 */
class MbkmRecognition extends Model
{
    use HasFactory, Auditable;

    protected $table = 'mbkm_recognitions';

    protected $fillable = [
        'participant_id',
        'program_id',
        'activity_log_id',
        'source_label',
        'course_id',
        'curriculum_id',
        'curriculum_subject_id',
        'credits',
        'recognition_type',
        'score',
        'letter_grade',
        'grade_point',
        'semester_id',
        'status',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'review_notes',
        'approved_at',
        'approved_by',
        'locked_at',
        'academic_enrollment_id',
        'academic_enrollment_item_id',
        'academic_class_id',
        'sync_status',
        'sync_message',
        'synced_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecognitionStatus::class,
            'recognition_type' => RecognitionType::class,
            'credits' => 'integer',
            'score' => 'float',
            'grade_point' => 'float',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'locked_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(MbkmParticipant::class, 'participant_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(MbkmProgram::class, 'program_id');
    }

    public function activityLog(): BelongsTo
    {
        return $this->belongsTo(MbkmActivityLog::class, 'activity_log_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function curriculumSubject(): BelongsTo
    {
        return $this->belongsTo(CurriculumSubject::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function academicEnrollment(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollment::class, 'academic_enrollment_id');
    }

    public function academicEnrollmentItem(): BelongsTo
    {
        return $this->belongsTo(StudentEnrollmentItem::class, 'academic_enrollment_item_id');
    }

    public function academicClass(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'academic_class_id');
    }

    public function isLocked(): bool
    {
        $status = $this->status instanceof RecognitionStatus ? $this->status : RecognitionStatus::from($this->status);

        return $status->isLocked();
    }
}
