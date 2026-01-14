<?php

namespace App\Services\Activity;

use App\Models\ActivityLog;

class ActivityMessageFormatter
{
    public function format(ActivityLog $log): string
    {
        $action = (string) $log->getAttribute('action');
        $details = (array) ($log->getAttribute('details') ?? []);

        return match ($action) {
            'admin.user.role_updated' => $this->roleUpdated($log, $details),
            'admin.user.ban_updated' => $this->banUpdated($log, $details),
            default => $this->fallback($action, $details),
        };
    }

    private function roleUpdated(ActivityLog $log, array $details): string
    {
        $targetId = $log->getAttribute('target_id');
        $before = $details['before'] ?? null;
        $after = $details['after'] ?? null;

        if ($targetId && $before !== null && $after !== null) {
            return "Admin changed user #{$targetId} role from '{$before}' to '{$after}'.";
        }

        return 'Admin updated user role.';
    }

    private function banUpdated(ActivityLog $log, array $details): string
    {
        $targetId = $log->getAttribute('target_id');
        $after = $details['after'] ?? null;

        if ($targetId && is_bool($after)) {
            return $after
                ? "Admin banned user #{$targetId}."
                : "Admin unbanned user #{$targetId}.";
        }

        return 'Admin updated user ban status.';
    }

    private function fallback(string $action, array $details): string
    {
        if ($details !== []) {
            return $action . ' ' . json_encode($details, JSON_UNESCAPED_UNICODE);
        }

        return $action;
    }
}
