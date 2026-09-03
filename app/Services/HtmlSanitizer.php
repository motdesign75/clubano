<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'a', 'b', 'blockquote', 'br', 'caption', 'div', 'em', 'figcaption',
        'figure', 'h2', 'h3', 'h4', 'hr', 'i', 'img', 'li', 'ol', 'p',
        'span', 'strong', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead',
        'tr', 'u', 'ul',
    ];

    private const BLOCKED_WITH_CONTENT = [
        'applet', 'base', 'embed', 'form', 'iframe', 'link', 'meta', 'object',
        'script', 'style', 'svg',
    ];

    private const GLOBAL_ATTRIBUTES = [
        'align',
        'colspan',
        'height',
        'rowspan',
        'style',
        'title',
        'width',
    ];

    private const TAG_ATTRIBUTES = [
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt'],
    ];

    public function sanitize(?string $value): ?string
    {
        $value = trim($this->normalize((string) $value));

        if ($value === '') {
            return null;
        }

        if (! $this->containsAllowedHtmlTag($value)) {
            return nl2br(e($value), false);
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div id="clubano-sanitize-root">' . mb_convert_encoding($value, 'HTML-ENTITIES', 'UTF-8') . '</div></body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('clubano-sanitize-root');

        if (! $root instanceof DOMElement) {
            return null;
        }

        $this->cleanNode($root);

        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return trim($html) !== '' ? trim($html) : null;
    }

    private function cleanNode(DOMNode $node): void
    {
        for ($child = $node->firstChild; $child !== null;) {
            $next = $child->nextSibling;

            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if (in_array($tag, self::BLOCKED_WITH_CONTENT, true)) {
                    $child->parentNode?->removeChild($child);
                    $child = $next;
                    continue;
                }

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    $this->unwrapNode($child);
                    $child = $next;
                    continue;
                }

                $this->cleanAttributes($child, $tag);
            }

            if ($child->parentNode !== null) {
                $this->cleanNode($child);
            }

            $child = $next;
        }
    }

    private function containsAllowedHtmlTag(string $value): bool
    {
        $allowed = implode('|', array_map('preg_quote', self::ALLOWED_TAGS));

        return (bool) preg_match('/<\s*\/?\s*(' . $allowed . ')(\s|\/?>)/i', $value);
    }

    public function normalize(string $value): string
    {
        $value = $this->repairMojibake($value);
        $value = str_replace("\xc2\xa0", ' ', $value);

        return preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $value) ?? $value;
    }

    private function repairMojibake(string $value): string
    {
        if (! preg_match('/(?:Ã|Â|â)/u', $value)) {
            return $value;
        }

        $candidate = strtr($value, [
            'Ã„' => 'Ä',
            'Ã–' => 'Ö',
            'Ãœ' => 'Ü',
            'Ã¤' => 'ä',
            'Ã¶' => 'ö',
            'Ã¼' => 'ü',
            'ÃŸ' => 'ß',
            'Ã©' => 'é',
            'Ã¨' => 'è',
            'Ã¡' => 'á',
            'Ã ' => 'à',
            'Ã³' => 'ó',
            'Ã´' => 'ô',
            'Ã§' => 'ç',
            'â€“' => '–',
            'â€”' => '—',
            'â€ž' => '„',
            'â€œ' => '“',
            'â€' => '”',
            'â€˜' => '‘',
            'â€™' => '’',
            'â€¦' => '…',
            'Â ' => ' ',
            'Â«' => '«',
            'Â»' => '»',
            'Â' => '',
        ]);

        $originalArtifacts = $this->mojibakeArtifactCount($value);
        $candidateArtifacts = $this->mojibakeArtifactCount($candidate);

        return $candidateArtifacts < $originalArtifacts ? $candidate : $value;
    }

    private function mojibakeArtifactCount(string $value): int
    {
        return preg_match_all('/(?:Ã.|Â.|â[^\s<]*)/u', $value) ?: 0;
    }

    private function unwrapNode(DOMElement $node): void
    {
        $parent = $node->parentNode;

        if (! $parent) {
            return;
        }

        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }

    private function cleanAttributes(DOMElement $element, string $tag): void
    {
        $allowed = array_merge(self::GLOBAL_ATTRIBUTES, self::TAG_ATTRIBUTES[$tag] ?? []);

        for ($index = $element->attributes->length - 1; $index >= 0; $index--) {
            $attribute = $element->attributes->item($index);
            $name = strtolower($attribute->name);
            $value = trim($attribute->value);

            if (str_starts_with($name, 'on') || ! in_array($name, $allowed, true)) {
                $element->removeAttributeNode($attribute);
                continue;
            }

            if (in_array($name, ['href', 'src'], true) && ! $this->isSafeUrl($value, $name === 'src')) {
                $element->removeAttributeNode($attribute);
                continue;
            }

            if ($name === 'style') {
                $safeStyle = $this->sanitizeStyle($value);

                if ($safeStyle === '') {
                    $element->removeAttributeNode($attribute);
                } else {
                    $element->setAttribute('style', $safeStyle);
                }
            }
        }

        if ($tag === 'a') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function isSafeUrl(string $value, bool $allowImageData): bool
    {
        $normalized = strtolower(preg_replace('/\s+/', '', $value) ?? '');

        if ($normalized === '' || str_starts_with($normalized, '#')) {
            return true;
        }

        if (! $allowImageData && preg_match('/^\{[a-zA-Z0-9_]+\}$/', $normalized)) {
            return true;
        }

        if (preg_match('#^(https?:|mailto:|tel:)#', $normalized)) {
            return true;
        }

        if ($allowImageData && preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#', $normalized)) {
            return true;
        }

        return str_starts_with($normalized, '/');
    }

    private function sanitizeStyle(string $value): string
    {
        $allowedProperties = [
            'background',
            'background-color',
            'border',
            'border-radius',
            'color',
            'display',
            'font-size',
            'font-weight',
            'line-height',
            'margin',
            'margin-bottom',
            'margin-top',
            'padding',
            'text-align',
            'text-decoration',
        ];

        $declarations = collect(explode(';', $value))
            ->map(fn (string $declaration) => trim($declaration))
            ->filter()
            ->map(function (string $declaration) use ($allowedProperties): ?string {
                if (! str_contains($declaration, ':')) {
                    return null;
                }

                [$property, $propertyValue] = array_map('trim', explode(':', $declaration, 2));
                $property = strtolower($property);
                $propertyValue = trim($propertyValue);

                if (! in_array($property, $allowedProperties, true)) {
                    return null;
                }

                if (preg_match('/expression|javascript:|url\s*\(/i', $propertyValue)) {
                    return null;
                }

                return $property . ':' . $propertyValue;
            })
            ->filter()
            ->values();

        return $declarations->implode('; ');
    }
}
