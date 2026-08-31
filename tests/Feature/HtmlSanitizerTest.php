<?php

use App\Services\HtmlSanitizer;

test('html sanitizer keeps safe formatting and removes executable html', function () {
    $html = <<<'HTML'
        <h2 onclick="alert(1)">Update</h2>
        <p>Hallo <strong>Verein</strong></p>
        <script>alert('xss')</script>
        <a href="javascript:alert(1)" target="_blank">kaputt</a>
        <a href="https://clubano.de/update" onclick="alert(2)">sicher</a>
        <img src="data:text/html;base64,PHNjcmlwdD4=" onerror="alert(3)">
        <img src="data:image/png;base64,iVBORw0KGgo=" alt="Logo">
        <iframe src="https://example.test"></iframe>
    HTML;

    $clean = app(HtmlSanitizer::class)->sanitize($html);

    expect($clean)
        ->toContain('<h2>Update</h2>')
        ->toContain('<strong>Verein</strong>')
        ->toContain('href="https://clubano.de/update"')
        ->toContain('rel="noopener noreferrer"')
        ->toContain('data:image/png;base64,iVBORw0KGgo=')
        ->not->toContain('<script')
        ->not->toContain('<iframe')
        ->not->toContain('onclick')
        ->not->toContain('onerror')
        ->not->toContain('javascript:')
        ->not->toContain('data:text/html');
});

test('html sanitizer turns plain text into safe line break html', function () {
    $clean = app(HtmlSanitizer::class)->sanitize("Hallo <Club>\nNeue Zeile");

    expect($clean)
        ->toBe('Hallo &lt;Club&gt;<br>' . PHP_EOL . 'Neue Zeile');
});
