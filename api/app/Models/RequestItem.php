<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestItem extends Model
{
    use BelongsToHotel;

    protected $fillable = [
        'request_id',
        'hotel_id',
        'menu_item_id',
        'service_id',
        'name',
        'unit_price_minor',
        'quantity',
        'line_total_minor',
        'notes',
        'is_unavailable',
    ];

    protected function casts(): array
    {
        return [
            'is_unavailable' => 'boolean',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(HotelRequest::class, 'request_id');
    }
}
