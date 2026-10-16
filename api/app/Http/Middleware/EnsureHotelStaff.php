<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\HotelStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHotelStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || $user->status !== UserStatus::Active) {
            abort(403, 'This account cannot use the staff desk.');
        }
        if ($user->role === UserRole::PlatformAdmin || $user->hotel_id === null) {
            abort(403, 'This action is not available for this account.');
        }

        $hotel = $user->hotel;
        if ($hotel === null || $hotel->status !== HotelStatus::Active) {
            abort(403, 'This hotel account is not active.');
        }

        return $next($request);
    }
}
