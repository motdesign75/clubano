@if($isPdf ?? false)
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Kassenprüfung</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #0f172a; }
        h1 { font-size: 20pt; margin: 0 0 4px; }
        h2 { font-size: 13pt; margin: 18px 0 8px; }
        .screen-only { display: none; }
        .audit-card { border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px; margin-bottom: 10px; }
        .audit-grid { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .audit-grid th, .audit-grid td { border-bottom: 1px solid #e2e8f0; padding: 6px; text-align: left; vertical-align: top; }
        .audit-grid th { background: #f8fafc; font-size: 7pt; text-transform: uppercase; color: #64748b; }
        .text-right { text-align: right; }
        .muted { color: #64748b; }
        .amount-positive { color: #047857; font-weight: bold; }
        .amount-negative { color: #b91c1c; font-weight: bold; }
    </style>
</head>
<body>
    @include('transactions.partials.audit-content')
</body>
</html>
@else
@extends('layouts.app')

@section('title', 'Kassenprüfung')

@section('content')
    @include('transactions.partials.audit-content')
@endsection
@endif
