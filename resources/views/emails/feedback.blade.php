<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Feedback</title>
</head>
<body style="font-family: sans-serif; color: #333;">
    <h2>🗣️ Neues Feedback aus Clubano</h2>

    <p><strong>Von:</strong> {{ $feedback->user->name ?? 'Unbekannter Nutzer' }} (ID: {{ $feedback->user_id }})</p>
    <p><strong>Kategorie:</strong> {{ $feedback->category ?? 'Allgemein' }}</p>
    <p><strong>Seite:</strong> {{ $feedback->view ?? 'Unbekannt' }}</p>
    <p><strong>URL:</strong> {{ $feedback->url ?? 'Unbekannt' }}</p>
    <p><strong>Seitentitel:</strong> {{ $feedback->page_title ?? 'Unbekannt' }}</p>
    <p><strong>Geraet:</strong> {{ $feedback->device_label ?? 'Unbekannt' }}</p>
    <p><strong>Viewport:</strong> {{ $feedback->viewport ?? 'Unbekannt' }}</p>

    <p><strong>Nachricht:</strong></p>
    <pre style="background: #f4f4f4; padding: 1rem; border-radius: 8px;">{{ $feedback->message }}</pre>

    @if($feedback->screenshot_path)
        <p><strong>Screenshot:</strong></p>
        <p>Der Screenshot ist dieser Mail als Anhang beigefuegt.</p>
    @endif
</body>
</html>
