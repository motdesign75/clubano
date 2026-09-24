<p>Hallo {{ $recipientName ?: '' }},</p>

@if(($addressStyle ?? 'sie') === 'du')
    <p>
        wir möchten sicherstellen, dass die bei uns gespeicherten Stammdaten aktuell sind.
        Bitte prüfe deine Angaben über den folgenden Link:
    </p>
@else
    <p>
        wir möchten sicherstellen, dass die bei uns gespeicherten Stammdaten aktuell sind.
        Bitte prüfen Sie Ihre Angaben über den folgenden Link:
    </p>
@endif

@if($message)
    <p>{!! nl2br(e($message)) !!}</p>
@endif

<p style="margin: 24px 0;">
    <a href="{{ $url }}" style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:999px;font-weight:700;">
        Stammdaten prüfen
    </a>
</p>

<p>
    @if(($addressStyle ?? 'sie') === 'du')
        Eingereichte Änderungen werden nicht automatisch übernommen. Wir prüfen deine Angaben zunächst intern und aktualisieren die Stammdaten anschließend.
    @else
        Eingereichte Änderungen werden nicht automatisch übernommen. Wir prüfen Ihre Angaben zunächst intern und aktualisieren die Stammdaten anschließend.
    @endif
</p>

<p>Viele Grüße<br>{{ $tenant->name }}</p>
