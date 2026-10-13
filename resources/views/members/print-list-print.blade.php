<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Mitgliederliste</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 12mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #ffffff;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9pt;
        }

        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 8mm;
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

        .heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12mm;
            margin-bottom: 7mm;
            padding-bottom: 4mm;
            border-bottom: 2px solid #1f3d63;
        }

        h1 {
            margin: 0;
            color: #0f172a;
            font-size: 20pt;
            line-height: 1.1;
        }

        .meta {
            margin-top: 2mm;
            color: #64748b;
            font-size: 9pt;
            line-height: 1.4;
        }

        .tenant {
            max-width: 90mm;
            color: #334155;
            font-size: 10pt;
            font-weight: 700;
            text-align: right;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }

        th {
            padding: 8px 7px;
            border: 1px solid #1e3a8a;
            background: #243f87;
            color: #ffffff;
            font-size: 8pt;
            letter-spacing: 0.04em;
            text-align: left;
            text-transform: uppercase;
        }

        td {
            padding: 7px;
            border: 1px solid #cbd5e1;
            vertical-align: top;
        }

        tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .name {
            font-weight: 700;
        }

        .muted {
            color: #64748b;
            font-size: 8pt;
            line-height: 1.35;
        }

        .amount {
            white-space: nowrap;
            text-align: right;
        }

        @media print {
            .toolbar {
                display: none;
            }
        }
    </style>
</head>
<body>
    @php
        $tenantName = $tenant?->name ?: config('app.name', 'Clubano');
        $generatedAt = now()->format('d.m.Y H:i');
        $isOrganizationMode = ($displayMode ?? 'person') === 'organization';
        $displayLabel = $isOrganizationMode ? 'Firmenname / Organisation' : 'Vor- und Nachname';
        $displayMember = function ($member) use ($isOrganizationMode) {
            $personName = trim(implode(' ', array_filter([$member->first_name, $member->last_name])));
            $personName = $personName !== '' ? $personName : trim((string) ($member->full_name ?? ''));
            $organization = trim((string) ($member->organization ?? ''));

            if ($isOrganizationMode) {
                return [
                    'primary' => $organization !== '' ? $organization : ($personName !== '' ? $personName : 'Ohne Namen'),
                    'secondary' => $organization !== '' && $personName !== '' ? $personName : null,
                ];
            }

            return [
                'primary' => $personName !== '' ? $personName : ($organization !== '' ? $organization : 'Ohne Namen'),
                'secondary' => $personName !== '' && $organization !== '' ? $organization : null,
            ];
        };
    @endphp

    <div class="toolbar">
        <div>{{ $members->count() }} Mitglied(er) in der Druckliste.</div>
        <button type="button" onclick="window.print()">Drucken</button>
    </div>

    <header class="heading">
        <div>
            <h1>Mitgliederliste</h1>
            <div class="meta">Erstellt am {{ $generatedAt }} · {{ $members->count() }} Mitglied(er) · Darstellung: {{ $displayLabel }}</div>
        </div>
        <div class="tenant">{{ $tenantName }}</div>
    </header>

    <table>
        <thead>
            <tr>
                <th style="width: 20%;">{{ $isOrganizationMode ? 'Firma / Organisation' : 'Name' }}</th>
                <th style="width: 11%;">Mitglieds-Nr.</th>
                <th style="width: 18%;">Mitgliedschaft</th>
                <th style="width: 20%;">Kontakt</th>
                <th style="width: 16%;">Adresse</th>
                @if($showContribution)
                    <th style="width: 10%;">Beitrag</th>
                @endif
                @if($showPaymentMethod)
                    <th style="width: 10%;">Zahlungsart</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($members as $member)
                @php
                    $display = $displayMember($member);
                    $amount = $member->membership_amount ?? $member->membership?->amount;
                    $interval = $member->membership_interval ?: $member->membership?->interval;
                    $address = array_filter([
                        $member->street,
                        trim(($member->zip ?? '') . ' ' . ($member->city ?? '')),
                    ]);
                @endphp
                <tr>
                    <td>
                        <div class="name">{{ $display['primary'] }}</div>
                        @if($display['secondary'])
                            <div class="muted">{{ $display['secondary'] }}</div>
                        @endif
                    </td>
                    <td>{{ $member->member_id ?: '—' }}</td>
                    <td>{{ $member->membership?->name ?: '—' }}</td>
                    <td>
                        @if($member->email)
                            <div>{{ $member->email }}</div>
                        @endif
                        @if($member->mobile)
                            <div class="muted">{{ $member->mobile }}</div>
                        @endif
                    </td>
                    <td>
                        @if(! empty($address))
                            {!! implode('<br>', array_map('e', $address)) !!}
                        @else
                            —
                        @endif
                    </td>
                    @if($showContribution)
                        <td class="amount">
                            @if($amount)
                                {{ number_format((float) $amount, 2, ',', '.') }} €
                                @if($interval)
                                    <div class="muted">{{ $interval }}</div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                    @endif
                    @if($showPaymentMethod)
                        <td>{{ $member->paymentMethodLabel() }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
