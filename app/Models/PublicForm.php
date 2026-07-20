<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PublicForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'title',
        'slug',
        'description',
        'form_type',
        'success_message',
        'confirmation_mail_enabled',
        'confirmation_mail_subject',
        'confirmation_mail_body',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'confirmation_mail_enabled' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function fields()
    {
        return $this->hasMany(PublicFormField::class)->orderBy('sort_order');
    }

    public function submissions()
    {
        return $this->hasMany(PublicFormSubmission::class)->latest();
    }
}
