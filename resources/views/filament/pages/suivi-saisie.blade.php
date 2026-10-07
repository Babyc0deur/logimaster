@php
    $green = '#16a34a';
    $red = '#dc2626';
    $field = 'border:1px solid rgba(127,127,127,.35);border-radius:8px;padding:6px 10px;background:transparent;color:inherit;font-size:.875rem';
    $th = 'padding:6px 4px;font-size:.75rem;font-weight:600;text-align:center;white-space:nowrap;position:sticky;top:0;z-index:1';
    $metric = 'background:rgba(127,127,127,.09);border-radius:10px;padding:10px 14px';
@endphp

<x-filament-panels::page>
    <style>.ss-th{background:#f9fafb}.dark .ss-th{background:#18181b}</style>
    <div style="display:flex;flex-direction:column;gap:1rem">
        <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
            <select wire:model.live="annee" style="{{ $field }}" aria-label="Année">
                @foreach ($years as $y)
                    <option value="{{ $y }}" style="color:#111">{{ $y }}</option>
                @endforeach
            </select>
            <select wire:model.live="region" style="{{ $field }}" aria-label="Région">
                <option value="" style="color:#111">Toutes les régions</option>
                @foreach ($regions as $id => $name)
                    <option value="{{ $id }}" style="color:#111">{{ $name }}</option>
                @endforeach
            </select>
            <input type="search" wire:model.live.debounce.400ms="recherche" placeholder="Rechercher un district…" style="{{ $field }};min-width:200px" />
            <label style="display:flex;gap:6px;align-items:center;font-size:.875rem">
                <input type="checkbox" wire:model.live="manquants" /> Seulement les districts en retard
            </label>
            <span wire:loading style="font-size:.8rem;opacity:.7">Chargement…</span>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px">
            <div style="{{ $metric }}"><div style="font-size:.75rem;opacity:.7">Districts</div><div style="font-size:1.3rem;font-weight:600">{{ $total }}</div></div>
            <div style="{{ $metric }}"><div style="font-size:.75rem;opacity:.7">À jour ({{ $elapsed }} mois écoulé{{ $elapsed > 1 ? 's' : '' }})</div><div style="font-size:1.3rem;font-weight:600;color:{{ $green }}">{{ $complets }}</div></div>
            <div style="{{ $metric }}"><div style="font-size:.75rem;opacity:.7">Aucune saisie en {{ $year }}</div><div style="font-size:1.3rem;font-weight:600;color:{{ $red }}">{{ $aucun }}</div></div>
        </div>

        <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:.8rem;opacity:.85">
            <span><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:{{ $green }};vertical-align:-1px"></span> Renseigné (au moins une sortie de véhicule saisie)</span>
            <span><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:{{ $red }};vertical-align:-1px"></span> Non renseigné</span>
            <span><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:rgba(127,127,127,.25);vertical-align:-1px"></span> Mois à venir</span>
        </div>

        <div style="overflow:auto;max-height:70vh;border:1px solid rgba(127,127,127,.28);border-radius:12px">
            <table style="width:100%;border-collapse:collapse;font-size:.85rem">
                <thead>
                    <tr>
                        <th class="ss-th" style="{{ $th }};text-align:left;padding-left:10px">N°</th>
                        <th class="ss-th" style="{{ $th }};text-align:left">District</th>
                        <th class="ss-th" style="{{ $th }};text-align:left">Région</th>
                        @foreach ($months as $m => $date)
                            <th class="ss-th" style="{{ $th }}">{{ ucfirst($date->translatedFormat('M')) }}</th>
                        @endforeach
                        <th class="ss-th" style="{{ $th }}">Mois saisis</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $i => $r)
                        <tr style="border-top:1px solid rgba(127,127,127,.18)">
                            <td style="padding:4px 10px;opacity:.6">{{ $i + 1 }}</td>
                            <td style="padding:4px 6px;font-weight:600;white-space:nowrap">{{ $r['name'] }}</td>
                            <td style="padding:4px 6px;opacity:.75;white-space:nowrap">{{ $r['region'] }}</td>
                            @foreach ($months as $m => $date)
                                @php
                                    $n = $r['mois'][$m] ?? 0;
                                    $futur = $m > $elapsed;
                                    $bg = $n ? $green : ($futur ? 'rgba(127,127,127,.18)' : $red);
                                    $tip = $date->translatedFormat('F Y').' : '.($n ? $n.' sortie'.($n > 1 ? 's' : '').' saisie'.($n > 1 ? 's' : '') : ($futur ? 'mois à venir' : 'aucune saisie'));
                                @endphp
                                <td style="padding:3px 2px;text-align:center">
                                    <div title="{{ $tip }}" style="margin:auto;width:30px;height:22px;border-radius:5px;background:{{ $bg }};color:#fff;font-size:.7rem;line-height:22px">{{ $n ?: '' }}</div>
                                </td>
                            @endforeach
                            <td style="padding:4px 8px;text-align:center;font-weight:600;color:{{ $r['renseignes'] >= $elapsed ? $green : $red }}">{{ $r['renseignes'] }} / {{ $elapsed }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="16" style="padding:1.5rem;text-align:center;opacity:.7">Aucun district.</td></tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr style="border-top:2px solid rgba(127,127,127,.35)">
                            <td colspan="3" style="padding:6px 10px;font-weight:600">Districts ayant saisi</td>
                            @foreach ($perMonth as $m => $n)
                                <td style="padding:6px 2px;text-align:center;font-size:.75rem;font-weight:600">{{ $m > $elapsed ? '' : $n.'/'.$rows->count() }}</td>
                            @endforeach
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</x-filament-panels::page>
