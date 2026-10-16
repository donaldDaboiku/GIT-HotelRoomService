<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestAssignment extends Model
{
    use BelongsToHotel;

    protected $fillable = [
        'request_id',
        'hotel_id',
        'department_id',
        'assigned_to_user_id',
        'assigned_by_user_id',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(HotelRequest::class, 'request_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
