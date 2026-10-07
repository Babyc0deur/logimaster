<x-filament-panels::page>
    <style>
        .cg { display:grid; grid-template-columns:minmax(0, 1fr) 320px; gap:1rem; }
        @media (max-width: 1100px) { .cg { grid-template-columns:1fr; } }
        .cg-map { height:min(72vh, 680px); min-height:420px; border-radius:12px; border:1px solid rgba(127,127,127,.3); z-index:0; }
        .cg-side { display:flex; flex-direction:column; gap:.75rem; }
        .cg-box { border:1px solid rgba(127,127,127,.28); border-radius:12px; padding:.9rem 1rem; background:rgba(127,127,127,.04); }
        .cg-clock { font:600 1.9rem/1 ui-monospace, Consolas, monospace; letter-spacing:.02em; }
        .cg-ctrl { display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.7rem; }
        .cg-ctrl button { border:1px solid rgba(127,127,127,.4); border-radius:8px; padding:6px 11px; font-size:.82rem; font-weight:600; background:transparent; color:inherit; cursor:pointer; }
        .cg-ctrl button.on { background:#16150f; color:#f3efe6; border-color:#16150f; }
        .dark .cg-ctrl button.on { background:#f3efe6; color:#16150f; border-color:#f3efe6; }
        .cg-veh { display:flex; gap:.7rem; align-items:flex-start; padding:.6rem 0; border-top:1px solid rgba(127,127,127,.2); cursor:pointer; }
        .cg-veh:first-child { border-top:none; }
        .cg-sw { width:12px; height:12px; border-radius:3px; margin-top:5px; flex:none; }
        .cg-veh b { font-family:ui-monospace, Consolas, monospace; font-size:.92rem; }
        .cg-veh small { display:block; font-size:.78rem; opacity:.7; }
        .cg-st { font-size:.82rem; margin-top:2px; }
        .cg-bar { height:4px; background:rgba(127,127,127,.2); border-radius:2px; margin-top:6px; overflow:hidden; }
        .cg-bar span { display:block; height:100%; }
        .cg-note { font-size:.78rem; opacity:.75; line-height:1.45; }
        .cg-truck { width:30px; height:30px; border-radius:50%; border:3px solid #fff; box-shadow:0 2px 6px rgba(0,0,0,.45); display:flex; align-items:center; justify-content:center; }
        .cg-truck svg { width:16px; height:16px; stroke:#fff; fill:none; stroke-width:2.2; stroke-linecap:round; stroke-linejoin:round; }
        .cg-tag { font:700 11px/1 ui-monospace, Consolas, monospace; background:#16150f; color:#fff; padding:3px 5px; border-radius:4px; white-space:nowrap; transform:translate(20px, -26px); display:inline-block; }
        .cg-site { width:12px; height:12px; border-radius:50%; background:#fff; border:3px solid; }
        .cg-site.done { background:currentColor; }
        .cg-hq { width:18px; height:18px; background:#16150f; border:3px solid #fff; box-shadow:0 1px 4px rgba(0,0,0,.45); transform:rotate(45deg); }
    </style>

    @if (empty($map['vehicles']))
        <div class="cg-box">Aucun véhicule avec un circuit dans ce district : ajoutez des véhicules et des circuits (avec leurs sites) pour voir la simulation.</div>
    @else
        <div class="cg" wire:ignore
             x-data="{}"
             x-init="
                const data = @js($map);
                const run = () => window.lmCartographie($refs.map, data);
                const boot = () => {
                    if (window.lmCartographie) return run();
                    const s = document.createElement('script'); s.src = '/js/lm-cartographie.js?v={{ @filemtime(public_path('js/lm-cartographie.js')) }}'; s.onload = run; document.head.appendChild(s);
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
            <div x-ref="map" class="cg-map"></div>
            <div class="cg-side">
                <div class="cg-box">
                    <div style="font-size:.75rem;opacity:.7;text-transform:uppercase;letter-spacing:.05em">Heure simulée · {{ $map['district'] }}</div>
                    <div class="cg-clock" id="cg-clock">07:30</div>
                    <div class="cg-ctrl" id="cg-ctrl">
                        <button type="button" data-act="play" id="cg-play">Pause</button>
                        <button type="button" data-speed="30">×30</button>
                        <button type="button" data-speed="60" class="on">×60</button>
                        <button type="button" data-speed="180">×180</button>
                        <button type="button" data-act="reset">Recommencer</button>
                    </div>
                </div>
                <div class="cg-box" id="cg-list"></div>
                <div class="cg-note">
                    <b>Simulation.</b> Départ du chef-lieu à partir de 07:30, 40 km/h de moyenne, 10 min d'arrêt par site, puis retour au district.
                    Les tracés suivent le réseau routier OpenStreetMap (calcul d'itinéraire OSRM).
                    @if ($map['simulated'])
                        <br><b>Positions de démonstration :</b> les sites sans coordonnées GPS sont placés autour du chef-lieu. Renseignez le GPS des centres de santé pour des positions réelles.
                    @endif
                </div>
            </div>
        </div>
    @endif

</x-filament-panels::page>
