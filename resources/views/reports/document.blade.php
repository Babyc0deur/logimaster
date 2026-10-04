<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 60px 40px 50px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #111827; }
        .cover { border-bottom: 3px solid #2563eb; padding-bottom: 10px; margin-bottom: 16px; }
        .brand { color: #2563eb; font-size: 11px; font-weight: bold; letter-spacing: 1px; }
        h1 { font-size: 21px; margin: 4px 0 2px; }
        .sub { color: #6b7280; font-size: 11px; }
        h2 { font-size: 13.5px; color: #1d4ed8; border-bottom: 1px solid #dbeafe; padding-bottom: 3px; margin: 18px 0 8px; page-break-after: avoid; }
        h3 { font-size: 10.5px; margin: 10px 0 4px; page-break-after: avoid; }
        .kpis { width: 100%; border-collapse: separate; border-spacing: 5px; margin: 0 -5px 6px; }
        .kpi { background: #f3f4f6; border-left: 3px solid #2563eb; padding: 5px 7px; }
        .kpi .l { color: #6b7280; font-size: 8px; }
        .kpi .v { font-size: 12.5px; font-weight: bold; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.data th { background: #2563eb; color: #fff; text-align: left; padding: 3px 5px; font-size: 8.5px; }
        table.data td { padding: 2.5px 5px; border-bottom: 1px solid #e5e7eb; font-size: 8.5px; }
        table.data tr:nth-child(even) td { background: #f9fafb; }
        ul { margin: 4px 0 4px 16px; padding: 0; }
        li { margin-bottom: 3px; }
        .def { color: #6b7280; font-size: 8.5px; font-style: italic; margin-bottom: 4px; }
        .footer { position: fixed; bottom: -30px; left: 0; right: 0; text-align: center; color: #9ca3af; font-size: 8px; }
    </style>
</head>
<body>
    <div class="footer">LogiMaster Pro — {{ $doc->title }} — {{ $doc->scope }} — généré le {{ $generatedAt->format('d/m/Y H:i') }}</div>

    <div class="cover">
        <div class="brand">LOGIMASTER PRO · DDKM</div>
        <h1>{{ $doc->title }}</h1>
        <div class="sub">{{ $doc->subtitle }}</div>
    </div>

    @foreach ($doc->sections as $section)
        <h2>{{ $section['heading'] }}</h2>

        @if ($section['kpis'])
            <table class="kpis"><tr>
                @foreach ($section['kpis'] as $k)
                    <td class="kpi"><div class="l">{{ $k['label'] }}</div><div class="v">{{ $k['value'] }}</div></td>
                    @if ($loop->iteration % 4 === 0 && ! $loop->last)</tr><tr>@endif
                @endforeach
            </tr></table>
        @endif

        @if ($section['text'])
            @if (count($section['text']) === 1 && $section['heading'] !== 'Commentaires et recommandations')
                <div class="def">{{ $section['text'][0] }}</div>
            @else
                <ul>@foreach ($section['text'] as $line)<li>{{ $line }}</li>@endforeach</ul>
            @endif
        @endif

        @foreach ($section['tables'] as $table)
            <h3>{{ $table['title'] }}</h3>
            <table class="data">
                <thead><tr>@foreach ($table['headers'] as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($table['rows'] as $row)
                        <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @endforeach
</body>
</html>
