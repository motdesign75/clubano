<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; font-family: DejaVu Sans, Arial, sans-serif; color: #172033; font-size: 10.5pt; line-height: 1.45; }
        .page { position: relative; width: 210mm; min-height: 297mm; padding-top: 45mm; box-sizing: border-box; }
        .letterhead-image { position: absolute; inset: 0; width: 210mm; height: 297mm; object-fit: cover; z-index: -1; }
        .top-row {
            margin-left: 20mm;
            margin-right: 20mm;
            height: 45mm;
            width: 170mm;
            border-collapse: separate;
            border-spacing: 0;
            border: 0;
        }
        .top-row tr,
        .top-row td { padding: 0; vertical-align: top; border: 0; }
        .address-cell { width: 85mm; }
        .top-gap-cell { width: 20mm; }
        .meta-cell { width: 65mm; }
        .address-window {
            width: 85mm;
            height: 45mm;
            overflow: hidden;
            box-sizing: border-box;
        }
        .sender-line {
            margin-left: 5mm;
            margin-top: 2mm;
            width: 75mm;
            height: 5mm;
            overflow: hidden;
            white-space: nowrap;
            font-size: 7.5pt;
            line-height: 5mm;
            color: #64748b;
        }
        .recipient-address {
            margin-left: 5mm;
            margin-top: 10.7mm;
            width: 80mm;
            height: 27.3mm;
            overflow: hidden;
            font-size: 11pt;
            line-height: 4.55mm;
            color: #111827;
        }
        .meta {
            padding-top: 5mm;
            width: 65mm;
            min-height: 40mm;
            font-size: 9.5pt;
            line-height: 1.35;
            color: #475569;
        }
        .meta-row { margin-bottom: 2.4mm; }
        .meta-label { display: block; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; }
        .meta-value { display: block; color: #172033; }
        .content {
            margin: 26mm 20mm 22mm 25mm;
            box-sizing: border-box;
        }
        .subject { margin: 0 0 8mm 0; font-size: 12pt; font-weight: bold; line-height: 1.3; }
        .body { font-size: 10.5pt; line-height: 1.5; }
        .body p { margin: 0 0 4mm 0; }
        .body ul, .body ol { margin: 0 0 4mm 6mm; padding-left: 5mm; }
        .body table { width: 100%; border-collapse: collapse; margin: 4mm 0; }
        .body th, .body td { border: 0.2mm solid #cbd5e1; padding: 2mm; vertical-align: top; }
    </style>
</head>
<body>
    <div class="page">
        @if($showLetterheadImage && $letterheadImagePath)
            <img src="{{ $letterheadImagePath }}" alt="Briefbogen" class="letterhead-image">
        @endif
        <table class="top-row">
            <tr>
                <td class="address-cell">
                    <div class="address-window">
                        @if($senderLine)
                            <div class="sender-line">{{ $senderLine }}</div>
                        @endif
                        <div class="recipient-address">
                            @foreach($letter['address_lines'] as $line)
                                <div>{{ $line }}</div>
                            @endforeach
                        </div>
                    </div>
                </td>
                <td class="top-gap-cell"></td>
                <td class="meta-cell">
                    <div class="meta">
                        <div class="meta-row">
                            <span class="meta-label">Datum</span>
                            <span class="meta-value">{{ $tenant->city ?: 'Sarstedt' }}, {{ now()->format('d.m.Y') }}</span>
                        </div>
                        @if($tenant->email)
                            <div class="meta-row">
                                <span class="meta-label">E-Mail</span>
                                <span class="meta-value">{{ $tenant->email }}</span>
                            </div>
                        @endif
                        @if($tenant->phone)
                            <div class="meta-row">
                                <span class="meta-label">Telefon</span>
                                <span class="meta-value">{{ $tenant->phone }}</span>
                            </div>
                        @endif
                    </div>
                </td>
            </tr>
        </table>
        <main class="content">
            @if($template->subject)
                <div class="subject">{{ $template->subject }}</div>
            @endif
            <div class="body">{!! $letter['body'] !!}</div>
        </main>
    </div>
</body>
</html>
