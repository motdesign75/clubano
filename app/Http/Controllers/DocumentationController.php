<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

class DocumentationController extends Controller
{
    /**
     * @return array<string, array{title:string, path:string, group:string}>
     */
    protected function pages(): array
    {
        return [
            '' => [
                'title' => 'Überblick',
                'path' => base_path('docs/README.md'),
                'group' => 'Start',
            ],
            'handbuch/01-startcenter-und-schnellstart' => [
                'title' => 'Schnellstart & Startcenter',
                'path' => base_path('docs/handbuch/01-startcenter-und-schnellstart.md'),
                'group' => 'Handbuch',
            ],
            'handbuch/02-verein-und-benutzer' => [
                'title' => 'Verein, Benutzer & Grundeinrichtung',
                'path' => base_path('docs/handbuch/02-verein-und-benutzer.md'),
                'group' => 'Handbuch',
            ],
            'handbuch/03-mitglieder-und-kommunikation' => [
                'title' => 'Mitglieder & Kommunikation',
                'path' => base_path('docs/handbuch/03-mitglieder-und-kommunikation.md'),
                'group' => 'Handbuch',
            ],
            'handbuch/04-veranstaltungen-und-dienstplan' => [
                'title' => 'Veranstaltungen & Dienstplan',
                'path' => base_path('docs/handbuch/04-veranstaltungen-und-dienstplan.md'),
                'group' => 'Handbuch',
            ],
            'handbuch/05-formulare-vorlagen-und-versand' => [
                'title' => 'Formulare, Vorlagen, Mail & Brief',
                'path' => base_path('docs/handbuch/05-formulare-vorlagen-und-versand.md'),
                'group' => 'Handbuch',
            ],
            'handbuch/06-finanzen-und-rechnungen' => [
                'title' => 'Finanzen, Buchungen & Rechnungen',
                'path' => base_path('docs/handbuch/06-finanzen-und-rechnungen.md'),
                'group' => 'Handbuch',
            ],
            'admin/01-betrieb-deployment-backups' => [
                'title' => 'Betrieb, Deployment & Backups',
                'path' => base_path('docs/admin/01-betrieb-deployment-backups.md'),
                'group' => 'Administration',
            ],
            'admin/02-rollen-rechte-saas' => [
                'title' => 'Rollen, Rechte & SaaS-Konzept',
                'path' => base_path('docs/admin/02-rollen-rechte-saas.md'),
                'group' => 'Administration',
            ],
            'admin/03-oeffentliche-seiten-und-embeds' => [
                'title' => 'Öffentliche Seiten, Embeds & Außenansicht',
                'path' => base_path('docs/admin/03-oeffentliche-seiten-und-embeds.md'),
                'group' => 'Administration',
            ],
            'admin/04-roadmap-version-1-0' => [
                'title' => 'Roadmap Version 1.0',
                'path' => base_path('docs/admin/04-roadmap-version-1-0.md'),
                'group' => 'Administration',
            ],
            'assets' => [
                'title' => 'Screenshots',
                'path' => base_path('docs/assets/README.md'),
                'group' => 'Ressourcen',
            ],
            'assets/shotlist' => [
                'title' => 'Shotlist',
                'path' => base_path('docs/assets/SHOTLIST.md'),
                'group' => 'Ressourcen',
            ],
        ];
    }

    public function index(): View
    {
        return $this->show('');
    }

    public function show(?string $path = ''): View
    {
        $path = trim((string) $path, '/');
        $page = $this->pages()[$path] ?? abort(404);

        abort_unless(File::exists($page['path']), 404);

        $markdown = File::get($page['path']);
        $rewrittenMarkdown = $this->rewriteMarkdownLinks($markdown);
        $content = (string) Str::markdown($rewrittenMarkdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return view('docs.show', [
            'pageTitle' => $page['title'],
            'pageGroup' => $page['group'],
            'currentPath' => $path,
            'content' => $content,
            'navigation' => $this->groupedNavigation(),
            'screenshots' => $this->extractScreenshotReferences($markdown),
        ]);
    }

    public function asset(string $filename)
    {
        abort_unless(preg_match('/^[A-Za-z0-9._-]+\.(png|jpg|jpeg|webp|svg)$/', $filename) === 1, 404);

        $path = base_path('docs/assets/' . $filename);
        abort_unless(File::exists($path), 404);

        return Response::file($path);
    }

    /**
     * @return array<string, array<int, array{title:string, url:string, active:bool}>>
     */
    protected function groupedNavigation(): array
    {
        $groups = [];

        foreach ($this->pages() as $urlPath => $page) {
            $groups[$page['group']][] = [
                'title' => $page['title'],
                'url' => $urlPath === ''
                    ? route('docs.index')
                    : route('docs.show', ['path' => $urlPath]),
                'active' => request()->routeIs('docs.show') && trim((string) request()->route('path', ''), '/') === $urlPath
                    || (request()->routeIs('docs.index') && $urlPath === ''),
            ];
        }

        return $groups;
    }

    /**
     * @return array<int, array{filename:string, url:?string, exists:bool}>
     */
    protected function extractScreenshotReferences(string $markdown): array
    {
        preg_match_all('/docs\/assets\/([A-Za-z0-9._-]+\.(?:png|jpg|jpeg|webp|svg))/', $markdown, $matches);

        $files = array_values(array_unique($matches[1] ?? []));

        return array_map(function (string $filename): array {
            $path = base_path('docs/assets/' . $filename);
            $exists = File::exists($path);

            return [
                'filename' => $filename,
                'url' => $exists ? route('docs.asset', ['filename' => $filename]) : null,
                'exists' => $exists,
            ];
        }, $files);
    }

    protected function rewriteMarkdownLinks(string $markdown): string
    {
        $rewrites = [
            './handbuch/01-startcenter-und-schnellstart.md' => route('docs.show', ['path' => 'handbuch/01-startcenter-und-schnellstart']),
            './handbuch/02-verein-und-benutzer.md' => route('docs.show', ['path' => 'handbuch/02-verein-und-benutzer']),
            './handbuch/03-mitglieder-und-kommunikation.md' => route('docs.show', ['path' => 'handbuch/03-mitglieder-und-kommunikation']),
            './handbuch/04-veranstaltungen-und-dienstplan.md' => route('docs.show', ['path' => 'handbuch/04-veranstaltungen-und-dienstplan']),
            './handbuch/05-formulare-vorlagen-und-versand.md' => route('docs.show', ['path' => 'handbuch/05-formulare-vorlagen-und-versand']),
            './handbuch/06-finanzen-und-rechnungen.md' => route('docs.show', ['path' => 'handbuch/06-finanzen-und-rechnungen']),
            './admin/01-betrieb-deployment-backups.md' => route('docs.show', ['path' => 'admin/01-betrieb-deployment-backups']),
            './admin/02-rollen-rechte-saas.md' => route('docs.show', ['path' => 'admin/02-rollen-rechte-saas']),
            './admin/03-oeffentliche-seiten-und-embeds.md' => route('docs.show', ['path' => 'admin/03-oeffentliche-seiten-und-embeds']),
            './admin/04-roadmap-version-1-0.md' => route('docs.show', ['path' => 'admin/04-roadmap-version-1-0']),
            './assets/README.md' => route('docs.show', ['path' => 'assets']),
            './assets/SHOTLIST.md' => route('docs.show', ['path' => 'assets/shotlist']),
        ];

        return str_replace(array_keys($rewrites), array_values($rewrites), $markdown);
    }
}
