<?php

namespace App\Models;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PublicFormField extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_form_id',
        'label',
        'slug',
        'field_type',
        'help_text',
        'placeholder',
        'options',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function form()
    {
        return $this->belongsTo(PublicForm::class, 'public_form_id');
    }

    public function getRenderedHelpTextAttribute(): ?string
    {
        return self::sanitizeHelpText($this->help_text);
    }

    public static function sanitizeHelpText(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (!preg_match('/<\s*[a-z][^>]*>/i', $value)) {
            return self::renderPlainHelpText($value);
        }

        return self::sanitizeHtmlHelpText($value);
    }

    private static function renderPlainHelpText(string $value): string
    {
        $pattern = '/\[([^\]]+)\]\(([^)]+)\)/';
        $matches = [];
        preg_match_all($pattern, $value, $matches, PREG_OFFSET_CAPTURE);

        if (empty($matches[0])) {
            return nl2br(e($value));
        }

        $result = '';
        $offset = 0;

        foreach ($matches[0] as $index => $fullMatch) {
            [$fullText, $position] = $fullMatch;
            $label = $matches[1][$index][0] ?? '';
            $href = $matches[2][$index][0] ?? '';

            $result .= nl2br(e(substr($value, $offset, $position - $offset)));

            $safeHref = self::normalizeSafeHref($href);

            if ($safeHref === null) {
                $result .= e($fullText);
            } else {
                $result .= sprintf(
                    '<a href="%s"%s rel="noopener noreferrer" class="font-medium text-indigo-600 underline underline-offset-2 hover:text-indigo-700">%s</a>',
                    e($safeHref),
                    self::linkTargetAttribute($safeHref),
                    e($label)
                );
            }

            $offset = $position + strlen($fullText);
        }

        $result .= nl2br(e(substr($value, $offset)));

        return $result;
    }

    private static function sanitizeHtmlHelpText(string $html): string
    {
        $previousUseErrors = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');

        $wrappedHtml = '<div>' . $html . '</div>';
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $wrapper = $dom->getElementsByTagName('div')->item(0);

        if (!$wrapper) {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseErrors);

            return e($html);
        }

        self::sanitizeNode($wrapper);

        $sanitized = '';
        foreach ($wrapper->childNodes as $child) {
            $sanitized .= $dom->saveHTML($child);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        return $sanitized;
    }

    private static function sanitizeNode(DOMNode $node): void
    {
        $allowedTags = ['a', 'strong', 'em', 'b', 'i', 'br', 'p', 'ul', 'ol', 'li'];

        foreach (iterator_to_array($node->childNodes) as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            self::sanitizeNode($child);

            if (!in_array($child->tagName, $allowedTags, true)) {
                self::unwrapNode($child);
                continue;
            }

            if ($child->tagName === 'a') {
                $safeHref = self::normalizeSafeHref($child->getAttribute('href'));

                if ($safeHref === null) {
                    self::unwrapNode($child);
                    continue;
                }

                while ($child->attributes->length > 0) {
                    $child->removeAttributeNode($child->attributes->item(0));
                }

                $child->setAttribute('href', $safeHref);
                $child->setAttribute('rel', 'noopener noreferrer');
                $child->setAttribute('class', 'font-medium text-indigo-600 underline underline-offset-2 hover:text-indigo-700');

                if (self::isExternalHref($safeHref)) {
                    $child->setAttribute('target', '_blank');
                }

                continue;
            }

            while ($child->attributes->length > 0) {
                $child->removeAttributeNode($child->attributes->item(0));
            }
        }
    }

    private static function unwrapNode(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if (!$parent) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private static function normalizeSafeHref(?string $href): ?string
    {
        $href = trim((string) $href);

        if ($href === '') {
            return null;
        }

        if (str_starts_with($href, '#') || str_starts_with($href, '/')) {
            return $href;
        }

        if (preg_match('/^(https?:\/\/|mailto:|tel:)/i', $href) !== 1) {
            return null;
        }

        return $href;
    }

    private static function isExternalHref(string $href): bool
    {
        return str_starts_with($href, 'http://') || str_starts_with($href, 'https://') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:');
    }

    private static function linkTargetAttribute(string $href): string
    {
        return self::isExternalHref($href) ? ' target="_blank"' : '';
    }
}
