@extends('layouts.public', [
    'title' => $event->title,
    'bodyClass' => (($isEmbed ?? false) ? 'bg-transparent' : 'min-h-screen bg-slate-50') . ' text-slate-900',
    'robots' => ($isEmbed ?? false) ? 'noindex, nofollow' : null,
])

@section('content')
    @include('events.partials.show-content', [
        'event' => $event,
        'isPublicPreview' => true,
        'isEmbed' => $isEmbed ?? false,
        'publicListUrl' => $publicListUrl ?? null,
    ])
@endsection
