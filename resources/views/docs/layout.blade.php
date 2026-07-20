<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Clubano Dokumentation' }}</title>
    <style>
        :root {
            --bg: #f5f7fb;
            --surface: #ffffff;
            --surface-strong: #0f172a;
            --border: #dbe4f0;
            --text: #1e293b;
            --muted: #64748b;
            --accent: #4f46e5;
            --accent-soft: rgba(79, 70, 229, 0.08);
            --success-soft: rgba(16, 185, 129, 0.08);
            --shadow: 0 24px 60px rgba(15, 23, 42, 0.08);
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            background: linear-gradient(180deg, #f8fbff 0%, var(--bg) 100%);
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }

        .docs-shell {
            max-width: 1440px;
            margin: 0 auto;
            padding: 32px 24px 64px;
        }

        .docs-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 28px;
        }

        .docs-brand {
            max-width: 760px;
        }

        .docs-kicker {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.08);
            color: #334155;
            font-size: 0.875rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .docs-brand h1 {
            margin: 18px 0 14px;
            font-size: clamp(2.2rem, 4vw, 4rem);
            line-height: 1.03;
            letter-spacing: -0.03em;
        }

        .docs-brand p {
            margin: 0;
            max-width: 60ch;
            color: var(--muted);
            font-size: 1.05rem;
            line-height: 1.75;
        }

        .docs-link-home {
            white-space: nowrap;
            align-self: center;
            padding: 12px 18px;
            border-radius: 999px;
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--text);
            font-weight: 600;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
        }

        .docs-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            align-self: center;
        }

        .docs-link-print {
            white-space: nowrap;
            padding: 12px 18px;
            border-radius: 999px;
            background: var(--surface-strong);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
        }

        .docs-link-print:hover {
            text-decoration: none;
            background: #172036;
        }

        .docs-grid {
            display: grid;
            grid-template-columns: 300px minmax(0, 1fr);
            gap: 24px;
            align-items: start;
        }

        .docs-nav,
        .docs-content {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 28px;
            box-shadow: var(--shadow);
        }

        .docs-nav {
            position: sticky;
            top: 24px;
            padding: 24px 20px;
        }

        .docs-nav-group + .docs-nav-group {
            margin-top: 22px;
            padding-top: 22px;
            border-top: 1px solid rgba(148, 163, 184, 0.18);
        }

        .docs-nav-group-title {
            margin: 0 0 12px;
            color: #7c3aed;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .docs-nav-links {
            display: grid;
            gap: 8px;
        }

        .docs-nav-link {
            display: block;
            padding: 12px 14px;
            border-radius: 16px;
            color: #334155;
            font-weight: 600;
            line-height: 1.4;
        }

        .docs-nav-link:hover {
            text-decoration: none;
            background: rgba(148, 163, 184, 0.08);
        }

        .docs-nav-link.active {
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.16), rgba(14, 165, 233, 0.12));
            color: #1e1b4b;
        }

        .docs-content {
            overflow: hidden;
        }

        .docs-content-head {
            padding: 24px 28px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.18);
            background: linear-gradient(180deg, rgba(248, 250, 252, 0.9), rgba(255, 255, 255, 1));
        }

        .docs-content-head-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 12px;
        }

        .docs-chip {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.06);
            color: #475569;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .docs-content-head h2 {
            margin: 0;
            font-size: clamp(1.8rem, 2.8vw, 2.6rem);
            line-height: 1.12;
            letter-spacing: -0.02em;
        }

        .docs-content-body {
            padding: 30px 34px 40px;
        }

        .docs-article {
            color: var(--text);
            line-height: 1.78;
            font-size: 1.04rem;
        }

        .docs-article > *:first-child { margin-top: 0; }
        .docs-article h1,
        .docs-article h2,
        .docs-article h3 {
            color: #0f172a;
            line-height: 1.2;
            letter-spacing: -0.02em;
        }
        .docs-article h1 { font-size: 2rem; margin: 0 0 18px; }
        .docs-article h2 {
            margin-top: 40px;
            margin-bottom: 14px;
            padding-top: 4px;
            font-size: 1.55rem;
        }
        .docs-article h3 {
            margin-top: 26px;
            margin-bottom: 10px;
            font-size: 1.15rem;
        }
        .docs-article p,
        .docs-article ul,
        .docs-article ol,
        .docs-article blockquote {
            margin: 0 0 18px;
        }
        .docs-article ul,
        .docs-article ol {
            padding-left: 1.3rem;
        }
        .docs-article li + li {
            margin-top: 8px;
        }
        .docs-article strong {
            color: #0f172a;
        }
        .docs-article code {
            padding: 0.2rem 0.45rem;
            border-radius: 8px;
            background: rgba(15, 23, 42, 0.06);
            font-size: 0.94em;
        }
        .docs-article pre {
            overflow-x: auto;
            padding: 18px 20px;
            border-radius: 20px;
            background: #0f172a;
            color: #e2e8f0;
        }
        .docs-article pre code {
            padding: 0;
            background: transparent;
            color: inherit;
        }
        .docs-article blockquote {
            padding: 18px 20px;
            border-left: 4px solid rgba(79, 70, 229, 0.5);
            border-radius: 0 18px 18px 0;
            background: rgba(79, 70, 229, 0.05);
            color: #475569;
        }

        .docs-screenshots {
            margin-top: 40px;
            padding-top: 28px;
            border-top: 1px solid rgba(148, 163, 184, 0.18);
        }

        .docs-screenshots-head {
            margin-bottom: 18px;
        }

        .docs-screenshots-head h3 {
            margin: 0 0 8px;
            font-size: 1.25rem;
        }

        .docs-screenshots-head p {
            margin: 0;
            color: var(--muted);
        }

        .docs-shot-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px;
        }

        .docs-shot {
            border: 1px solid var(--border);
            border-radius: 22px;
            overflow: hidden;
            background: #fff;
        }

        .docs-shot-preview {
            aspect-ratio: 16 / 10;
            display: grid;
            place-items: center;
            background:
                linear-gradient(135deg, rgba(79, 70, 229, 0.08), rgba(14, 165, 233, 0.06)),
                linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border-bottom: 1px solid rgba(148, 163, 184, 0.18);
        }

        .docs-shot-preview.placeholder {
            padding: 20px;
            text-align: center;
            color: #64748b;
            font-weight: 700;
        }

        .docs-shot-preview.placeholder span {
            display: inline-flex;
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: #4338ca;
            font-size: 0.82rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .docs-shot img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .docs-shot-meta {
            padding: 14px 16px 16px;
        }

        .docs-shot-meta strong {
            display: block;
            margin-bottom: 6px;
            font-size: 0.98rem;
        }

        .docs-shot-meta p {
            margin: 0;
            color: var(--muted);
            font-size: 0.92rem;
            line-height: 1.55;
        }

        @media (max-width: 1080px) {
            .docs-grid {
                grid-template-columns: 1fr;
            }

            .docs-nav {
                position: static;
            }
        }

        @media (max-width: 720px) {
            .docs-shell {
                padding: 18px 14px 40px;
            }

            .docs-header {
                flex-direction: column;
            }

            .docs-actions {
                width: 100%;
                justify-content: stretch;
            }

            .docs-actions > * {
                width: 100%;
                text-align: center;
            }

            .docs-content-head,
            .docs-content-body {
                padding-left: 20px;
                padding-right: 20px;
            }
        }

        @media print {
            body {
                background: #fff;
            }

            .docs-shell {
                max-width: none;
                margin: 0;
                padding: 0;
            }

            .docs-header,
            .docs-nav,
            .docs-screenshots,
            .docs-link-home,
            .docs-link-print {
                display: none !important;
            }

            .docs-grid {
                display: block;
            }

            .docs-content,
            .docs-content-head,
            .docs-content-body {
                border: 0;
                border-radius: 0;
                box-shadow: none;
                padding-left: 0;
                padding-right: 0;
                background: #fff;
            }

            .docs-content-head {
                padding-top: 0;
                padding-bottom: 18px;
                border-bottom: 1px solid #cbd5e1;
            }

            .docs-content-body {
                padding-top: 24px;
                padding-bottom: 0;
            }

            .docs-content-head-meta {
                margin-bottom: 10px;
            }

            .docs-chip {
                border: 1px solid #cbd5e1;
                background: #fff;
                color: #334155;
            }

            .docs-article {
                font-size: 12pt;
                line-height: 1.55;
            }

            .docs-article h1,
            .docs-article h2,
            .docs-article h3 {
                page-break-after: avoid;
            }

            .docs-article p,
            .docs-article li {
                orphans: 3;
                widows: 3;
            }
        }
    </style>
</head>
<body>
    <div class="docs-shell">
        <header class="docs-header">
            <div class="docs-brand">
                <span class="docs-kicker">Clubano Dokumentation</span>
                <h1>Handbuch, Betrieb und Veröffentlichungsbasis in einer HTML-Doku</h1>
                <p>
                    Diese Dokumentation ist für echte Vereinsarbeit geschrieben. Sie erklärt Clubano aus Nutzersicht,
                    bleibt online-tauglich und zeigt an den passenden Stellen bewusst Screenshot-Platzhalter, bis die
                    finalen Bilder eingesetzt werden.
                </p>
            </div>

            <div class="docs-actions">
                <button type="button" class="docs-link-print" onclick="window.print()">Drucken</button>
                <a class="docs-link-home" href="{{ route('docs.index') }}">Zur Doku-Startseite</a>
            </div>
        </header>

        <div class="docs-grid">
            <aside class="docs-nav">
                @foreach ($navigation as $group => $links)
                    <section class="docs-nav-group">
                        <h2 class="docs-nav-group-title">{{ $group }}</h2>
                        <div class="docs-nav-links">
                            @foreach ($links as $link)
                                <a class="docs-nav-link {{ $link['active'] ? 'active' : '' }}" href="{{ $link['url'] }}">
                                    {{ $link['title'] }}
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </aside>

            <main class="docs-content">
                <div class="docs-content-head">
                    <div class="docs-content-head-meta">
                        <span class="docs-chip">{{ $group ?? 'Dokumentation' }}</span>
                        <span class="docs-chip">HTML-Version</span>
                        <span class="docs-chip">Screenshot-Platzhalter aktiv</span>
                    </div>
                    <h2>{{ $title ?? 'Dokumentation' }}</h2>
                </div>

                <div class="docs-content-body">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
</body>
</html>
