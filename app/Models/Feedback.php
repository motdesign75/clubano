<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Feedback extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'view',
        'url',
        'page_title',
        'device_label',
        'viewport',
        'user_agent',
        'message',
        'screenshot_path',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getScreenshotUrlAttribute(): ?string
    {
        return $this->screenshot_path ? Storage::disk('public')->url($this->screenshot_path) : null;
    }
}
