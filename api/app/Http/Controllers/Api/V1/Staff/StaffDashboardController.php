<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\HotelRequest;
use App\Support\Access;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $hotel = $user->hotel;
        $start = now($hotel->timezone)->startOfDay()->utc();
        $terminal = [
            RequestStatus::Completed->value,
            RequestStatus::Delivered->value,
            RequestStatus::Cancelled->value,
            RequestStatus::Rejected->value,
        ];
        $base = Access::scopeVisible(HotelRequest::query(), $user);

        return response()->json([
            'data' => [
                'new_requests' => (clone $base)->where('status', RequestStatus::Submitted->value)->count(),
                'awaiting_assignment' => (clone $base)
                    ->whereIn('type', [RequestType::Service->value, RequestType::Maintenance->value])
                    ->whereIn('status', [RequestStatus::Submitted->value, RequestStatus::Acknowledged->value])
                    ->count(),
                'active_tasks' => (clone $base)->whereIn('status', [
                    RequestStatus::Assigned->value,
                    RequestStatus::Accepted->value,
                    RequestStatus::InProgress->value,
                    RequestStatus::Preparing->value,
                    RequestStatus::ReadyForDelivery->value,
                ])->count(),
                'overdue' => (clone $base)
                    ->whereNotIn('status', $terminal)
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', now())
                    ->count(),
                'completed_today' => (clone $base)
                    ->whereIn('status', [RequestStatus::Completed->value, RequestStatus::Delivered->value])
                    ->where('completed_at', '>=', $start)
                    ->count(),
                'new_orders' => (clone $base)
                    ->where('type', RequestType::Order->value)
                    ->where('status', RequestStatus::Submitted->value)
                    ->count(),
                'open_maintenance' => (clone $base)
                    ->where('type', RequestType::Maintenance->value)
                    ->whereNotIn('status', $terminal)
                    ->count(),
                'role' => $user->role->value,
            ],
        ]);
    }

    public function departments(Request $request): JsonResponse
    {
        $hotel = $request->user()->hotel;

        return response()->json([
            'data' => $hotel->departments()->orderBy('name')->get()->map(fn ($department) => [
                'id' => $department->id,
                'name' => $department->name,
                'code' => $department->code->value,
            ])->values(),
        ]);
    }

    public function assignees(Request $request): JsonResponse
    {
        $data = $request->validate(['department_id' => ['required', 'integer']]);
        $department = $request->user()->hotel->departments()->whereKey($data['department_id'])->first();
        abort_if($department === null, 404);

        $users = $department->users()
            ->where('status', 'active')
            ->whereIn('role', [UserRole::ServiceStaff->value, UserRole::KitchenStaff->value, UserRole::Receptionist->value])
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        return response()->json([
            'data' => $users->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role->value,
            ])->values(),
        ]);
    }
}
