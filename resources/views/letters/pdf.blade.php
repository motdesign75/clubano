<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 11pt; line-height: 1.5; }
        .page { position: relative; min-height: 277mm; padding: 34mm 22mm 22mm 28mm; box-sizing: border-box; }
        .letterhead-image { position: absolute; inset: 0; width: 210mm; height: 297mm; object-fit: cover; z-index: -1; }
        .address-window { margin-top: 18mm; width: 82mm; min-height: 40mm; }
        .meta { margin-top: 12mm; text-align: right; font-size: 10pt; color: #475569; }
        .subject { margin-top: 16mm; font-size: 13pt; font-weight: bold; }
        .body { margin-top: 10mm; }
    </style>
</head>
<body>
    <div class="page">
        @if($showLetterheadImage && $letterheadImagePath)
            <img src="{{ $letterheadImagePath }}" alt="Briefbogen" class="letterhead-image">
        @endif
        <div class="address-window">
            @foreach($letter['address_lines'] as $line)
                <div>{{ $line }}</div>
            @endforeach
        </div>
        <div class="meta">{{ $tenant->city ?: 'Sarstedt' }}, {{ now()->format('d.m.Y') }}</div>
        @if($template->subject)
            <div class="subject">{{ $template->subject }}</div>
        @endif
        <div class="body">{!! $letter['body'] !!}</div>
    </div>
</body>
</html>
