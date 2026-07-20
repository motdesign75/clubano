@php
    $dateRange = optional($event->start)->format('d.m.Y H:i') . ' bis ' . optional($event->end)->format('d.m.Y H:i') . ' Uhr';
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Dienstplan {{ $event->title }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2937;
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

        .meta {
            margin-top: 8px;
            line-height: 1.5;
            color: #475569;
        }

        .stats {
            margin-top: 14px;
            width: 100%;
            border-collapse: collapse;
        }

        .stats td {
            width: 25%;
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            background: #f8fafc;
        }

        .stat-label {
            font-size: 9px;
            text-transform: uppercase;
            color: #64748b;
        }

        .stat-value {
            margin-top: 4px;
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
        }

        .summary {
            margin: 12px 0 14px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            padding: 8px 10px;
            border-radius: 8px;
        }

        table.plan {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.plan th,
        table.plan td {
            border: 1px solid #cbd5e1;
            padding: 7px 8px;
            vertical-align: top;
        }

        table.plan th {
            background: #dbe4f0;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            color: #0f172a;
        }

        .col-time { width: 11%; }
        .col-shift { width: 21%; }
        .col-num { width: 5%; text-align: center; }
        .col-status { width: 12%; }
        .col-note { width: 15%; }

        .time {
            font-weight: bold;
            font-size: 12px;
        }

        .date {
            margin-top: 3px;
            font-size: 9px;
            color: #64748b;
        }

        .shift-title {
            font-weight: bold;
            font-size: 13px;
        }

        .role {
            margin-top: 3px;
            color: #64748b;
            font-size: 10px;
        }

        .helpers {
            margin: 0;
            padding-left: 16px;
        }

        .helpers li {
            margin-bottom: 3px;
        }

        .status-open {
            color: #b91c1c;
            font-weight: bold;
        }

        .status-full {
            color: #166534;
            font-weight: bold;
        }

        .status-over {
            color: #92400e;
            font-weight: bold;
        }

        .muted {
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">{{ $event->tenant->name ?? 'Clubano' }}</div>
        <div class="title">{{ $event->title }}</div>
        <div class="meta">
            <div>{{ $dateRange }}</div>
            <div>{{ $event->location ?: 'Ort folgt' }}</div>
        </div>

        <table class="stats">
            <tr>
                <td>
                    <div class="stat-label">Schichten</div>
                    <div class="stat-value">{{ $scheduleStats['shift_count'] }}</div>
                </td>
                <td>
                    <div class="stat-label">Soll</div>
                    <div class="stat-value">{{ $scheduleStats['total_required'] }}</div>
                </td>
                <td>
                    <div class="stat-label">Bestätigt</div>
                    <div class="stat-value">{{ $scheduleStats['total_confirmed'] }}</div>
                </td>
                <td>
                    <div class="stat-label">Offen</div>
                    <div class="stat-value">{{ $scheduleStats['open_slots'] }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="summary">
        <strong>Leselogik:</strong> Pro Schicht eine Zeile. In der Besetzungsspalte stehen die eingeteilten Personen. Offene Schichten sind rot markiert.
    </div>

    <table class="plan">
        <thead>
            <tr>
                <th class="col-time">Zeit</th>
                <th class="col-shift">Schicht</th>
                <th class="col-num">Soll</th>
                <th class="col-num">Ist</th>
                <th class="col-num">Offen</th>
                <th>Wer hat Dienst?</th>
                <th class="col-status">Status</th>
                <th class="col-note">Hinweis</th>
            </tr>
        </thead>
        <tbody>
            @forelse($eventShifts as $shift)
                @php
                    $statusClass = match($shift->coverage_status) {
                        'full' => 'status-full',
                        'overstaffed' => 'status-over',
                        default => 'status-open',
                    };

                    $statusLabel = match($shift->coverage_status) {
                        'full' => 'Ausreichend',
                        'overstaffed' => 'Über Soll',
                        default => 'Offen',
                    };
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
                    <td class="col-num">{{ $shift->required_people }}</td>
                    <td class="col-num">{{ $shift->confirmed_assignments_count }}</td>
                    <td class="col-num">{{ $shift->open_slots }}</td>
                    <td>
                        @if($shift->assignments->isEmpty())
                            <span class="muted">Noch niemand eingeteilt</span>
                        @else
                            <ol class="helpers">
                                @foreach($shift->assignments as $assignment)
                                    <li>
                                        {{ $assignment->display_name }}
                                        @if($assignment->status === 'planned')
                                            <span class="muted">(geplant)</span>
                                        @elseif($assignment->status === 'cancelled')
                                            <span class="muted">(abgesagt)</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </td>
                    <td class="col-status">
                        <div class="{{ $statusClass }}">{{ $statusLabel }}</div>
                    </td>
                    <td class="col-note">
                        @if($shift->notes)
                            {{ $shift->notes }}
                        @elseif($shift->open_slots > 0)
                            {{ $shift->open_slots }} Platz / Plätze fehlen noch.
                        @elseif($shift->coverage_status === 'overstaffed')
                            Mehr Helfer als benötigt eingeteilt.
                        @else
                            Vollständig besetzt.
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; padding: 24px; color: #64748b;">
                        Für dieses Event wurden noch keine Schichten angelegt.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
