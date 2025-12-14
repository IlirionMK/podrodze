<?php

namespace App\Services\Activity;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    public function add($actor, string $action, $target = null, array $details = []): void
    {
        $userId = ($actor instanceof Model) ? $actor->getKey() : null;

        $targetType = is_string($target)
            ? $target
            : (($target instanceof Model) ? $target->getMorphClass() : null);

        $targetId = ($target instanceof Model) ? $target->getKey() : null;

        ActivityLog::create([
            'user_id' => $userId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'details' => $details,
        ]);
    }
}
