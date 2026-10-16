<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QrTokenStatus;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Room extends Model
{
    use BelongsToHotel;

    protected $fillable = ['hotel_id', 'number', 'floor', 'room_type', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function qrTokens(): HasMany
    {
        return $this->hasMany(RoomQrToken::class);
    }

    public function activeQr(): HasOne
    {
        return $this->hasOne(RoomQrToken::class)->where('status', QrTokenStatus::Active->value);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(HotelRequest::class);
    }
}
