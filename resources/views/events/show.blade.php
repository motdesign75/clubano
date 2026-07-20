@extends('layouts.app')

@section('title', $event->title)

@section('content')
    @include('events.partials.show-content', ['event' => $event, 'isPublicPreview' => false])
@endsection
