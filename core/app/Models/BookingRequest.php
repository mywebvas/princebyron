<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookingRequest extends Model
{
    use SoftDeletes;

    const STATUS_NEW       = 'new';
    const STATUS_REVIEWING = 'reviewing';
    const STATUS_QUOTED    = 'quoted';
    const STATUS_ACCEPTED  = 'accepted';
    const STATUS_DECLINED  = 'declined';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'reference',
        'contact_name',
        'contact_email',
        'contact_phone',
        'organisation',
        'event_type',
        'event_name',
        'event_date',
        'event_city',
        'event_state',
        'event_country',
        'expected_audience',
        'event_description',
        'requirements',
        'status',
        'owner_notes',
        'owner_reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'event_date'         => 'date',
            'owner_reviewed_at'  => 'datetime',
            'expected_audience'  => 'integer',
        ];
    }
}
