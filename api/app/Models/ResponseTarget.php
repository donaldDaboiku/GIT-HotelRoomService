<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RequestType;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;

class ResponseTarget extends Model
{
    use BelongsToHotel;

    protected $fillable = ['hotel_id', 'request_type', 'target_minutes'];

    protected function casts(): array
    {
        return [
            'request_type' => RequestType::class,
        ];
    }
}
