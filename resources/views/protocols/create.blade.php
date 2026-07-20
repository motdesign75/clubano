@extends('layouts.app')

@section('title', 'Neues Protokoll')

@push('head')
    <link rel="stylesheet" href="https://unpkg.com/trix@2.0.0/dist/trix.css">
@endpush

@push('scripts')
    <script src="https://unpkg.com/trix@2.0.0/dist/trix.umd.min.js"></script>
@endpush

@section('content')
    @include('protocols.partials.form', ['members' => $members])
@endsection
