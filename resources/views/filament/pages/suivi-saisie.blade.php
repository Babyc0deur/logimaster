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
            <div style="{{ $metric }}"><div style="font-size:.75rem;opacity:.7">Taux de remplissage {{ $year }}</div><div style="font-size:1.3rem;font-weight:600;color:{{ $tauxAnnee >= 80 ? $green : ($tauxAnnee >= 50 ? '#d97706' : $red) }}">{{ $tauxAnnee }} %</div><div style="font-size:.7rem;opacity:.6">mois renseignés / mois écoulés</div></div>
        </div>

        <div class="ss-card" style="border:1px solid rgba(127,127,127,.28);border-radius:12px;overflow:hidden">
            <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;padding:10px 14px;border-bottom:1px solid rgba(127,127,127,.28)">
                <strong style="font-size:.95rem">Carte des districts sanitaires</strong>
                <select wire:model.live="mois" style="{{ $field }}" aria-label="Mois de la carte">
                    <option value="" style="color:#111">Toute l'année {{ $year }}</option>
                    @foreach ($months as $m => $date)
                        <option value="{{ $m }}" style="color:#111">{{ ucfirst($date->translatedFormat('F Y')) }}</option>
                    @endforeach
                </select>
                <select wire:model.live="region" style="{{ $field }}" aria-label="Région de la carte">
                    <option value="" style="color:#111">Toutes les régions</option>
                    @foreach ($regions as $id => $name)
                        <option value="{{ $id }}" style="color:#111">{{ $name }}</option>
                    @endforeach
                </select>
                <span style="font-size:.85rem"><b style="color:{{ $green }}">{{ $mapOk }}</b> district(s) renseigné(s) · <b style="color:{{ $red }}">{{ count($map) - $mapOk }}</b> non renseigné(s) · taux de remplissage <b>{{ $tauxCarte }} %</b></span>
                <span style="margin-left:auto;display:flex;gap:14px;font-size:.8rem">
                    <span><i style="display:inline-block;width:12px;height:12px;border-radius:3px;background:{{ $green }};vertical-align:-1px"></i> Renseigné</span>
                    <span><i style="display:inline-block;width:12px;height:12px;border-radius:3px;background:{{ $red }};vertical-align:-1px"></i> Non renseigné</span>
                    <span><i style="display:inline-block;width:16px;height:0;border-top:3px solid #ea580c;vertical-align:3px"></i> Limite de région</span>
                </span>
            </div>
            <div wire:key="ss-map-{{ md5(json_encode([$map, $region])) }}" wire:ignore x-data="{}"
                 x-init="
                    const data = @js($map);
                    const run = () => window.lmSaisieMap($refs.map, data, '{{ \App\Support\Brand::url('geo/ci-districts.json') }}', @js($regions[$region] ?? ''));   // adresse versionnée : le navigateur reprend le nouveau fichier
                    const boot = () => {
                        if (window.lmSaisieMap) return run();
                        const s = document.createElement('script'); s.src = '/js/lm-saisie-map.js?v={{ @filemtime(public_path('js/lm-saisie-map.js')) }}'; s.onload = run; document.head.appendChild(s);
                    };
                    if (window.L) { boot(); }
                    else {
                        if (!document.getElementById('leaflet-css')) {
                            const css = document.createElement('link'); css.id = 'leaflet-css'; css.rel = 'stylesheet'; css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'; document.head.appendChild(css);
                        }
                        const existing = document.getElementById('leaflet-js');
                        if (existing) { existing.addEventListener('load', boot); }
                        else { const js = document.createElement('script'); js.id = 'leaflet-js'; js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'; js.onload = boot; document.head.appendChild(js); }
                    }
                 ">
                <div x-ref="map" style="height:min(64vh, 620px);min-height:380px;z-index:0"></div>
            </div>
            <p style="font-size:.75rem;opacity:.75;padding:8px 14px;border-top:1px solid rgba(127,127,127,.28)">
                Limites officielles des 113 districts sanitaires ; les traits orange sont les limites des régions ; chaque point blanc est le chef-lieu du district. Fond de carte © contributeurs OpenStreetMap.
            </p>
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
                        <th class="ss-th" style="{{ $th }}">Taux</th>
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
                            <td style="padding:4px 8px;text-align:center;font-weight:600">{{ $elapsed > 0 ? round(100 * $r['renseignes'] / $elapsed) : 0 }} %</td>
                        </tr>
                    @empty
                        <tr><td colspan="17" style="padding:1.5rem;text-align:center;opacity:.7">Aucun district.</td></tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr style="border-top:2px solid rgba(127,127,127,.35)">
                            <td colspan="3" style="padding:6px 10px;font-weight:600">Districts ayant saisi</td>
                            @foreach ($perMonth as $m => $n)
                                <td style="padding:6px 2px;text-align:center;font-size:.75rem;font-weight:600">{{ $m > $elapsed ? '' : $n.'/'.$rows->count() }}</td>
                            @endforeach
                            <td></td><td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</x-filament-panels::page>
