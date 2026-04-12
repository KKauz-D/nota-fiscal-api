<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Http\Traits\ApiResponse;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::orderByDesc('created_at');

        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }

        $logs = $query->paginate($request->query('per_page', 50));

        return $this->success(AuditLogResource::collection($logs)->response()->getData(true));
    }
}
