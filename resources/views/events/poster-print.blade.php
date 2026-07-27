<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $headline }}</title>
    <style>
        body {
            margin: 0;
            background: #f8fafc;
            color: #0f172a;
            font-family: Inter, Arial, sans-serif;
        }

        .page {
            width: min(920px, calc(100% - 32px));
            margin: 24px auto;
            background: white;
            padding: 42px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.12);
        }

        .eyebrow {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #64748b;
        }

        h1 {
            margin: 10px 0 0;
            font-size: 42px;
            line-height: 1.08;
        }

        .meta {
            margin-top: 12px;
            color: #475569;
            font-size: 15px;
        }

        .note {
            margin-top: 22px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            padding: 14px 16px;
            border-radius: 12px;
            font-size: 15px;
            color: #334155;
        }

        .month {
            margin-top: 34px;
        }

        .month-title {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 8px;
            font-size: 18px;
            font-weight: 800;
        }

        .event {
            display: grid;
            grid-template-columns: 110px minmax(0, 1fr);
            gap: 20px;
            padding: 18px 0;
            border-bottom: 1px solid #e2e8f0;
            break-inside: avoid;
        }

        .date-box {
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            padding: 12px;
            text-align: center;
        }

        .weekday {
            font-size: 12px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
        }

        .day {
            margin-top: 3px;
            font-size: 34px;
            font-weight: 900;
        }

        .time {
            margin-top: 4px;
            font-size: 13px;
            color: #475569;
        }

        .event-title {
            font-size: 24px;
            font-weight: 850;
            line-height: 1.2;
        }

        .event-meta {
            margin-top: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            color: #475569;
            font-size: 14px;
        }

        .pill {
            border-radius: 999px;
            background: #f1f5f9;
            padding: 5px 10px;
        }

        .description {
            margin-top: 10px;
            color: #334155;
            font-size: 14px;
            line-height: 1.5;
        }

        .actions {
            width: min(920px, calc(100% - 32px));
            margin: 18px auto 0;
            text-align: right;
        }

        .actions button {
            border: 0;
            border-radius: 10px;
            background: #0f172a;
            color: white;
            padding: 12px 18px;
            font-weight: 700;
            cursor: pointer;
        }

        @media print {
            @page {
                margin: 14mm;
            }

            html,
            body {
                background: white;
                min-height: auto;
            }

            .actions {
                display: none;
            }

            .page {
                width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" onclick="window.print()">Drucken</button>
    </div>

    <main class="page">
        <div class="eyebrow">{{ $tenant->name ?? 'Clubano' }}</div>
        <h1>{{ $headline }}</h1>
        <div class="meta">Stand: {{ now()->format('d.m.Y') }} · {{ $events->count() }} Termin{{ $events->count() === 1 ? '' : 'e' }}</div>

        @if($note)
            <div class="note">{{ $note }}</div>
        @endif

        @foreach($events->groupBy(fn ($event) => $event->start->translatedFormat('F Y')) as $month => $monthEvents)
            <section class="month">
                <div class="month-title">{{ $month }}</div>

                @foreach($monthEvents as $event)
                    <article class="event">
                        <div class="date-box">
                            <div class="weekday">{{ $event->start->translatedFormat('D') }}</div>
                            <div class="day">{{ $event->start->format('d') }}</div>
                            <div class="time">{{ $event->start->format('H:i') }} Uhr</div>
                        </div>

                        <div>
                            <div class="event-title">{{ $event->title }}</div>
                            <div class="event-meta">
                                <span class="pill">{{ $event->location ?: 'Ort folgt' }}</span>
                                <span class="pill">bis {{ $event->end->format('H:i') }} Uhr</span>
                                @if($event->category)
                                    <span class="pill">{{ $event->category->name }}</span>
                                @endif
                                @if($event->responsible_name)
                                    <span class="pill">Verantwortlich: {{ $event->responsible_name }}</span>
                                @endif
                            </div>

                            @php
                                $description = \Illuminate\Support\Str::limit(
                                    html_entity_decode(trim(strip_tags($event->description ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                                    220
                                );
                            @endphp

                            @if($description !== '')
                                <div class="description">{{ $description }}</div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </section>
        @endforeach
    </main>
</body>
</html>
