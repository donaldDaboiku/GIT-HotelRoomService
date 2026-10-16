<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Hotel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToHotel
{
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function scopeForHotel(Builder $query, int $hotelId): Builder
    {
        return $query->where($this->getTable().'.hotel_id', $hotelId);
    }
}
