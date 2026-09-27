@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div>
        <a href="{{ route('app-news.index') }}" class="text-sm font-semibold text-slate-500 transition hover:text-slate-900">Zurück zu App-News</a>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">App-News bearbeiten</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500">Änderungen erscheinen nach dem Speichern in der App, sofern die Mitteilung veröffentlicht ist.</p>
    </div>

    @include('app-news.form', [
        'item' => $item,
        'statusOptions' => $statusOptions,
        'action' => route('app-news.update', $item),
        'method' => 'PUT',
        'submitLabel' => 'Änderungen speichern',
    ])
</div>
@endsection
