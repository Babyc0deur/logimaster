/* Cartographie : carte des districts sanitaires de Côte d'Ivoire (Leaflet + OpenStreetMap), limites officielles des districts.
   Vert = au moins une sortie de véhicule saisie, rouge = rien. */
window.lmSaisieMap = window.lmSaisieMap || function (el, data) {
    if (el._lm) return; el._lm = true;
    var OK = '#16a34a', BAD = '#dc2626', DIM = '#9ca3af';
    var map = L.map(el, { scrollWheelZoom: false, zoomSnap: 0.25 });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 14, attribution: '© contributeurs OpenStreetMap' }).addTo(map);
    var byName = {}; data.forEach(function (d) { byName[d.n] = d; });
    var focus = data.filter(function (d) { return !d.dim; });
    function bounds() {
        var pts = (focus.length ? focus : data).map(function (d) { return [d.lat, d.lon]; });
        return pts.length ? L.latLngBounds(pts).pad(0.25) : L.latLngBounds([[4.3, -8.6], [10.8, -2.4]]);
    }
    map.fitBounds(focus.length && focus.length < data.length ? bounds() : [[4.3, -8.6], [10.8, -2.4]]);
    el.addEventListener('click', function () { map.scrollWheelZoom.enable(); }, { once: true });

    fetch('/geo/ci-districts.json').then(function (r) { return r.json(); }).then(function (g) {
        L.geoJSON(g.features, {
            style: function (f) {
                var d = byName[f.properties.n];
                if (!d) return { weight: 0, fillOpacity: 0 };
                return { fillColor: d.dim ? DIM : (d.v > 0 ? OK : BAD), fillOpacity: d.dim ? 0.18 : 0.5, color: '#fbf9f4', weight: 0.8 };
            },
            onEachFeature: function (f, layer) {
                var d = byName[f.properties.n]; if (!d) return;
                layer.bindTooltip('<b>' + d.n + '</b><br>' + (d.r || '') + '<br>' + (d.v > 0 ? d.v + ' sortie' + (d.v > 1 ? 's' : '') + ' saisie' + (d.v > 1 ? 's' : '') : 'Rien de saisi') + ' · ' + d.m + ' mois renseigné' + (d.m > 1 ? 's' : ''), { sticky: true });
            }
        }).addTo(map);
        L.geoJSON({ type: 'Feature', geometry: g.outline }, { style: { fill: false, color: '#16150f', weight: 1.6 } }).addTo(map);
        data.forEach(function (d) {
            L.circleMarker([d.lat, d.lon], { radius: 3.5, color: '#16150f', weight: 1, fillColor: d.dim ? DIM : (d.v > 0 ? OK : BAD), fillOpacity: 1 })
                .addTo(map).bindTooltip(d.n);
        });
    });
};
