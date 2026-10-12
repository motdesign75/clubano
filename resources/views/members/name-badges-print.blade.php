<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Namensschilder</title>
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #ffffff;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin: 0 0 10mm;
            padding: 10px 12px;
            border: 1px solid #dbe4ef;
            border-radius: 12px;
            background: #f8fafc;
            color: #475569;
            font-size: 13px;
        }

        .toolbar button {
            border: 0;
            border-radius: 999px;
            background: #020617;
            color: #ffffff;
            padding: 10px 16px;
            font-weight: 700;
            cursor: pointer;
        }

        .sheet {
            display: grid;
            grid-template-columns: repeat(2, 85mm);
            grid-auto-rows: 55mm;
            gap: 7mm;
            align-items: start;
            justify-content: center;
            break-after: page;
            page-break-after: always;
        }

        .sheet:last-child {
            break-after: auto;
            page-break-after: auto;
        }

        .badge {
            position: relative;
            display: grid;
            grid-template-columns: 18mm 1fr;
            gap: 5mm;
            width: 85mm;
            height: 55mm;
            overflow: hidden;
            border: 0.35mm solid #d8e2ef;
            border-radius: 3mm;
            padding: 7mm;
            background: #ffffff;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .badge::before {
            content: "";
            position: absolute;
            inset: 0;
            border-top: 2mm solid #0f3f6f;
            pointer-events: none;
        }

        .logo-wrap {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding-top: 2mm;
        }

        .logo {
            max-width: 17mm;
            max-height: 17mm;
            object-fit: contain;
        }

        .logo-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 17mm;
            height: 17mm;
            border-radius: 4mm;
            background: #e0f2fe;
            color: #075985;
            font-size: 8mm;
            font-weight: 700;
        }

        .content {
            min-width: 0;
            padding-top: 1mm;
        }

        .club {
            overflow: hidden;
            color: #64748b;
            font-size: 8pt;
            font-weight: 700;
            letter-spacing: 0.08em;
            line-height: 1.2;
            text-overflow: ellipsis;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .name {
            display: flex;
            align-items: center;
            min-height: 20mm;
            margin-top: 4mm;
            color: #0f172a;
            font-size: 18pt;
            font-weight: 800;
            line-height: 1.08;
            overflow-wrap: anywhere;
        }

        .meta {
            margin-top: 3mm;
            color: #64748b;
            font-size: 8pt;
            line-height: 1.35;
        }

        @media print {
            .toolbar {
                display: none;
            }

            body {
                background: #ffffff;
            }
        }
    </style>
</head>
<body>
    @php
        $logoUrl = $tenant?->logo_url;
        $tenantName = $tenant?->name ?: config('app.name', 'Clubano');
    @endphp

    <div class="toolbar">
        <div>{{ $members->count() }} Namensschild(er) im Visitenkartenformat quer, je 85 × 55 mm.</div>
        <button type="button" onclick="window.print()">Drucken</button>
    </div>

    @foreach($members->chunk(8) as $pageMembers)
        <main class="sheet">
            @foreach($pageMembers as $member)
                @php
                    $name = trim(implode(' ', array_filter([$member->first_name, $member->last_name])));
                    $name = $name !== '' ? $name : ($member->full_name ?: ($member->organization ?: 'Ohne Namen'));
                @endphp
                <article class="badge">
                    <div class="logo-wrap">
                        @if($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $tenantName }}" class="logo">
                        @else
                            <div class="logo-placeholder">{{ mb_substr($tenantName, 0, 1) }}</div>
                        @endif
                    </div>
                    <div class="content">
                        <div class="club">{{ $tenantName }}</div>
                        <div class="name">{{ $name }}</div>
                        @if($member->organization)
                            <div class="meta">{{ $member->organization }}</div>
                        @endif
                    </div>
                </article>
            @endforeach
        </main>
    @endforeach
</body>
</html>
