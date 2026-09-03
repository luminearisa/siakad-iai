<?php

namespace Modules\Lecturer\Services;

use Modules\Lecturer\Actions\ChangeLecturerStatusAction;
use Modules\Lecturer\Actions\CreateLecturerAction;
use Modules\Lecturer\Actions\UpdateLecturerAction;
use Modules\Lecturer\Enums\LecturerStatus;
use Modules\Lecturer\Models\Lecturer;

class LecturerService
{
    public function __construct(
        protected CreateLecturerAction $createLecturerAction,
        protected UpdateLecturerAction $updateLecturerAction,
        protected ChangeLecturerStatusAction $changeLecturerStatusAction
    ) {}

    public function create(array $data): Lecturer
    {
        return $this->createLecturerAction->execute($data);
    }

    public function update(Lecturer $lecturer, array $data): Lecturer
    {
        return $this->updateLecturerAction->execute($lecturer, $data);
    }

    public function changeStatus(Lecturer $lecturer, LecturerStatus|string $status, ?string $notes = null): Lecturer
    {
        return $this->changeLecturerStatusAction->execute($lecturer, $status, $notes);
    }
}
