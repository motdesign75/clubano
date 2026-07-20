@extends('layouts.public', [
    'title' => $event->title,
    'bodyClass' => 'min-h-screen bg-slate-50 text-slate-900',
])

@section('content')
    @include('events.partials.show-content', ['event' => $event, 'isPublicPreview' => true])
@endsection
