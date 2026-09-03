<?php

namespace Modules\Graduation\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Student\Models\Student;
use Modules\Thesis\Models\Thesis;

class YudisiumParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'yudisium_period_id',
        'student_id',
        'application_date',
        'status',
        'rejection_reason',
        'notes',
        'total_credits',
        'gpa',
        'study_duration_days',
        'thesis_id',
        'sk_number',
        'sk_date',
        'is_certificate_taken',
        'certificate_taken_at',
        'certificate_taken_by',
    ];

    protected $casts = [
        'application_date' => 'date:Y-m-d',
        'sk_date' => 'date:Y-m-d',
        'certificate_taken_at' => 'datetime',
        'is_certificate_taken' => 'boolean',
        'total_credits' => 'integer',
        'gpa' => 'decimal:2',
        'study_duration_days' => 'integer',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(YudisiumPeriod::class, 'yudisium_period_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function thesis(): BelongsTo
    {
        return $this->belongsTo(Thesis::class);
    }

    public function documentSubmissions(): HasMany
    {
        return $this->hasMany(YudisiumDocumentSubmission::class);
    }
}
