<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>{{ $title ?? 'Dokument hochladen' }}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    html,body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Arial;margin:0;padding:0}
    .wrap{max-width:800px;margin:40px auto;padding:24px}
    label{display:block;margin:.75rem 0 .25rem;font-weight:600}
    input[type=file]{width:100%;padding:.5rem .6rem;border:1px solid #d4d4d8;border-radius:.5rem;background:#fff}
    .actions{margin-top:1rem;display:flex;gap:.75rem}
    .btn{display:inline-flex;gap:.5rem;background:#18181b;color:#fff;padding:.5rem .75rem;border-radius:.5rem;text-decoration:none}
    .btn.secondary{background:#e5e7eb;color:#111827}
  </style>
</head>
<body>
<div class="wrap">
  <a href="{{ route('projects.show', $project) }}" style="text-decoration:none">← Zurück zum Projekt</a>
  <h1 style="margin:8px 0 12px">Dokument hochladen – {{ $project->name }}</h1>

  <form method="post" action="{{ route('projects.documents.store', $project) }}" enctype="multipart/form-data">
    @csrf

    <label for="file">Datei *</label>
    <input id="file" name="file" type="file" required>

    <div class="actions">
      <button class="btn" type="submit">Hochladen</button>
      <a class="btn secondary" href="{{ route('projects.show', $project) }}">Abbrechen</a>
    </div>
  </form>
</div>
</body>
</html>
