<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestNote extends Model
{
    use BelongsToHotel;

    protected $table = 'request_internal_notes';

    protected $fillable = ['request_id', 'hotel_id', 'user_id', 'body', 'is_guest_visible'];

    protected function casts(): array
    {
        return [
            'is_guest_visible' => 'boolean',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
