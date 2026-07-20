<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Feedback</title>
</head>
<body style="font-family: sans-serif; color: #333;">
    <h2>🗣️ Neues Feedback aus Clubano</h2>

    <p><strong>Kategorie:</strong> {{ $feedback->category ?? '-' }}</p>
    <p><strong>Seite:</strong> {{ $feedback->view ?? '-' }}</p>
    <p><strong>URL:</strong> {{ $feedback->url ?? '-' }}</p>

    <p><strong>Nachricht:</strong></p>
    <pre style="background: #f4f4f4; padding: 1rem; border-radius: 8px;">{{ $feedback->message }}</pre>
</body>
</html>
