<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PublicFormSubmission extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'public_form_id',
        'tenant_id',
        'event_id',
        'event_booking_id',
        'member_id',
        'contact_id',
        'full_name',
        'email',
        'phone',
        'status',
        'cancelled_at',
        'answers',
    ];

    protected $casts = [
        'answers' => 'array',
        'cancelled_at' => 'datetime',
    ];

    public function form()
    {
        return $this->belongsTo(PublicForm::class, 'public_form_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function eventBooking()
    {
        return $this->belongsTo(EventBooking::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}
