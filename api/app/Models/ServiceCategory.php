<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServiceKind;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCategory extends Model
{
    use BelongsToHotel;

    protected $fillable = ['hotel_id', 'name', 'kind', 'sort_order'];

    protected function casts(): array
    {
        return [
            'kind' => ServiceKind::class,
        ];
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
