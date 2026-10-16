<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function record(
        ?User $user,
        string $action,
        ?Model $subject = null,
        array $metadata = [],
        ?string $ip = null,
        ?int $hotelId = null,
    ): void {
        $resolvedHotelId = $hotelId ?? $user?->hotel_id;
        if ($resolvedHotelId === null && $subject !== null && isset($subject->hotel_id)) {
            $resolvedHotelId = $subject->hotel_id;
        }

        AuditLog::query()->create([
            'hotel_id' => $resolvedHotelId,
            'user_id' => $user?->id,
            'action' => $action,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
            'ip' => $ip,
            'created_at' => now(),
        ]);
    }
}
