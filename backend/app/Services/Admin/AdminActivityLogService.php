<?php

namespace App\Services\Admin;

use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminActivityLogService
{
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $table = (new ActivityLog())->getTable();
        $query = ActivityLog::query();

        $this->applyDateRange($query, $filters, $table);
        $this->applyExactFilters($query, $filters, $table);
        $this->applySearch($query, $filters, $table);

        $orderColumn = Schema::hasColumn($table, 'id') ? 'id' : 'created_at';
        $query->orderByDesc($orderColumn);

        return $query->paginate($perPage);
    }

    private function applyDateRange(Builder $query, array $filters, string $table): void
    {
        if (!Schema::hasColumn($table, 'created_at')) {
            return;
        }

        $from = isset($filters['from']) ? trim((string) $filters['from']) : '';
        $to = isset($filters['to']) ? trim((string) $filters['to']) : '';

        if ($from !== '') {
            $query->where('created_at', '>=', $from);
        }
        if ($to !== '') {
            $query->where('created_at', '<=', $to);
        }
    }

    private function applyExactFilters(Builder $query, array $filters, string $table): void
    {
        $userId = isset($filters['user_id']) ? (int) $filters['user_id'] : null;
        if ($userId && Schema::hasColumn($table, 'user_id')) {
            $query->where('user_id', $userId);
        }

        $action = isset($filters['action']) ? trim((string) $filters['action']) : '';
        if ($action !== '' && Schema::hasColumn($table, 'action')) {
            $query->where('action', $action);
        }
    }

    private function applySearch(Builder $query, array $filters, string $table): void
    {
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search === '') {
            return;
        }

        $driver = DB::connection()->getDriverName();
        $like = "%$search%";

        $query->where(function ($q) use ($table, $driver, $like) {
            if (Schema::hasColumn($table, 'action')) {
                $q->orWhere('action', 'like', $like);
            }

            if (Schema::hasColumn($table, 'target_type')) {
                $q->orWhere('target_type', 'like', $like);
            }

            if (Schema::hasColumn($table, 'details')) {
                if ($driver === 'pgsql') {
                    $q->orWhereRaw('details::text ILIKE ?', [$like]);
                } elseif ($driver === 'mysql') {
                    $q->orWhereRaw('CAST(details AS CHAR) LIKE ?', [$like]);
                } else {
                    $q->orWhere('details', 'like', $like);
                }
            }
        });
    }
}
