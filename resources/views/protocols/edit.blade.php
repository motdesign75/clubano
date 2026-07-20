@extends('layouts.app')

@section('title', 'Protokoll bearbeiten')

@push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/trix@2.0.0/dist/trix.css">
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/trix@2.0.0/dist/trix.umd.min.js"></script>
@endpush

@section('content')
    @include('protocols.partials.form', ['protocol' => $protocol, 'members' => $members, 'selected' => $selected])
@endsection
