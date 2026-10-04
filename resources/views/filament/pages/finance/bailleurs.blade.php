<x-filament-widgets::widget>
    <x-filament::section :heading="'Dépenses par bailleur — ' . ucfirst($month->translatedFormat('F Y'))"
                         description="Coûts rattachés aux véhicules financés par chaque bailleur (carburant, maintenance, frais).">
        @if (count($rows))
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:.85rem">
                    <thead>
                        <tr style="text-align:left;border-bottom:1px solid #e5e7eb">
                            <th style="padding:.4rem">Bailleur</th><th>Véhicules</th><th>Carburant</th><th>Maintenance</th><th>Autres frais</th>
                            <th>Total</th><th>Budget alloué</th><th>Consommé</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr style="border-bottom:1px solid #f3f4f6">
                                <td style="padding:.4rem;font-weight:600">{{ $r['bailleur'] }}</td>
                                <td>{{ $r['vehicules'] }}</td>
                                <td>{{ number_format($r['carburant'], 0, ',', ' ') }}</td>
                                <td>{{ number_format($r['maintenance'], 0, ',', ' ') }}</td>
                                <td>{{ number_format($r['autres'], 0, ',', ' ') }}</td>
                                <td style="font-weight:700">{{ number_format($r['total'], 0, ',', ' ') }}</td>
                                <td>{{ $r['alloue'] > 0 ? number_format($r['alloue'], 0, ',', ' ') : '—' }}</td>
                                <td>
                                    @if ($r['pct'] !== null)
                                        <x-filament::badge :color="$r['pct'] > 100 ? 'danger' : ($r['pct'] >= 80 ? 'warning' : 'success')">{{ round($r['pct']) }} %</x-filament::badge>
                                    @else — @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p style="font-size:.85rem;color:#6b7280">Aucun véhicule dans ce périmètre.</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
