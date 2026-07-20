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

<div class="section">
    <h2>Protokoll</h2>
    {!! $protocol->content !!}
</div>

</body>
</html>