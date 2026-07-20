<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dienstplan {{ $event->title }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Inter, Arial, sans-serif;
            color: #0f172a;
            background: #eef2f7;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .page {
            max-width: 1120px;
            margin: 0 auto;
            padding: 20px 24px 28px;
        }

        .print-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 16px;
        }

        .toolbar-actions {
            display: flex;
            gap: 10px;
        }

        .button {
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            color: #1e293b;
            background: #fff;
        }

        .button.primary {
            border-color: #2563eb;
            background: #2563eb;
            color: #fff;
        }

        .sheet {
            background: #fff;
            border-radius: 20px;
            padding: 22px 24px 24px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
        }

        .header {
            display: grid;
            grid-template-columns: 1.7fr 1fr;
            gap: 20px;
            align-items: start;
            margin-bottom: 18px;
        }

        .eyebrow {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #475569;
        }

        .title {
            margin: 8px 0 0;
            font-size: 32px;
            line-height: 1.05;
            font-weight: 800;
        }

        .meta {
            margin-top: 10px;
            font-size: 15px;
            color: #475569;
            line-height: 1.5;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }

        .stat {
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            background: #f8fafc;
            padding: 12px 14px;
        }

        .stat-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
        }

        .stat-value {
            margin-top: 6px;
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
        }

        .summary {
            margin-bottom: 14px;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            background: #f8fafc;
            padding: 10px 14px;
            font-size: 13px;
            color: #334155;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        thead th {
            border: 1px solid #cbd5e1;
            background: #dbe4f0;
            padding: 10px 10px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            text-align: left;
            color: #0f172a;
        }

        tbody td {
            border: 1px solid #cbd5e1;
            padding: 10px 10px;
            font-size: 13px;
            vertical-align: top;
        }

        tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .col-time { width: 110px; }
        .col-shift { width: 220px; }
        .col-need { width: 62px; text-align: center; }
        .col-status { width: 120px; }
        .col-notes { width: 170px; }

        .time {
            font-weight: 700;
            font-size: 16px;
            line-height: 1.2;
        }

        .time-sub {
            margin-top: 4px;
            font-size: 11px;
            color: #64748b;
        }

        .shift-title {
            font-weight: 800;
            font-size: 18px;
            line-height: 1.15;
            color: #0f172a;
        }

        .shift-role {
            margin-top: 4px;
            font-size: 12px;
            color: #475569;
        }

        .need-number {
            display: block;
            font-size: 20px;
            font-weight: 800;
            line-height: 1.1;
            color: #0f172a;
        }

        .need-label {
            margin-top: 4px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
        }

        .helpers {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .helpers li {
            display: flex;
            align-items: baseline;
            gap: 8px;
            padding: 3px 0;
            border-bottom: 1px dashed #e2e8f0;
        }

        .helpers li:last-child {
            border-bottom: 0;
        }

        .helper-index {
            min-width: 18px;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
        }

        .helper-name {
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
        }

        .helper-note {
            margin-left: 4px;
            font-size: 11px;
            color: #92400e;
        }

        .helper-empty {
            color: #94a3b8;
            font-style: italic;
        }

        .status-badge {
            display: inline-block;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 800;
        }

        .status-full {
            background: #dcfce7;
            color: #166534;
        }

        .status-open {
            background: #fee2e2;
            color: #b91c1c;
        }

        .status-over {
            background: #fef3c7;
            color: #92400e;
        }

        .status-note {
            margin-top: 8px;
            font-size: 12px;
            line-height: 1.4;
            color: #475569;
        }

        .notes {
            font-size: 12px;
            line-height: 1.45;
            color: #334155;
            white-space: pre-line;
        }

        .notes-empty {
            color: #94a3b8;
        }

        @media print {
            html, body {
                width: 100%;
                height: auto;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
                background: #fff !important;
            }

            body {
                background: #fff;
            }

            .print-hidden {
                display: none !important;
            }

            .page {
                display: block !important;
                max-width: none;
                margin: 0;
                padding: 0;
            }

            .sheet {
                display: block !important;
                border-radius: 0;
                box-shadow: none;
                padding: 0;
                background: #fff !important;
                overflow: visible !important;
            }

            .header,
            .stats {
                display: block !important;
            }

            .stat {
                display: inline-block;
                vertical-align: top;
                width: 23%;
                margin-right: 1%;
            }

            table {
                page-break-inside: auto;
            }

            thead {
                display: table-header-group;
            }

            tr, td, th {
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="print-toolbar print-hidden">
            <div>
                <div class="eyebrow">Druckansicht</div>
                <strong>Dienstplan für den Aushang</strong>
            </div>
            <div class="toolbar-actions">
                <a href="{{ route('events.schedule.pdf', $event) }}" target="_blank" class="button primary">Interne PDF</a>
                <a href="{{ route('events.schedule.member-pdf', $event) }}" target="_blank" class="button">Mitglieder-Aushang</a>
                <a href="{{ route('events.show', $event) }}" class="button">Zurück zum Event</a>
            </div>
        </div>

        <div class="sheet">
            <div class="header">
                <div>
                    <div class="eyebrow">{{ $event->tenant->name ?? 'Clubano' }}</div>
                    <div class="title">{{ $event->title }}</div>
                    <div class="meta">
                        <div>{{ optional($event->start)->format('d.m.Y H:i') }} bis {{ optional($event->end)->format('d.m.Y H:i') }} Uhr</div>
                        <div>{{ $event->location ?: 'Ort folgt' }}</div>
                    </div>
                </div>

                <div class="stats">
                    <div class="stat">
                        <div class="stat-label">Schichten</div>
                        <div class="stat-value">{{ $scheduleStats['shift_count'] }}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">Soll</div>
                        <div class="stat-value">{{ $scheduleStats['total_required'] }}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">Bestätigt</div>
                        <div class="stat-value">{{ $scheduleStats['total_confirmed'] }}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">Offen</div>
                        <div class="stat-value">{{ $scheduleStats['open_slots'] }}</div>
                    </div>
                </div>
            </div>

            <div class="summary">
                <strong>Auf einen Blick:</strong> links stehen Zeit und Schicht, in der großen Besetzungsspalte direkt die eingeteilten Personen. Rot markierte Schichten haben noch offene Plätze.
            </div>

            <table>
                <thead>
                    <tr>
                        <th class="col-time">Zeit</th>
                        <th class="col-shift">Schicht</th>
                        <th class="col-need">Soll</th>
                        <th class="col-need">Ist</th>
                        <th class="col-need">Offen</th>
                        <th>Wer hat Dienst?</th>
                        <th class="col-status">Status</th>
                        <th class="col-notes">Hinweis</th>
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
                                <div class="time-sub">{{ $shift->starts_at->format('d.m.Y') }}</div>
                            </td>
                            <td class="col-shift">
                                <div class="shift-title">{{ $shift->title }}</div>
                                <div class="shift-role">{{ $shift->role ?: 'Allgemeiner Dienst' }}</div>
                            </td>
                            <td class="col-need">
                                <span class="need-number">{{ $shift->required_people }}</span>
                                <div class="need-label">Soll</div>
                            </td>
                            <td class="col-need">
                                <span class="need-number">{{ $shift->confirmed_assignments_count }}</span>
                                <div class="need-label">Ist</div>
                            </td>
                            <td class="col-need">
                                <span class="need-number">{{ $shift->open_slots }}</span>
                                <div class="need-label">offen</div>
                            </td>
                            <td>
                                @if($shift->assignments->isEmpty())
                                    <div class="helper-empty">Noch niemand eingeteilt</div>
                                @else
                                    <ul class="helpers">
                                        @foreach($shift->assignments as $assignment)
                                            <li>
                                                <span class="helper-index">{{ $loop->iteration }}.</span>
                                                <span class="helper-name">{{ $assignment->display_name }}</span>
                                                @if($assignment->status === 'planned')
                                                    <span class="helper-note">geplant</span>
                                                @elseif($assignment->status === 'cancelled')
                                                    <span class="helper-note">abgesagt</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                            <td class="col-status">
                                <span class="status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                @if($shift->open_slots > 0)
                                    <div class="status-note">{{ $shift->open_slots }} Platz / Plätze fehlen noch.</div>
                                @elseif($shift->coverage_status === 'overstaffed')
                                    <div class="status-note">Mehr Helfer als benötigt eingeteilt.</div>
                                @else
                                    <div class="status-note">Schicht ist vollständig besetzt.</div>
                                @endif
                            </td>
                            <td class="col-notes">
                                @if($shift->notes)
                                    <div class="notes">{{ $shift->notes }}</div>
                                @else
                                    <div class="notes-empty">—</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="padding: 28px 12px; text-align: center; color: #64748b;">
                                Für dieses Event wurden noch keine Schichten angelegt.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
