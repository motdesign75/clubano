<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Protocol extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Mass assignable Felder
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'title',
        'type',
        'location',
        'start_time',
        'end_time',
        'raw_agenda',
        'raw_notes',
        'content',

        // 🔥 NEU
        'resolutions',
        'next_meeting',
        'attachments', // JSON Feld
        'archived_at',
    ];

    /**
     * Casts (wichtig für JSON!)
     */
    protected $casts = [
        'attachments' => 'array',
        'archived_at' => 'datetime',
    ];

    /**
     * Zugehöriger Mandant (Verein)
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Ersteller des Protokolls
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Teilnehmer des Protokolls
     */
    public function participants()
    {
        return $this->belongsToMany(Member::class, 'protocol_member')
            ->withTimestamps();
    }

    public function entries()
    {
        return $this->hasMany(ProtocolEntry::class)->orderBy('position')->orderBy('id');
    }

    /**
     * 🔥 OPTIONAL (sehr sinnvoll):
     * Prüft ob Protokoll Anhänge hat
     */
    public function hasAttachments(): bool
    {
        return !empty($this->attachments);
    }

    /**
     * 🔥 OPTIONAL:
     * Gibt alle Attachment-URLs zurück
     */
    public function getAttachmentUrls(): array
    {
        if (!$this->attachments) {
            return [];
        }

        return collect($this->attachments)
            ->map(fn ($file, $index) => route('protocols.attachments.show', ['protocol' => $this, 'index' => $index]))
            ->toArray();
    }
}
