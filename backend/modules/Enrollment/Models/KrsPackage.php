<?php

namespace Modules\Enrollment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academic\Models\StudyProgram;

class KrsPackage extends Model
{
    use HasFactory;

    protected $table = 'krs_packages';

    protected $fillable = [
        'name',
        'study_program_id',
        'semester_level', // Tingkat semester mahasiswa (1–14), bukan FK ke tabel semesters
        'total_credits',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'semester_level' => 'integer',
            'total_credits' => 'integer',
        ];
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(KrsPackageItem::class, 'krs_package_id');
    }
}
