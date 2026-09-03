<?php

namespace Modules\Advising\Actions;

use Modules\Advising\Models\AdvisingSession;
use Modules\Audit\Services\AuditService;

class UpdateAdvisingSessionAction
{
    public function execute(AdvisingSession $session, array $data): AdvisingSession
    {
        $oldValues = $session->toArray();
        $session->update($data);

        AuditService::log(
            action: 'session_updated',
            module: 'Advising',
            description: "Advising session #{$session->id} updated.",
            entity: $session,
            oldValues: $oldValues,
            newValues: $session->fresh()->toArray()
        );

        return $session->fresh(['student', 'lecturer', 'enrollment']);
    }
}
