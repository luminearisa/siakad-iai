<?php

namespace Modules\Advising\Actions;

use Modules\Advising\Enums\AdvisingSessionStatus;
use Modules\Advising\Models\AdvisingSession;
use Modules\Audit\Services\AuditService;

class CreateAdvisingSessionAction
{
    public function execute(array $data): AdvisingSession
    {
        if (!isset($data['status'])) {
            $data['status'] = AdvisingSessionStatus::COMPLETED;
        }

        $session = AdvisingSession::create($data);

        AuditService::log(
            action: 'session_created',
            module: 'Advising',
            description: "Advising session logged for student #{$session->student_id} with lecturer #{$session->lecturer_id}.",
            entity: $session,
            oldValues: null,
            newValues: $session->toArray()
        );

        return $session->load(['student', 'lecturer', 'enrollment']);
    }
}
