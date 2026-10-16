<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    use BelongsToHotel;

    protected $fillable = ['hotel_id', 'request_id', 'path', 'original_name', 'mime', 'size'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(HotelRequest::class, 'request_id');
    }
}
