<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            color: #000;
        }

        h1 {
            font-size: 16pt;
            margin-bottom: 10px;
        }

        h2 {
            font-size: 12pt;
            margin-top: 20px;
        }

        .section {
            margin-bottom: 15px;
        }

        .meta {
            font-size: 9pt;
            color: #555;
        }

        .entry {
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 8px;
        }

        .entry-type {
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #555;
        }

        .entry-title {
            font-size: 11pt;
            font-weight: bold;
            margin-top: 4px;
        }

        .entry-meta {
            font-size: 8.5pt;
            color: #555;
            margin-top: 6px;
        }
    </style>
</head>
<body>

<h1>{{ $protocol->title }}</h1>

<div class="meta">
    <p><strong>Typ:</strong> {{ $protocol->type }}</p>
    <p><strong>Ort:</strong> {{ $protocol->location }}</p>
    <p><strong>Beginn:</strong> {{ $protocol->start_time }}</p>
    <p><strong>Ende:</strong> {{ $protocol->end_time }}</p>
</div>

@if($protocol->participants->count())
<div class="section">
    <h2>Teilnehmer</h2>
    <ul>
        @foreach($protocol->participants as $member)
            <li>{{ $member->full_name }}</li>
        @endforeach
    </ul>
</div>
@endif

@php
    $entryTypes = \App\Models\ProtocolEntry::typeOptions();
    $visibleEntries = ($protocol->entries ?? collect())->where('visible_in_protocol', true)->values();
@endphp

@if($visibleEntries->isNotEmpty())
<div class="section">
    <h2>Protokollpunkte</h2>
    @php($currentAgendaTitle = null)
    @foreach($visibleEntries as $entry)
        @if($entry->agenda_title && $entry->agenda_title !== $currentAgendaTitle)
            @php($currentAgendaTitle = $entry->agenda_title)
            <h2>{{ $entry->agenda_title }}</h2>
        @endif

        <div class="entry">
            <div class="entry-type">{{ $entryTypes[$entry->type] ?? 'Protokollpunkt' }}</div>
            @if($entry->title)
                <div class="entry-title">{{ $entry->title }}</div>
            @endif
            <p>{!! nl2br(e($entry->content)) !!}</p>
            @if($entry->responsible_name || $entry->due_date || $entry->scheduled_date)
                <div class="entry-meta">
                    @if($entry->responsible_name)
                        Verantwortlich: {{ $entry->responsible_name }}
                    @endif
                    @if($entry->due_date)
                        {{ $entry->responsible_name ? ' | ' : '' }}Fällig: {{ $entry->due_date->format('d.m.Y') }}
                    @endif
                    @if($entry->scheduled_date)
                        {{ ($entry->responsible_name || $entry->due_date) ? ' | ' : '' }}Termin: {{ $entry->scheduled_date->format('d.m.Y') }}
                    @endif
                </div>
            @endif
        </div>
    @endforeach
</div>
@endif

@if($protocol->resolutions)
<div class="section">
    <h2>Beschlüsse / Ergebnisse</h2>
    <p>{!! nl2br(e($protocol->resolutions)) !!}</p>
</div>
@endif

@if($protocol->next_meeting)
<div class="section">
    <h2>Nächstes Treffen</h2>
    <p>{!! nl2br(e($protocol->next_meeting)) !!}</p>
</div>
@endif

@if($visibleEntries->isEmpty())
<div class="section">
    <h2>Protokoll</h2>
    {!! $protocol->content !!}
</div>
@endif

</body>
</html>
