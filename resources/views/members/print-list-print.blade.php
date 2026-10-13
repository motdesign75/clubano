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
            background: #e5e7eb;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8.4pt;
            line-height: 1.28;
        }

        .print-page {
            width: 297mm;
            min-height: 210mm;
            margin: 0 auto;
            padding: 12mm;
            background: #ffffff;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.16);
        }

        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            width: 297mm;
            margin: 10mm auto 5mm;
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
            gap: 10mm;
            margin-bottom: 5mm;
            padding-bottom: 3mm;
            border-bottom: 1.5pt solid #1f3d63;
        }

        h1 {
            margin: 0;
            color: #0f172a;
            font-size: 17pt;
            line-height: 1.1;
        }

        .meta {
            margin-top: 2mm;
            color: #64748b;
            font-size: 7.8pt;
            line-height: 1.4;
        }

        .tenant {
            max-width: 90mm;
            color: #334155;
            font-size: 9pt;
            font-weight: 700;
            text-align: right;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
            table-layout: fixed;
        }

        thead {
            display: table-header-group;
        }

        tfoot {
            display: table-row-group;
        }

        tr {
            page-break-inside: avoid;
        }

        th {
            padding: 4px 5px;
            border: 0.6pt solid #1e3a8a;
            background: #243f87;
            color: #ffffff;
            font-size: 6.9pt;
            letter-spacing: 0.04em;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
        }

        td {
            padding: 4px 5px;
            border: 0.6pt solid #cbd5e1;
            vertical-align: top;
            overflow-wrap: anywhere;
            word-break: normal;
        }

        tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .name {
            font-weight: 700;
        }

        .muted {
            color: #64748b;
            font-size: 7.2pt;
            line-height: 1.35;
        }

        .amount {
            white-space: nowrap;
            text-align: right;
        }

        .col-name {
            width: 24%;
        }

        .col-member-id {
            width: 9%;
        }

        .col-membership {
            width: 12%;
        }

        .col-contact {
            width: 23%;
        }

        .col-address {
            width: 17%;
        }

        .col-contribution {
            width: 8%;
        }

        .col-payment {
            width: 7%;
        }

        .member-list.compact .col-name {
            width: 22%;
        }

        .member-list.compact .col-contact {
            width: 21%;
        }

        .member-list.compact .col-address {
            width: 16%;
        }

        .member-list.compact .col-contribution {
            width: 8%;
        }

        .member-list.compact .col-payment {
            width: 12%;
        }

        .print-footer {
            margin-top: 4mm;
            color: #64748b;
            font-size: 7pt;
            text-align: right;
        }

        @media print {
            html,
            body {
                width: 297mm;
                min-height: 210mm;
                background: #ffffff;
            }

            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .toolbar {
                display: none;
            }

            .print-page {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
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

    <main class="print-page">
        <header class="heading">
            <div>
                <h1>Mitgliederliste</h1>
                <div class="meta">Erstellt am {{ $generatedAt }} · {{ $members->count() }} Mitglied(er) · Darstellung: {{ $displayLabel }}</div>
            </div>
            <div class="tenant">{{ $tenantName }}</div>
        </header>

        <table class="member-list @if($showContribution && $showPaymentMethod) compact @endif">
            <colgroup>
                <col class="col-name">
                <col class="col-member-id">
                <col class="col-membership">
                <col class="col-contact">
                <col class="col-address">
                @if($showContribution)
                    <col class="col-contribution">
                @endif
                @if($showPaymentMethod)
                    <col class="col-payment">
                @endif
            </colgroup>
            <thead>
                <tr>
                    <th>{{ $isOrganizationMode ? 'Firma / Organisation' : 'Name' }}</th>
                    <th>Mitglieds-Nr.</th>
                    <th>Mitgliedschaft</th>
                    <th>Kontakt</th>
                    <th>Adresse</th>
                    @if($showContribution)
                        <th>Beitrag</th>
                    @endif
                    @if($showPaymentMethod)
                        <th>Zahlungsart</th>
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

        <div class="print-footer">DIN A4 quer · {{ $tenantName }}</div>
    </main>
</body>
</html>
