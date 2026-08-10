<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    use BelongsToTenant;

    public const TYPE_MAIL = 'mail';
    public const TYPE_LETTER = 'letter';
    public const TYPE_MAIL_AND_LETTER = 'mail_letter';
    public const TYPE_PDF = 'pdf';

    protected $fillable = [
        'tenant_id',
        'name',
        'subject',
        'body',
        'type',
    ];

    public static function typeOptions(): array
    {
        return [
            self::TYPE_MAIL => 'Nur Mail',
            self::TYPE_LETTER => 'Nur Brief',
            self::TYPE_MAIL_AND_LETTER => 'Mail & Brief',
            self::TYPE_PDF => 'PDF / Dokument',
        ];
    }

    public function supportsMail(): bool
    {
        return in_array($this->type, [self::TYPE_MAIL, self::TYPE_MAIL_AND_LETTER], true);
    }

    public function supportsLetter(): bool
    {
        return in_array($this->type, [self::TYPE_LETTER, self::TYPE_MAIL_AND_LETTER], true);
    }

    public function typeLabel(): string
    {
        return self::typeOptions()[$this->type] ?? strtoupper((string) $this->type);
    }
}
