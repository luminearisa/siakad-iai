<?php

namespace Modules\Schedule\Services;

use Modules\Schedule\Actions\CreateScheduleAction;
use Modules\Schedule\Actions\UpdateScheduleAction;
use Modules\Schedule\Models\ClassSchedule;

class ScheduleService
{
    public function __construct(
        protected CreateScheduleAction $createScheduleAction,
        protected UpdateScheduleAction $updateScheduleAction,
        protected ScheduleConflictService $scheduleConflictService
    ) {}

    public function create(array $data): ClassSchedule
    {
        return $this->createScheduleAction->execute($data);
    }

    public function update(ClassSchedule $schedule, array $data): ClassSchedule
    {
        return $this->updateScheduleAction->execute($schedule, $data);
    }

    public function getConflictService(): ScheduleConflictService
    {
        return $this->scheduleConflictService;
    }
}
