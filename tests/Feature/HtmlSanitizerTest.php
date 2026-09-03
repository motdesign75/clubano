<?php

use App\Services\HtmlSanitizer;
use App\Services\TemplateParser;
use App\Models\Template;
use App\Models\Tenant;

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

test('html sanitizer keeps german umlauts readable in formatted mail text', function () {
    $clean = app(HtmlSanitizer::class)->sanitize('Und wir würden uns freuen, <strong>Sie wieder mit dabei zu haben!</strong>');

    expect($clean)
        ->toContain('würden')
        ->toContain('<strong>Sie wieder mit dabei zu haben!</strong>')
        ->not->toContain('wÃ¼rden');
});

test('html sanitizer keeps safe mail buttons with placeholder links', function () {
    $html = '<p style="margin:24px 0;"><a href="{link}" style="display:inline-block;background:#2954A3;color:#ffffff;text-decoration:none;border-radius:14px;padding:14px 22px;font-weight:700;" onclick="alert(1)">Jetzt öffnen</a></p>';

    $clean = app(HtmlSanitizer::class)->sanitize($html);

    expect($clean)
        ->toContain('href="{link}"')
        ->toContain('style="display:inline-block; background:#2954A3; color:#ffffff; text-decoration:none; border-radius:14px; padding:14px 22px; font-weight:700"')
        ->toContain('rel="noopener noreferrer"')
        ->not->toContain('onclick')
        ->not->toContain('javascript:');
});

test('template parser replaces individual link placeholder from recipient data', function () {
    $html = '<a href="{link}">Antwort öffnen</a>';

    $parsed = TemplateParser::parse($html, [
        'tenant_id' => null,
        'name' => 'Max Mustermann',
        'link' => 'https://clubano.de/einladung/abc',
    ]);

    expect($parsed)->toBe('<a href="https://clubano.de/einladung/abc">Antwort öffnen</a>');
});

test('stored html sanitizer command supports dry run and apply mode', function () {
    $tenant = Tenant::create([
        'name' => 'HTML Verein',
        'slug' => 'html-verein',
        'email' => 'html@example.test',
    ]);

    $template = Template::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Unsichere Vorlage',
        'subject' => 'Update',
        'body' => '<p onclick="alert(1)">Hallo</p><script>alert(2)</script><a href="https://clubano.de">Clubano</a>',
        'type' => Template::TYPE_MAIL,
    ]);

    $this->artisan('clubano:sanitize-stored-html')
        ->assertSuccessful();

    expect($template->refresh()->body)->toContain('<script>');

    $this->artisan('clubano:sanitize-stored-html --apply')
        ->assertSuccessful();

    expect($template->refresh()->body)
        ->toContain('<p>Hallo</p>')
        ->toContain('href="https://clubano.de"')
        ->not->toContain('<script')
        ->not->toContain('onclick');
});
