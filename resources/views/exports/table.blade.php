<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111827; }
        h1 { font-size: 15px; margin: 0 0 2px; color: #2563eb; }
        p { margin: 0 0 10px; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #2563eb; color: #fff; text-align: left; padding: 4px 5px; }
        td { padding: 3px 5px; border-bottom: 1px solid #e5e7eb; }
        tr:nth-child(even) td { background: #f9fafb; }
    </style>
</head>
<body>
    <h1>LogiMaster Pro — {{ $title }}</h1>
    <p>Généré le {{ now()->format('d/m/Y H:i') }} · {{ count($rows) }} ligne(s)</p>
    <table>
        <thead><tr>@foreach ($headers as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
