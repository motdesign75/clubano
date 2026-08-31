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
        'title',
        'width',
    ];

    private const TAG_ATTRIBUTES = [
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt'],
    ];

    public function sanitize(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! $this->containsAllowedHtmlTag($value)) {
            return nl2br(e($value), false);
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<!DOCTYPE html><html><body><div id="clubano-sanitize-root">' . mb_convert_encoding($value, 'HTML-ENTITIES', 'UTF-8') . '</div></body></html>',
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

        if (preg_match('#^(https?:|mailto:|tel:)#', $normalized)) {
            return true;
        }

        if ($allowImageData && preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#', $normalized)) {
            return true;
        }

        return str_starts_with($normalized, '/');
    }
}
