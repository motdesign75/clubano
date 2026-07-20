<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12pt;
            margin: 0;
            padding: 2cm;
            @if($pdf_template)
                background-image: url('{{ public_path("storage/" . $pdf_template) }}');
                background-size: cover;
                background-repeat: no-repeat;
            @endif
        }
    </style>
</head>
<body>
    <p>{{ $city }}, den {{ $date }}</p>
    <p>Sehr geehrte Damen und Herren,</p>
    <p>dies ist ein Testbrief auf offiziellem Briefpapier von {{ $name }}.</p>
</body>
</html>
