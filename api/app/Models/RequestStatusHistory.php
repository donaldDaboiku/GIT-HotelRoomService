<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestStatusHistory extends Model
{
    use BelongsToHotel;

    public const UPDATED_AT = null;

    protected $fillable = [
        'request_id',
        'hotel_id',
        'from_status',
        'to_status',
        'changed_by_user_id',
        'note',
        'guest_visible',
    ];

    protected function casts(): array
    {
        return [
            'guest_visible' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
