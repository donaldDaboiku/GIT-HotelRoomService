<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HotelStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Hotel extends Model
{
    protected $fillable = [
        'name',
        'public_code',
        'logo_path',
        'contact_phone',
        'contact_email',
        'address',
        'currency',
        'timezone',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => HotelStatus::class,
        ];
    }

    public function settings(): HasOne
    {
        return $this->hasOne(HotelSetting::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(HotelSubscription::class);
    }

    public function responseTargets(): HasMany
    {
        return $this->hasMany(ResponseTarget::class);
    }
}
