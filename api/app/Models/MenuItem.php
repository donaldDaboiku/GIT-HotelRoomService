<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItem extends Model
{
    use BelongsToHotel;

    protected $fillable = [
        'hotel_id',
        'menu_category_id',
        'name',
        'description',
        'price_minor',
        'image_path',
        'is_available',
        'is_sold_out',
        'dietary_info',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'is_sold_out' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }
}
