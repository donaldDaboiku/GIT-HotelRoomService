<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QrTokenStatus;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomQrToken extends Model
{
    use BelongsToHotel;

    protected $fillable = ['hotel_id', 'room_id', 'public_code', 'status', 'revoked_at'];

    protected function casts(): array
    {
        return [
            'status' => QrTokenStatus::class,
            'revoked_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
