<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuestAccessToken extends Model
{
    use BelongsToHotel;

    protected $fillable = ['hotel_id', 'room_id', 'token_hash', 'expires_at', 'last_seen_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(HotelRequest::class);
    }
}
