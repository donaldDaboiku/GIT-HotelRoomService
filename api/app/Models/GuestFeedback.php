<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestFeedback extends Model
{
    use BelongsToHotel;

    protected $table = 'guest_feedback';

    protected $fillable = ['request_id', 'hotel_id', 'rating', 'comment', 'category'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(HotelRequest::class, 'request_id');
    }
}
