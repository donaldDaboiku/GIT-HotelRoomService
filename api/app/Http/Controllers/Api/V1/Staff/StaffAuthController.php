<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\HotelStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StaffAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', strtolower($data['email']))->first();
        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }
        if ($user->status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'email' => 'This account is suspended.',
            ]);
        }
        if ($user->role !== UserRole::PlatformAdmin) {
            $hotel = $user->hotel;
            if ($hotel === null || $hotel->status !== HotelStatus::Active) {
                throw ValidationException::withMessages([
                    'email' => 'This hotel account is not active.',
                ]);
            }
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken('staff', ['*'], now()->addHours(12))->plainTextToken;
        AuditLogger::record($user, 'auth.login', $user, [], $request->ip());

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => $this->profile($user),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        if ($token !== null) {
            $token->delete();
        }
        AuditLogger::record($user, 'auth.logout', $user, [], $request->ip());

        return response()->json(['data' => ['logged_out' => true]]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->profile($request->user())]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function profile(User $user): array
    {
        $user->loadMissing(['hotel', 'department']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'status' => $user->status->value,
            'department' => $user->department === null ? null : [
                'id' => $user->department->id,
                'name' => $user->department->name,
                'code' => $user->department->code->value,
            ],
            'hotel' => $user->hotel === null ? null : [
                'id' => $user->hotel->id,
                'name' => $user->hotel->name,
                'public_code' => $user->hotel->public_code,
                'currency' => $user->hotel->currency,
                'timezone' => $user->hotel->timezone,
                'status' => $user->hotel->status->value,
            ],
        ];
    }
}
