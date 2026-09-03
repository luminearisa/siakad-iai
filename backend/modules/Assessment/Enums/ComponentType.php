<?php

namespace Modules\Assessment\Enums;

enum ComponentType: string
{
    case QUIZ = 'quiz';
    case ASSIGNMENT = 'assignment';
    case MIDTERM = 'midterm';
    case FINAL_EXAM = 'final_exam';
    case PROJECT = 'project';
    case PARTICIPATION = 'participation';
    case PRACTICAL = 'practical';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::QUIZ => 'Kuis',
            self::ASSIGNMENT => 'Tugas / PR',
            self::MIDTERM => 'Ujian Tengah Semester (UTS)',
            self::FINAL_EXAM => 'Ujian Akhir Semester (UAS)',
            self::PROJECT => 'Proyek / Portofolio',
            self::PARTICIPATION => 'Keaktifan / Partisipasi',
            self::PRACTICAL => 'Praktikum',
            self::OTHER => 'Lainnya',
        };
    }
}
