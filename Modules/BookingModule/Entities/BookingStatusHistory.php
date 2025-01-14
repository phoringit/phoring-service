<?php

namespace Modules\BookingModule\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\UserManagement\Entities\User;

class BookingStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = ['booking_id','changed_by','booking_status','is_guest','booking_repeat_id'];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    protected static function newFactory()
    {
        return \Modules\BookingModule\Database\factories\BookingStatusHistoryFactory::new();
    }
}
