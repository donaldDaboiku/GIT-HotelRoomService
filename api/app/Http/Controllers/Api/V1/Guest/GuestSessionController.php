<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Guest;

use App\Enums\HotelStatus;
use App\Enums\QrTokenStatus;
use App\Http\Controllers\Controller;
use App\Models\GuestAccessToken;
use App\Models\Hotel;
use App\Models\RoomQrToken;
use App\Support\GuestView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestSessionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hotel_code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'room_code' => ['required', 'string', 'regex:/^[A-Za-z0-9]{16,64}$/'],
        ]);

        $hotel = Hotel::query()->where('public_code', strtolower($data['hotel_code']))->first();
        $roomCode = strtolower($data['room_code']);
        if ($hotel === null || $hotel->status !== HotelStatus::Active) {
            abort(404, 'Room link not found.');
        }

        $qr = RoomQrToken::query()
            ->with('room')
            ->where('hotel_id', $hotel->id)
            ->where('public_code', $roomCode)
            ->first();

        if ($qr === null) {
            abort(404, 'Room link not found.');
        }
        if ($qr->status !== QrTokenStatus::Active || ! $qr->room->is_active) {
            abort(404, 'This room link is no longer active.');
        }

        $plain = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        GuestAccessToken::query()->create([
            'hotel_id' => $hotel->id,
            'room_id' => $qr->room_id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays(max(1, (int) config('hotel.guest_session_days'))),
        ]);

        return response()->json([
            'data' => [
                'token' => $plain,
                'expires_at' => now()->addDays(max(1, (int) config('hotel.guest_session_days')))->toIso8601String(),
                ...GuestView::place($hotel, $qr->room),
            ],
        ], 201);
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => GuestView::place($request->attributes->get('guestHotel'), $request->attributes->get('guestRoom')),
        ]);
    }
}
