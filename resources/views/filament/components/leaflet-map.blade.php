{{-- Carte OpenStreetMap (Leaflet). Variables : $points = [['lat','lon','label','n','type']], $line (bool : relier les points dans l'ordre), $height --}}
@php($points = $points ?? [])
@if (count($points) === 0)
    <div style="padding:1rem;border:1px dashed #d1d5db;border-radius:.5rem;font-size:.85rem;color:#6b7280">
        Aucune coordonnée GPS renseignée : saisissez la latitude et la longitude du départ et des sites pour afficher la carte.
    </div>
@else
    <div wire:ignore
         x-data="{}"
         x-init="
            const points = @js($points);
            const withLine = @js($line ?? true);
            const boot = () => {
                const map = L.map($refs.map, { scrollWheelZoom: false });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
                const latlngs = [];
                points.forEach(p => {
                    latlngs.push([p.lat, p.lon]);
                    const color = p.type === 'depart' ? '#10b981' : '#c2410c';
                    const icon = L.divIcon({
                        className: '',
                        html: '<div style=&quot;background:' + color + ';color:#fff;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font:700 12px sans-serif;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4)&quot;>' + p.n + '</div>',
                        iconSize: [26, 26], iconAnchor: [13, 13],
                    });
                    L.marker([p.lat, p.lon], { icon }).addTo(map).bindPopup('<strong>' + p.label + '</strong>');
                });
                if (withLine && latlngs.length > 1) { L.polyline(latlngs, { color: '#c2410c', weight: 3, opacity: .8, dashArray: '6 6' }).addTo(map); }
                latlngs.length > 1 ? map.fitBounds(latlngs, { padding: [30, 30] }) : map.setView(latlngs[0], 13);
            };
            if (window.L) { boot(); }
            else {
                if (!document.getElementById('leaflet-css')) {
                    const css = document.createElement('link'); css.id = 'leaflet-css'; css.rel = 'stylesheet'; css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'; document.head.appendChild(css);
                }
                const existing = document.getElementById('leaflet-js');
                if (existing) { existing.addEventListener('load', boot); }
                else {
                    const js = document.createElement('script'); js.id = 'leaflet-js'; js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'; js.onload = boot; document.head.appendChild(js);
                }
            }
         ">
        <div x-ref="map" style="height:{{ $height ?? '360px' }};width:100%;border-radius:.6rem;z-index:0"></div>
    </div>
@endif
