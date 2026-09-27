<?php

namespace Modules\Attendance\Resources;

use Illuminate\Http\Request;

/**
 * Session row as it appears inside a class recap.
 *
 * The recap answers "how many meetings were held and who attended", so the check-in
 * token has no business being in the payload — even for the lecturer who is allowed
 * to see it on the session screen. Recaps end up in shared screens, screenshots and
 * proxy logs far more often than a single session detail does, and a leaked token
 * lets someone check in without being in the room.
 */
class TeachingSessionRecapResource extends TeachingSessionResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        unset($data['check_in_code'], $data['check_in_expires_at']);

        return $data;
    }
}
