<?php

namespace App\Http\Resources;

use App\Models\ActivityLog;
use App\Services\Activity\ActivityMessageFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ActivityLog $log */
        $log = $this->resource;

        $formatter = app(ActivityMessageFormatter::class);

        $createdAt = $log->getAttribute('created_at');

        return [
            'id' => $log->getKey(),
            'user_id' => $log->getAttribute('user_id'),
            'action' => (string) $log->getAttribute('action'),
            'target_type' => $log->getAttribute('target_type'),
            'target_id' => $log->getAttribute('target_id'),
            'details' => $log->getAttribute('details'),
            'message' => $formatter->format($log),
            'created_at' => $createdAt?->toISOString(),
        ];
    }
}
