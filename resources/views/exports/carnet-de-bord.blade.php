<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #111827; }
        h1 { font-size: 14px; text-align: center; margin: 0 0 8px; letter-spacing: 1px; }
        .id { width: 100%; margin-bottom: 8px; border-collapse: collapse; }
        .id td { padding: 2px 4px; font-size: 9px; }
        .id b { color: #374151; }
        table.carnet { width: 100%; border-collapse: collapse; }
        table.carnet th, table.carnet td { border: 1px solid #6b7280; padding: 3px 4px; vertical-align: middle; }
        table.carnet th { background: #e5e7eb; text-align: center; font-size: 7px; }
        td.n { text-align: right; }
        td.c { text-align: center; }
        tr.total td { font-weight: bold; background: #f3f4f6; }
        .visa { width: 11%; }
        .foot { margin-top: 6px; color: #6b7280; font-size: 7px; }
    </style>
</head>
<body>
    <h1>CARNET DE BORD DU VÉHICULE</h1>
    <table class="id">
        <tr>
            <td><b>Région sanitaire :</b> {{ $region }}</td>
            <td><b>District sanitaire :</b> {{ $district }}</td>
            <td><b>Mois :</b> {{ \Illuminate\Support\Str::ucfirst($month->translatedFormat('F Y')) }}</td>
        </tr>
        <tr>
            <td><b>Immatriculation :</b> {{ $vehicle->immatriculation }}</td>
            <td><b>Marque :</b> {{ trim($vehicle->marque.' '.$vehicle->modele) ?: '—' }}</td>
            <td><b>Carburant :</b> {{ \App\Models\FuelPrice::TYPES[$vehicle->type_carburant] ?? ($vehicle->type_carburant ?: '—') }}
                · <b>1ère mise en circulation :</b> {{ $vehicle->annee_circulation ?: '—' }}</td>
        </tr>
    </table>

    <table class="carnet">
        <thead>
            <tr>
                <th rowspan="2">Date<br>jj/mm/aaaa</th>
                <th rowspan="2">Chauffeur<br>(noms / prénoms)</th>
                <th colspan="3">DÉPART</th>
                <th colspan="3">ARRIVÉE</th>
                <th rowspan="2">Distance<br>parcourue<br>(B − A)</th>
                <th rowspan="2">Motif</th>
                <th rowspan="2">Carburant<br>(litres)</th>
                <th rowspan="2">Montant<br>(FCFA)</th>
                <th rowspan="2" class="visa">Visa du site visité<br>date, heure, signature</th>
            </tr>
            <tr>
                <th>Lieu</th><th>Heure</th><th>Km (A)</th>
                <th>Lieu</th><th>Heure</th><th>Km (B)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td class="c">{{ $r['date'] }}</td>
                    <td>{{ $r['chauffeur'] }}</td>
                    <td>{{ $r['lieu_depart'] }}</td>
                    <td class="c">{{ $r['heure_depart'] }}</td>
                    <td class="n">{{ $r['km_depart'] !== null ? number_format($r['km_depart'], 0, ',', ' ') : '' }}</td>
                    <td>{{ $r['lieu_arrivee'] }}</td>
                    <td class="c">{{ $r['heure_arrivee'] }}</td>
                    <td class="n">{{ $r['km_arrivee'] !== null ? number_format($r['km_arrivee'], 0, ',', ' ') : '' }}</td>
                    <td class="n">{{ $r['distance'] !== null ? number_format($r['distance'], 0, ',', ' ') : '' }}</td>
                    <td>{{ $r['motif'] }}</td>
                    <td class="n">{{ $r['litres'] !== null ? number_format($r['litres'], 1, ',', ' ') : '' }}</td>
                    <td class="n">{{ $r['montant'] !== null ? number_format($r['montant'], 0, ',', ' ') : '' }}</td>
                    <td class="visa"></td>
                </tr>
            @empty
                <tr><td colspan="13" class="c" style="padding:12px">Aucune sortie enregistrée ce mois-ci.</td></tr>
            @endforelse
            @for ($i = count($rows); $i < 12 && count($rows) > 0; $i++)
                <tr>@for ($j = 0; $j < 13; $j++)<td>&nbsp;</td>@endfor</tr>
            @endfor
            <tr class="total">
                <td colspan="8" class="n">TOTAL DU MOIS</td>
                <td class="n">{{ number_format($totals['distance'], 0, ',', ' ') }}</td>
                <td></td>
                <td class="n">{{ $totals['litres'] ? number_format($totals['litres'], 1, ',', ' ') : '' }}</td>
                <td class="n">{{ $totals['montant'] ? number_format($totals['montant'], 0, ',', ' ') : '' }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
    <div class="foot">Généré par Logimaster le {{ now()->format('d/m/Y H:i') }}. Le visa du site visité se complète à la main sur la version imprimée.</div>
</body>
</html>
