<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Services\Admin\AdminActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminActivityLogController extends Controller
{
    public function __construct(
        private readonly AdminActivityLogService $service
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min(200, $perPage));

        $filters = [
            'search' => (string) $request->query('search', ''),
            'user_id' => $request->query('user_id'),
            'action' => (string) $request->query('action', ''),
            'level' => (string) $request->query('level', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
        ];

        $logs = $this->service->paginate($filters, $perPage);

        return ActivityLogResource::collection($logs);
    }
}
