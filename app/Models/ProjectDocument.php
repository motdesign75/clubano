<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class ProjectDocument extends Model
{
    use BelongsToTenant;
    use HasUlids;

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'tenant_id',
        'project_id',
        'user_id',
        'disk',
        'path',
        'original_name',
        'size',
        'mime_type',
    ];

    // Beziehungen
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Geschützte Download-URL über die Anwendung statt öffentliche Storage-Links.
    public function getUrlAttribute(): ?string
    {
        if (!$this->project_id || !$this->getKey()) {
            return null;
        }

        return route('projects.documents.download', [
            'project' => $this->project_id,
            'document' => $this->getKey(),
        ]);
    }
}
