<?php

namespace Modules\Curriculum\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Audit\Services\AuditService;
use Modules\Curriculum\Models\CurriculumSemester;
use Modules\Curriculum\Models\CurriculumSubject;

class ManageCurriculumSubjectsAction
{
    /**
     * Add a subject to a curriculum semester.
     *
     * @throws ValidationException
     */
    public function addSubject(CurriculumSemester $semester, array $data): CurriculumSubject
    {
        // Check if course already exists in this semester
        $exists = $semester->subjects()->where('course_id', $data['course_id'])->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'course_id' => ['This course is already added to this curriculum semester.'],
            ]);
        }

        $subject = $semester->subjects()->create($data);

        AuditService::log(
            action: 'subject_added',
            module: 'Curriculum',
            description: "Course #{$subject->course_id} was added to curriculum semester #{$semester->semester_number}.",
            entity: $subject,
            oldValues: null,
            newValues: $subject->toArray()
        );

        return $subject->load('course');
    }

    /**
     * Remove a subject from a curriculum semester.
     */
    public function removeSubject(CurriculumSubject $subject): bool
    {
        $oldValues = $subject->toArray();
        $semesterNumber = $subject->curriculumSemester?->semester_number;
        $courseId = $subject->course_id;

        $subject->delete();

        AuditService::log(
            action: 'subject_removed',
            module: 'Curriculum',
            description: "Course #{$courseId} was removed from curriculum semester #{$semesterNumber}.",
            entity: null,
            oldValues: $oldValues,
            newValues: null
        );

        return true;
    }
}
