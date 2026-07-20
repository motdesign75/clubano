@php
    $dateRange = optional($event->start)->format('d.m.Y H:i') . ' bis ' . optional($event->end)->format('d.m.Y H:i') . ' Uhr';
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Mitglieder-Aushang {{ $event->title }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 12mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #0f172a;
        }

        .header {
            width: 100%;
            margin-bottom: 16px;
        }

        .brand {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #475569;
        }

        .title {
            margin-top: 6px;
            font-size: 24px;
            font-weight: bold;
            color: #0f172a;
        }

        .subtitle {
            margin-top: 4px;
            font-size: 12px;
            font-weight: bold;
            color: #2563eb;
        }

        .meta {
            margin-top: 8px;
            line-height: 1.5;
            color: #475569;
        }

        .summary {
            margin: 12px 0 14px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            padding: 8px 10px;
        }

        table.plan {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.plan th,
        table.plan td {
            border: 1px solid #cbd5e1;
            padding: 9px 10px;
            vertical-align: top;
        }

        table.plan th {
            background: #dbe4f0;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            color: #0f172a;
        }

        .col-time { width: 15%; }
        .col-shift { width: 21%; }
        .col-note { width: 18%; }

        .time {
            font-weight: bold;
            font-size: 14px;
        }

        .date {
            margin-top: 4px;
            font-size: 9px;
            color: #64748b;
        }

        .shift-title {
            font-weight: bold;
            font-size: 15px;
        }

        .role {
            margin-top: 4px;
            color: #64748b;
            font-size: 10px;
        }

        .helpers {
            margin: 0;
            padding-left: 16px;
        }

        .helpers li {
            margin-bottom: 4px;
            line-height: 1.35;
        }

        .empty {
            color: #b91c1c;
            font-weight: bold;
        }

        .hint {
            color: #475569;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">{{ $event->tenant->name ?? 'Clubano' }}</div>
        <div class="title">{{ $event->title }}</div>
        <div class="subtitle">Mitglieder-Aushang</div>
        <div class="meta">
            <div>{{ $dateRange }}</div>
            <div>{{ $event->location ?: 'Ort folgt' }}</div>
        </div>
    </div>

    <div class="summary">
        <strong>Übersicht:</strong> Hier steht pro Schicht direkt, wer Dienst hat. Interne Planungszahlen werden in diesem Aushang bewusst nicht angezeigt.
    </div>

    <table class="plan">
        <thead>
            <tr>
                <th class="col-time">Zeit</th>
                <th class="col-shift">Schicht</th>
                <th>Wer hat Dienst?</th>
                <th class="col-note">Hinweis</th>
            </tr>
        </thead>
        <tbody>
            @forelse($eventShifts as $shift)
                @php
                    $activeAssignments = $shift->assignments->reject(fn ($assignment) => $assignment->status === 'cancelled');
                @endphp
                <tr>
                    <td class="col-time">
                        <div class="time">{{ $shift->starts_at->format('H:i') }}–{{ $shift->ends_at->format('H:i') }}</div>
                        <div class="date">{{ $shift->starts_at->format('d.m.Y') }}</div>
                    </td>
                    <td class="col-shift">
                        <div class="shift-title">{{ $shift->title }}</div>
                        <div class="role">{{ $shift->role ?: 'Allgemeiner Dienst' }}</div>
                    </td>
                    <td>
                        @if($activeAssignments->isEmpty())
                            <span class="empty">Noch offen</span>
                        @else
                            <ol class="helpers">
                                @foreach($activeAssignments as $assignment)
                                    <li>
                                        {{ $assignment->display_name }}
                                        @if($assignment->status === 'planned')
                                            <span class="hint">(eingeplant)</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </td>
                    <td class="col-note">
                        @if($shift->notes)
                            {{ $shift->notes }}
                        @elseif($activeAssignments->isEmpty())
                            Unterstützung willkommen.
                        @else
                            &nbsp;
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">Für dieses Event sind noch keine Schichten angelegt.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
