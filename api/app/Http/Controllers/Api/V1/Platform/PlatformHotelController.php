<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Platform;

use App\Enums\HotelStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\HotelRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\HotelProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class PlatformHotelController extends Controller
{
    public function __construct(private readonly HotelProvisioner $provisioner) {}

    public function index(): JsonResponse
    {
        $hotels = Hotel::query()->withCount(['users', 'rooms'])->with('subscription')->orderBy('name')->get();

        return response()->json([
            'data' => $hotels->map(fn (Hotel $hotel) => $this->summary($hotel))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'public_code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'contact_email' => ['nullable', 'email'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:240'],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'string', 'size:3'],
            'plan' => ['nullable', 'string', 'max:40'],
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'unique:users,email'],
            'admin_password' => ['required', 'string', Password::min(10)],
        ]);

        $hotel = $this->provisioner->create($data, $request->user(), $request->ip());
        User::query()->create([
            'name' => $data['admin_name'],
            'email' => strtolower($data['admin_email']),
            'password' => $data['admin_password'],
            'hotel_id' => $hotel->id,
            'role' => UserRole::HotelAdmin,
            'status' => UserStatus::Active,
        ]);

        return response()->json(['data' => $this->summary($hotel->loadCount(['users', 'rooms']))], 201);
    }

    public function status(Request $request, int $hotel): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(HotelStatus::class)],
        ]);
        $model = Hotel::query()->find($hotel);
        abort_if($model === null, 404);
        $model->status = HotelStatus::from($data['status']);
        $model->save();
        if ($model->subscription !== null) {
            $model->subscription->status = $model->status === HotelStatus::Active ? 'active' : 'suspended';
            $model->subscription->save();
        }
        if ($model->status !== HotelStatus::Active) {
            $ids = User::query()->where('hotel_id', $model->id)->pluck('id');
            User::query()->whereIn('id', $ids)->each(fn (User $user) => $user->tokens()->delete());
        }
        AuditLogger::record($request->user(), 'hotel.status', $model, ['status' => $model->status->value], $request->ip(), $model->id);

        return response()->json(['data' => $this->summary($model->loadCount(['users', 'rooms'])->load('subscription'))]);
    }

    public function health(): JsonResponse
    {
        return response()->json([
            'data' => [
                'hotels' => Hotel::query()->count(),
                'active_hotels' => Hotel::query()->where('status', HotelStatus::Active->value)->count(),
                'suspended_hotels' => Hotel::query()->where('status', HotelStatus::Suspended->value)->count(),
                'requests_today' => HotelRequest::query()->where('created_at', '>=', now()->startOfDay())->count(),
                'database' => 'ok',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Hotel $hotel): array
    {
        return [
            'id' => $hotel->id,
            'name' => $hotel->name,
            'public_code' => $hotel->public_code,
            'status' => $hotel->status->value,
            'currency' => $hotel->currency,
            'timezone' => $hotel->timezone,
            'plan' => $hotel->subscription?->plan,
            'subscription_status' => $hotel->subscription?->status,
            'staff_count' => (int) ($hotel->users_count ?? $hotel->users()->count()),
            'room_count' => (int) ($hotel->rooms_count ?? $hotel->rooms()->count()),
            'created_at' => $hotel->created_at?->toIso8601String(),
        ];
    }
}
