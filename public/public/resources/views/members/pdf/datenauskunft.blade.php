<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Datenauskunft</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #000;
            line-height: 1.6;
        }

        h1 {
            font-size: 16px;
            margin-bottom: 10px;
        }

        .section {
            margin-bottom: 20px;
        }

        .label {
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        td {
            vertical-align: top;
            padding: 4px 6px;
        }

        .footer {
            font-size: 10px;
            margin-top: 30px;
            color: #555;
        }
    </style>
</head>
<body>

    <h1>Datenauskunft gemäß Art. 15 DSGVO</h1>

    <p>Sie erhalten hiermit eine Auskunft über die bei uns gespeicherten personenbezogenen Daten gemäß Artikel 15 der Datenschutz-Grundverordnung (DSGVO).</p>

    <div class="section">
        <h2>🧾 Persönliche Angaben</h2>
        <table>
            <tr><td class="label">Name:</td><td>{{ $member->salutation ?? '' }} {{ $member->title ?? '' }} {{ $member->first_name }} {{ $member->last_name }}</td></tr>
            <tr><td class="label">Geburtsdatum:</td><td>{{ $member->birthday }}</td></tr>
            <tr><td class="label">Mitgliedsnummer:</td><td>{{ $member->member_id }}</td></tr>
            <tr><td class="label">Eintritt:</td><td>{{ $member->entry_date }}</td></tr>
            <tr><td class="label">Austritt:</td><td>{{ $member->exit_date }}</td></tr>
            <tr><td class="label">Kündigungsdatum:</td><td>{{ $member->termination_date }}</td></tr>
        </table>
    </div>

    <div class="section">
        <h2>📬 Kontaktinformationen</h2>
        <table>
            <tr><td class="label">E-Mail:</td><td>{{ $member->email }}</td></tr>
            <tr><td class="label">Telefon (mobil):</td><td>{{ $member->mobile }}</td></tr>
            <tr><td class="label">Telefon (Festnetz):</td><td>{{ $member->landline }}</td></tr>
        </table>
    </div>

    <div class="section">
        <h2>🏠 Adresse</h2>
        <table>
            <tr><td class="label">Straße:</td><td>{{ $member->street }} {{ $member->address_addition }}</td></tr>
            <tr><td class="label">PLZ / Ort:</td><td>{{ $member->zip }} {{ $member->city }}</td></tr>
            <tr><td class="label">Land:</td><td>{{ $member->country }}</td></tr>
            <tr><td class="label">c/o:</td><td>{{ $member->care_of }}</td></tr>
        </table>
    </div>

    <div class="section">
        <h2>🧾 Zugehörigkeit</h2>
        <table>
            <tr>
                <td class="label">Mitgliedschaft:</td>
                <td>{{ $member->membership?->name ?? 'Keine Angabe' }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Diese Auskunft wurde automatisch auf Grundlage Ihrer gespeicherten Daten generiert.  
        Bei Rückfragen wenden Sie sich bitte an den Vorstand Ihres Vereins.  
        <br><br>
        Auskunft erstellt am {{ now()->format('d.m.Y') }}
    </div>

</body>
</html>
