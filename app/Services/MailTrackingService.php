<?php

namespace App\Services;

use App\Models\TemplateDispatchLog;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\URL;

class MailTrackingService
{
    public function instrument(string $html, TemplateDispatchLog $dispatchLog): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $encodedHtml = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

        @$document->loadHTML(
            '<?xml encoding="utf-8" ?><!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div id="tracked-root">' . $encodedHtml . '</div></body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        $root = $document->getElementById('tracked-root');

        if (! $root instanceof DOMElement) {
            return $html;
        }

        foreach ($root->getElementsByTagName('a') as $link) {
            $href = trim((string) $link->getAttribute('href'));

            if (! $this->shouldTrackHref($href)) {
                continue;
            }

            $trackedUrl = URL::signedRoute('mail.tracking.click', [
                'dispatchLog' => $dispatchLog->id,
                'target' => $href,
            ]);

            $link->setAttribute('href', $trackedUrl);
        }

        $pixel = $document->createElement('img');
        $pixel->setAttribute('src', route('mail.tracking.open', $dispatchLog->tracking_token));
        $pixel->setAttribute('alt', '');
        $pixel->setAttribute('width', '1');
        $pixel->setAttribute('height', '1');
        $pixel->setAttribute('style', 'display:block;border:0;outline:none;text-decoration:none;width:1px;height:1px;');
        $pixel->setAttribute('aria-hidden', 'true');

        $root->appendChild($pixel);

        return $this->innerHtml($root);
    }

    private function shouldTrackHref(string $href): bool
    {
        if ($href === '') {
            return false;
        }

        if (str_starts_with($href, '#')
            || str_starts_with($href, 'mailto:')
            || str_starts_with($href, 'tel:')
            || str_starts_with($href, 'javascript:')) {
            return false;
        }

        if (! filter_var($href, FILTER_VALIDATE_URL)) {
            return false;
        }

        return in_array(parse_url($href, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    private function innerHtml(DOMElement $element): string
    {
        $html = '';

        foreach ($element->childNodes as $child) {
            $html .= $element->ownerDocument->saveHTML($child);
        }

        return $html;
    }
}
