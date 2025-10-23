<?php

namespace App\Helpers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class LogActivity
{
    public static function add($actor, string $action, $target = null, array $details = []): void
    {
        $userId   = ($actor instanceof Model)  ? $actor->getKey() : null;
        $tType    = is_string($target) ? $target : (($target instanceof Model) ? $target->getMorphClass() : null);
        $tId      = ($target instanceof Model) ? $target->getKey() : null;

        ActivityLog::create([
            'user_id'     => $userId,
            'action'      => $action,
            'target_type' => $tType,
            'target_id'   => $tId,
            'details'     => $details,
        ]);
    }
}
