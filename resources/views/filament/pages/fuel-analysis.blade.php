<x-filament-panels::page>
    <x-filament::section>
        <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
            <label for="periode" style="font-weight:600">Mois</label>
            <select id="periode" wire:model.live="periode" style="padding:.4rem .6rem;border:1px solid #d1d5db;border-radius:.5rem;background:transparent">
                @foreach ($months as $value => $label)
                    <option value="{{ $value }}">{{ ucfirst($label) }}</option>
                @endforeach
            </select>
            <span style="color:#6b7280;font-size:.85rem">Alerte si la consommation dépasse la théorique de plus de {{ $seuil }} %.</span>
        </div>
    </x-filament::section>

    <x-filament::section heading="Par véhicule : consommation réelle vs théorique">
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:.85rem">
                <thead>
                    <tr style="text-align:left;border-bottom:1px solid #e5e7eb">
                        <th style="padding:.4rem">Véhicule</th><th>Distance</th><th>Litres</th><th>Réelle (L/100)</th>
                        <th>Théorique (L/100)</th><th>Écart</th><th>Coût/km</th><th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vehicles as $row)
                        <tr style="border-bottom:1px solid #f3f4f6">
                            <td style="padding:.4rem;font-weight:600">{{ $row['vehicle']->immatriculation }}</td>
                            <td>{{ number_format($row['km'], 0, ',', ' ') }} km</td>
                            <td>{{ number_format($row['litres'], 1, ',', ' ') }} L</td>
                            <td>{{ $row['reelle'] ?? '—' }}</td>
                            <td>{{ $row['theorique'] ?? '—' }}</td>
                            <td style="font-weight:600;color:{{ $row['surconsommation'] ? '#dc2626' : '#059669' }}">
                                {{ $row['ecart'] !== null ? sprintf('%+.1f %%', $row['ecart']) : '—' }}
                            </td>
                            <td>{{ $row['cout_km'] !== null ? number_format($row['cout_km'], 0, ',', ' ').' FCFA' : '—' }}</td>
                            <td>
                                @if ($row['surconsommation'])
                                    <x-filament::badge color="danger">Surconsommation</x-filament::badge>
                                @elseif ($row['ecart'] !== null)
                                    <x-filament::badge color="success">Conforme</x-filament::badge>
                                @else
                                    <x-filament::badge color="gray">Données insuffisantes</x-filament::badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" style="padding:1rem;text-align:center">Aucun véhicule.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section heading="Par motif de déplacement">
        <table style="width:100%;border-collapse:collapse;font-size:.85rem">
            <thead><tr style="text-align:left;border-bottom:1px solid #e5e7eb"><th style="padding:.4rem">Motif</th><th>Litres</th><th>Coût</th><th>Part</th></tr></thead>
            <tbody>
                @php $totalLitres = max(0.01, $motifs->sum('litres')); @endphp
                @forelse ($motifs as $motif)
                    <tr style="border-bottom:1px solid #f3f4f6">
                        <td style="padding:.4rem;font-weight:600">{{ $motif['label'] }}</td>
                        <td>{{ number_format($motif['litres'], 1, ',', ' ') }} L</td>
                        <td>{{ number_format($motif['cout'], 0, ',', ' ') }} FCFA</td>
                        <td style="width:35%">
                            <div style="background:#e5e7eb;border-radius:4px;height:10px"><div style="background:#2563eb;height:10px;border-radius:4px;width:{{ round($motif['litres'] / $totalLitres * 100) }}%"></div></div>
                            {{ round($motif['litres'] / $totalLitres * 100) }} %
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="padding:1rem;text-align:center">Aucun ravitaillement sur la période.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>

    <x-filament::section heading="Comparaison mensuelle et tendance (12 mois)">
        <table style="width:100%;border-collapse:collapse;font-size:.85rem">
            <thead><tr style="text-align:left;border-bottom:1px solid #e5e7eb"><th style="padding:.4rem">Mois</th><th>Litres</th><th>Coût</th><th>Réelle</th><th>Théorique</th><th style="width:35%">Tendance (L/100 km)</th></tr></thead>
            <tbody>
                @foreach ($series as $i => $s)
                    @php $prev = $i > 0 ? $series[$i - 1]['reelle'] : null; @endphp
                    <tr style="border-bottom:1px solid #f3f4f6">
                        <td style="padding:.4rem;font-weight:600">{{ ucfirst($s['label']) }}</td>
                        <td>{{ number_format($s['litres'], 0, ',', ' ') }} L</td>
                        <td>{{ number_format($s['cout'], 0, ',', ' ') }}</td>
                        <td>
                            {{ $s['reelle'] ?? '—' }}
                            @if ($prev !== null && $s['reelle'] !== null)
                                <span style="color:{{ $s['reelle'] > $prev ? '#dc2626' : '#059669' }}">{{ $s['reelle'] > $prev ? '▲' : '▼' }}</span>
                            @endif
                        </td>
                        <td>{{ $s['theorique'] ?? '—' }}</td>
                        <td>
                            @if ($s['reelle'] !== null)
                                <div style="background:#2563eb;height:7px;border-radius:4px;width:{{ round($s['reelle'] / $maxConso * 100) }}%"></div>
                                @if ($s['theorique'])<div style="background:#10b981;height:7px;border-radius:4px;margin-top:2px;width:{{ round($s['theorique'] / $maxConso * 100) }}%"></div>@endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p style="margin-top:.5rem;font-size:.75rem;color:#6b7280">Bleu : consommation réelle · Vert : théorique. Projection de fin de mois disponible dans le tableau de bord carburant.</p>
    </x-filament::section>
</x-filament-panels::page>
