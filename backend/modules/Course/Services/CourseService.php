<?php

namespace Modules\Course\Services;

use Modules\Course\Actions\CreateCourseAction;
use Modules\Course\Actions\SetCoursePrerequisitesAction;
use Modules\Course\Actions\UpdateCourseAction;
use Modules\Course\Models\Course;

class CourseService
{
    public function __construct(
        protected CreateCourseAction $createCourseAction,
        protected UpdateCourseAction $updateCourseAction,
        protected SetCoursePrerequisitesAction $setCoursePrerequisitesAction
    ) {}

    public function create(array $data): Course
    {
        return $this->createCourseAction->execute($data);
    }

    public function update(Course $course, array $data): Course
    {
        return $this->updateCourseAction->execute($course, $data);
    }

    public function setPrerequisites(Course $course, array $prerequisites): Course
    {
        return $this->setCoursePrerequisitesAction->execute($course, $prerequisites);
    }
}
