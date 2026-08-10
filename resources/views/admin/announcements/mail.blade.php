<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="margin:0; padding:0; background:#f3f4f6; font-family:Arial, sans-serif; color:#1f2937;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6; padding:24px;">
    <tr>
        <td align="center">
            <table width="640" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #e5e7eb;">
                <tr>
                    <td style="background:#0f172a; color:white; padding:24px;">
                        <div style="font-size:12px; letter-spacing:2px; text-transform:uppercase; color:#93c5fd; font-weight:bold;">Clubano Update</div>
                        <div style="font-size:24px; line-height:32px; font-weight:bold; margin-top:8px;">{{ $announcement->subject }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px; font-size:15px; line-height:24px; color:#334155;">
                        {!! $bodyHtml ?? $announcement->body_html !!}

                        @if($announcement->cta_label && $announcement->cta_url)
                            <p style="margin-top:28px;">
                                <a href="{{ $ctaUrl ?? $announcement->cta_url }}" style="display:inline-block; background:#2563eb; color:#ffffff; text-decoration:none; border-radius:10px; padding:12px 18px; font-weight:bold;">
                                    {{ $announcement->cta_label }}
                                </a>
                            </p>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="background:#f8fafc; padding:18px 28px; font-size:12px; line-height:18px; color:#64748b;">
                        Diese Betreiber-Mitteilung richtet sich an Vereinsadmins von Clubano.
                        @if($tenant)
                            <br>Verein: <strong>{{ $tenant->name }}</strong>
                        @endif
                        <br>Clubano · Vereine einfach verwalten
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
