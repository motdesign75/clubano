<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $headline }}</title>
    <style>
        @page {
            margin: 18mm 16mm;
        }

        body {
            margin: 0;
            color: #0f172a;
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            line-height: 1.45;
        }

        .eyebrow {
            color: #64748b;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }

        h1 {
            margin: 6px 0 0;
            font-size: 28px;
            line-height: 1.12;
        }

        .meta {
            margin-top: 8px;
            color: #475569;
            font-size: 11px;
        }

        .note {
            margin-top: 14px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            padding: 10px 12px;
            font-size: 11px;
        }

        .month {
            margin-top: 22px;
            page-break-inside: avoid;
        }

        .month-title {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 6px;
            font-size: 14px;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        tr.event-row {
            page-break-inside: avoid;
        }

        td {
            vertical-align: top;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 0;
        }

        .date-cell {
            width: 88px;
            padding-right: 16px;
        }

        .date-box {
            border: 1px solid #cbd5e1;
            padding: 8px 6px;
            text-align: center;
        }

        .weekday {
            color: #64748b;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .day {
            margin-top: 2px;
            font-size: 26px;
            font-weight: bold;
        }

        .time {
            margin-top: 2px;
            color: #475569;
            font-size: 10px;
        }

        .event-title {
            font-size: 17px;
            font-weight: bold;
            line-height: 1.22;
        }

        .event-meta {
            margin-top: 6px;
            color: #475569;
            font-size: 10px;
        }

        .description {
            margin-top: 8px;
            color: #334155;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="eyebrow">{{ $tenant->name ?? 'Clubano' }}</div>
    <h1>{{ $headline }}</h1>
    <div class="meta">Stand: {{ now()->format('d.m.Y') }} &middot; {{ $events->count() }} Termin{{ $events->count() === 1 ? '' : 'e' }}</div>

    @if($note)
        <div class="note">{{ $note }}</div>
    @endif

    @foreach($events->groupBy(fn ($event) => $event->start->translatedFormat('F Y')) as $month => $monthEvents)
        <section class="month">
            <div class="month-title">{{ $month }}</div>

            <table>
                <tbody>
                    @foreach($monthEvents as $event)
                        <tr class="event-row">
                            <td class="date-cell">
                                <div class="date-box">
                                    <div class="weekday">{{ $event->start->translatedFormat('D') }}</div>
                                    <div class="day">{{ $event->start->format('d') }}</div>
                                    <div class="time">{{ $event->start->format('H:i') }} Uhr</div>
                                </div>
                            </td>
                            <td>
                                <div class="event-title">{{ $event->title }}</div>
                                <div class="event-meta">
                                    {{ $event->location ?: 'Ort folgt' }}
                                    &nbsp;|&nbsp; bis {{ $event->end->format('H:i') }} Uhr
                                    @if($event->category)
                                        &nbsp;|&nbsp; {{ $event->category->name }}
                                    @endif
                                    @if($event->responsible_name)
                                        &nbsp;|&nbsp; Verantwortlich: {{ $event->responsible_name }}
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
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endforeach
</body>
</html>
