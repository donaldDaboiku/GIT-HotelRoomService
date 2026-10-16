<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DepartmentCode;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use BelongsToHotel;

    protected $fillable = ['hotel_id', 'name', 'code'];

    protected function casts(): array
    {
        return [
            'code' => DepartmentCode::class,
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
