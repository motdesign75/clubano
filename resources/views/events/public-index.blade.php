@extends('layouts.public', [
    'title' => 'Kommende Veranstaltungen',
    'bodyClass' => ($isEmbed ? 'bg-transparent' : 'bg-[#f4f0e8]') . ' text-slate-900',
    'robots' => $isEmbed ? 'noindex, nofollow' : null,
])

@section('content')
    @include('events.partials.public-list-content', compact('tenant', 'categories', 'selectedCategorySlug', 'events', 'groupedEvents', 'embedUrl', 'publicListUrl', 'isEmbed'))
@endsection
