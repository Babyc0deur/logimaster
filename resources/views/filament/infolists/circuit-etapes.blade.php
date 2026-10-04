@php
    $circuit = $getRecord()->loadMissing(['espc', 'district:id,name']);
    $rows = $circuit->etapesDetail();
    $depart = $circuit->point_depart ?: $circuit->district?->name ?? 'District';
    $retour = $circuit->distanceRetour();
    $km = fn ($v) => number_format($v, 1, ',', ' ').' km';
    $node = 'width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex:none;';
    $rail = 'flex:1;width:3px;min-height:26px;background:rgba(127,127,127,.4);margin:2px 0';
@endphp

<div>
    <div style="display:flex;gap:14px">
        <div style="display:flex;flex-direction:column;align-items:center;width:28px;flex:none">
            <div style="{{ $node }}border:2px solid #6b7280;color:#6b7280"><x-filament::icon icon="heroicon-m-flag" style="width:14px;height:14px" /></div>
            <div style="{{ $rail }}"></div>
        </div>
        <div style="padding-bottom:10px"><strong>{{ $depart }}</strong> <span style="font-size:.8rem;opacity:.7">Départ du district</span></div>
    </div>

    @forelse ($rows as $r)
        <div style="display:flex;gap:14px">
            <div style="display:flex;flex-direction:column;align-items:center;width:28px;flex:none">
                <div style="{{ $node }}border:2px solid #2563eb;background:rgba(37,99,235,.12);color:#2563eb;font-size:.75rem;font-weight:600">{{ $r['ordre'] }}</div>
                <div style="{{ $rail }}"></div>
            </div>
            <div style="flex:1;min-width:0;padding-bottom:14px">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <strong>{{ $r['nom'] }}</strong>
                    @if ($r['type'])<span style="font-size:.75rem;opacity:.65">{{ $r['type'] }}</span>@endif
                    @if ($r['statut'] === 'inactif')<span style="font-size:.75rem;padding:2px 8px;border-radius:8px;background:rgba(156,163,175,.18);color:#6b7280">Inactif</span>@endif
                    @if (! $r['gps'])<span style="font-size:.75rem;opacity:.6">sans GPS</span>@endif
                    <span style="font-size:.78rem;opacity:.7;margin-left:auto">
                        @if ($r['distance'] !== null) {{ $km($r['distance']) }} depuis l'étape précédente
                        @elseif ($r['estimee'] !== null) ≈ {{ $km($r['estimee']) }} (estimée par GPS)
                        @endif
                    </span>
                </div>
                @if ($r['cumul'] > 0)
                    <div style="font-size:.78rem;opacity:.6;margin-top:2px">Cumul {{ $km($r['cumul']) }}</div>
                @endif
            </div>
        </div>
    @empty
        <div style="display:flex;gap:14px"><div style="width:28px;flex:none"></div><div style="padding-bottom:14px;opacity:.7">Aucune étape définie.</div></div>
    @endforelse

    <div style="display:flex;gap:14px">
        <div style="width:28px;flex:none;display:flex;justify-content:center">
            <div style="{{ $node }}border:2px solid #6b7280;color:#6b7280"><x-filament::icon icon="heroicon-m-home" style="width:14px;height:14px" /></div>
        </div>
        <div>
            <strong>{{ $depart }}</strong> <span style="font-size:.8rem;opacity:.7">Retour au district</span>
            @if ($retour !== null)<span style="font-size:.78rem;opacity:.65;margin-left:8px">≈ {{ $km($retour) }} depuis le dernier site</span>@endif
        </div>
    </div>
</div>
