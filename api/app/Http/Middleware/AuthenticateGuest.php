<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\HotelStatus;
use App\Enums\QrTokenStatus;
use App\Models\GuestAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateGuest
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = (string) $request->header('X-Guest-Token', '');
        if (strlen($plain) < 40) {
            abort(401, 'Guest session is missing or invalid.');
        }

        $token = GuestAccessToken::query()->where('token_hash', hash('sha256', $plain))->first();
        if ($token === null || $token->expires_at->isPast()) {
            abort(401, 'Guest session is missing or invalid.');
        }

        $hotel = $token->hotel()->with('settings')->first();
        $room = $token->room;
        $activeQr = $room?->qrTokens()->where('status', QrTokenStatus::Active->value)->exists();
        if (
            $hotel === null
            || $hotel->status !== HotelStatus::Active
            || $room === null
            || ! $room->is_active
            || ! $activeQr
        ) {
            abort(404, 'This room link is no longer active.');
        }

        if ($token->last_seen_at === null || $token->last_seen_at->lt(now()->subMinutes(5))) {
            $token->forceFill(['last_seen_at' => now()])->save();
        }

        $request->attributes->set('guestToken', $token);
        $request->attributes->set('guestHotel', $hotel);
        $request->attributes->set('guestRoom', $room);

        return $next($request);
    }
}
