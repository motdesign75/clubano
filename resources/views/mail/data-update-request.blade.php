<p>Hallo {{ $recipientName ?: '' }},</p>

<p>
    wir möchten sicherstellen, dass die bei uns gespeicherten Stammdaten aktuell sind.
    Bitte prüfen Sie Ihre Angaben über den folgenden Link:
</p>

@if($message)
    <p>{!! nl2br(e($message)) !!}</p>
@endif

<p style="margin: 24px 0;">
    <a href="{{ $url }}" style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:999px;font-weight:700;">
        Stammdaten prüfen
    </a>
</p>

<p>
    Eingereichte Änderungen werden nicht automatisch übernommen. Wir prüfen die Angaben zunächst intern und aktualisieren die Stammdaten anschließend.
</p>

<p>Viele Grüße<br>{{ $tenant->name }}</p>
