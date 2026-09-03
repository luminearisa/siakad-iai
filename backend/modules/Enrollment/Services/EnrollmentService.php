<?php

namespace Modules\Enrollment\Services;

use Modules\Enrollment\Actions\AddEnrollmentItemAction;
use Modules\Enrollment\Actions\ApproveEnrollmentAction;
use Modules\Enrollment\Actions\CreateEnrollmentAction;
use Modules\Enrollment\Actions\LockEnrollmentAction;
use Modules\Enrollment\Actions\RejectEnrollmentAction;
use Modules\Enrollment\Actions\RemoveEnrollmentItemAction;
use Modules\Enrollment\Actions\RequestRevisionAction;
use Modules\Enrollment\Actions\SubmitEnrollmentAction;
use Modules\Enrollment\Models\StudentEnrollment;
use Modules\Enrollment\Models\StudentEnrollmentItem;

class EnrollmentService
{
    public function __construct(
        protected CreateEnrollmentAction $createEnrollmentAction,
        protected AddEnrollmentItemAction $addEnrollmentItemAction,
        protected RemoveEnrollmentItemAction $removeEnrollmentItemAction,
        protected SubmitEnrollmentAction $submitEnrollmentAction,
        protected ApproveEnrollmentAction $approveEnrollmentAction,
        protected RejectEnrollmentAction $rejectEnrollmentAction,
        protected RequestRevisionAction $requestRevisionAction,
        protected LockEnrollmentAction $lockEnrollmentAction
    ) {}

    public function create(array $data): StudentEnrollment
    {
        return $this->createEnrollmentAction->execute($data);
    }

    public function addItem(StudentEnrollment $enrollment, int $classId, ?string $notes = null): StudentEnrollmentItem
    {
        return $this->addEnrollmentItemAction->execute($enrollment, $classId, $notes);
    }

    public function removeItem(StudentEnrollment $enrollment, StudentEnrollmentItem $item): bool
    {
        return $this->removeEnrollmentItemAction->execute($enrollment, $item);
    }

    public function submit(StudentEnrollment $enrollment): StudentEnrollment
    {
        return $this->submitEnrollmentAction->execute($enrollment);
    }

    public function approve(StudentEnrollment $enrollment, int $approverUserId, ?string $notes = null): StudentEnrollment
    {
        return $this->approveEnrollmentAction->execute($enrollment, $approverUserId, $notes);
    }

    public function reject(StudentEnrollment $enrollment, int $userId, string $reason): StudentEnrollment
    {
        return $this->rejectEnrollmentAction->execute($enrollment, $userId, $reason);
    }

    public function requestRevision(StudentEnrollment $enrollment, int $userId, string $revisionNotes): StudentEnrollment
    {
        return $this->requestRevisionAction->execute($enrollment, $userId, $revisionNotes);
    }

    public function lock(StudentEnrollment $enrollment, int $userId): StudentEnrollment
    {
        return $this->lockEnrollmentAction->execute($enrollment, $userId);
    }
}
