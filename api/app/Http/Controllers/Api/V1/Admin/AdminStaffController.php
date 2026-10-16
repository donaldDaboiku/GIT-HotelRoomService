<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\DepartmentCode;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Staff\StaffAuthController;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AdminStaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with('department')
            ->where('hotel_id', $request->user()->hotel_id)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $users->map(fn (User $user) => StaffAuthController::profile($user))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = User::query()->create($this->data($request, null));
        AuditLogger::record($request->user(), 'staff.created', $user, ['role' => $user->role->value], $request->ip(), $user->hotel_id);

        return response()->json(['data' => StaffAuthController::profile($user->load('department'))], 201);
    }

    public function update(Request $request, int $user): JsonResponse
    {
        $model = $this->staff($request, $user);
        $payload = $this->data($request, $model, false);
        $model->fill($payload)->save();
        AuditLogger::record($request->user(), 'staff.updated', $model, ['role' => $model->role->value], $request->ip(), $model->hotel_id);

        return response()->json(['data' => StaffAuthController::profile($model->load('department'))]);
    }

    public function password(Request $request, int $user): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string', Password::min(10)]]);
        $model = $this->staff($request, $user);
        $model->forceFill(['password' => $data['password']])->save();
        $model->tokens()->delete();
        AuditLogger::record($request->user(), 'staff.password_reset', $model, [], $request->ip(), $model->hotel_id);

        return response()->json(['data' => ['reset' => true]]);
    }

    public function status(Request $request, int $user): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(UserStatus::class)]]);
        $model = $this->staff($request, $user);
        if ((int) $model->id === (int) $request->user()->id) {
            abort(422, 'You cannot change your own account status.');
        }
        $model->status = UserStatus::from($data['status']);
        $model->save();
        if ($model->status !== UserStatus::Active) {
            $model->tokens()->delete();
        }
        AuditLogger::record($request->user(), 'staff.status', $model, ['status' => $model->status->value], $request->ip(), $model->hotel_id);

        return response()->json(['data' => StaffAuthController::profile($model->load('department'))]);
    }

    public function audit(Request $request): JsonResponse
    {
        $rows = \App\Models\AuditLog::query()
            ->with('user:id,name')
            ->where('hotel_id', $request->user()->hotel_id)
            ->latest('created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($row) => [
                'id' => $row->id,
                'action' => $row->action,
                'by' => $row->user?->name,
                'metadata' => $row->metadata,
                'ip' => $row->ip,
                'created_at' => $row->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function data(Request $request, ?User $existing, bool $withPassword = true): array
    {
        $hotelId = $request->user()->hotel_id;
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($existing?->id)],
            'role' => ['required', Rule::in([
                UserRole::HotelAdmin->value,
                UserRole::Receptionist->value,
                UserRole::ServiceStaff->value,
                UserRole::KitchenStaff->value,
            ])],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('hotel_id', $hotelId)],
        ];
        if ($withPassword) {
            $rules['password'] = ['required', 'string', Password::min(10)];
        }
        $data = $request->validate($rules);
        $role = UserRole::from($data['role']);
        $departmentId = $data['department_id'] ?? null;
        $department = $departmentId ? $request->user()->hotel->departments()->whereKey($departmentId)->first() : null;

        $expected = match ($role) {
            UserRole::HotelAdmin => null,
            UserRole::Receptionist => DepartmentCode::Reception,
            UserRole::KitchenStaff => DepartmentCode::Kitchen,
            UserRole::ServiceStaff => 'service',
            default => null,
        };

        if ($expected === null && $departmentId !== null) {
            throw ValidationException::withMessages(['department_id' => 'Hotel administrators are not placed in a department.']);
        }
        if ($expected instanceof DepartmentCode && ($department === null || $department->code !== $expected)) {
            throw ValidationException::withMessages(['department_id' => 'Choose the '.$expected->label().' department for this role.']);
        }
        if ($expected === 'service' && ($department === null || ! in_array($department->code, [DepartmentCode::Housekeeping, DepartmentCode::Laundry, DepartmentCode::Maintenance], true))) {
            throw ValidationException::withMessages(['department_id' => 'Service staff must belong to housekeeping, laundry, or maintenance.']);
        }

        return [
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'role' => $role,
            'department_id' => $role === UserRole::HotelAdmin ? null : $departmentId,
            'hotel_id' => $hotelId,
            'status' => $existing?->status ?? UserStatus::Active,
            ...($withPassword ? ['password' => $data['password']] : []),
        ];
    }

    private function staff(Request $request, int $id): User
    {
        $user = User::query()->where('hotel_id', $request->user()->hotel_id)->whereKey($id)->first();
        abort_if($user === null, 404);

        return $user;
    }
}
