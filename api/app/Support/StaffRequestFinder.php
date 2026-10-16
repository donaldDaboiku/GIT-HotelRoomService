<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\HotelRequest;
use App\Models\User;

final class StaffRequestFinder
{
    public static function find(User $user, int $id): HotelRequest
    {
        $request = Access::scopeVisible(HotelRequest::query(), $user)->whereKey($id)->first();
        abort_if($request === null, 404);

        return $request;
    }
}
