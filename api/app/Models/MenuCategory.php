<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuCategory extends Model
{
    use BelongsToHotel;

    protected $fillable = ['hotel_id', 'name', 'sort_order', 'is_available'];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }
}
