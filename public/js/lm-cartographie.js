/* Cartographie (LogiMaster) : simulation du déplacement des véhicules du district sur leurs circuits, fond OpenStreetMap (Leaflet). */
window.lmCartographie = window.lmCartographie || function (el, data) {
    if (el._lm) return; el._lm = true;
    const KMH = 40, STOP_MIN = 10, START = 7.5 * 60, GAP = 15;   // minutes simulées
    const map = L.map(el, { scrollWheelZoom: true, zoomControl: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© contributeurs OpenStreetMap' }).addTo(map);
    const centre = [data.centre.lat, data.centre.lon];
    L.marker(centre, { icon: L.divIcon({ className: '', html: '<div class="cg-hq"></div>', iconSize: [18, 18], iconAnchor: [9, 9] }) })
        .addTo(map).bindTooltip('District de ' + data.district + ' (départ et retour)', { direction: 'top' });
    const truck = '<svg viewBox="0 0 24 24"><path d="M3 7h11v8H3zM14 10h4l3 3v2h-7z"/><circle cx="7" cy="17" r="1.6"/><circle cx="17" cy="17" r="1.6"/></svg>';
    const all = [centre];

    const R = 6371, rad = d => d * Math.PI / 180;
    const km = (a, b) => { const dl = rad(b[0] - a[0]), dn = rad(b[1] - a[1]); const h = Math.sin(dl / 2) ** 2 + Math.cos(rad(a[0])) * Math.cos(rad(b[0])) * Math.sin(dn / 2) ** 2; return 2 * R * Math.asin(Math.sqrt(h)); };

    const V = data.vehicles.map((v, i) => {
        const pts = [centre, ...v.stops.map(s => [s.lat, s.lon]), centre];
        pts.forEach(p => all.push(p));
        const sites = v.stops.map(s => L.marker([s.lat, s.lon], { icon: L.divIcon({ className: '', html: '<div class="cg-site" style="border-color:' + v.color + ';color:' + v.color + '"></div>', iconSize: [12, 12], iconAnchor: [6, 6] }) })
            .addTo(map).bindTooltip(s.nom + ' · ' + v.circuit, { direction: 'top' }));
        const line = L.polyline(pts, { color: v.color, weight: 3, opacity: .55, dashArray: '6 7' }).addTo(map);
        const marker = L.marker(centre, { zIndexOffset: 1000, icon: L.divIcon({ className: '', html: '<div style="position:relative"><div class="cg-truck" style="background:' + v.color + '">' + truck + '</div><span class="cg-tag" style="position:absolute;left:0;top:0">' + v.immatriculation + '</span></div>', iconSize: [30, 30], iconAnchor: [15, 15] }) }).addTo(map);
        marker.bindTooltip(v.immatriculation + ' · ' + v.modele + ' · ' + v.circuit);
        const o = { v, i, sites, line, marker, path: pts, legs: null };
        setPath(o, pts, null);
        return o;
    });
    map.fitBounds(all, { padding: [30, 30] });

    // tracé : cumul des distances, et position des sites le long du tracé
    function setPath(o, path, stopIdx) {
        const cum = [0];
        for (let k = 1; k < path.length; k++) cum.push(cum[k - 1] + km(path[k - 1], path[k]));
        o.path = path; o.cum = cum;
        o.stopAt = stopIdx ? stopIdx.map(j => cum[j]) : o.v.stops.map((_, k) => cum[k + 1]);
        o.total = cum[cum.length - 1];
        // chronologie : trajets et arrêts
        o.plan = []; let t = START + o.i * GAP, d = 0;
        o.stopAt.forEach((sd, k) => { t += (sd - d) / KMH * 60; o.plan.push({ k, arrive: t, leave: t + STOP_MIN }); t += STOP_MIN; d = sd; });
        o.depart = START + o.i * GAP; o.back = t + (o.total - d) / KMH * 60;
    }
    // tracés routiers (OSRM) : remplacent les lignes droites quand le service répond
    const osrm = pts => fetch('https://router.project-osrm.org/route/v1/driving/' + pts.map(p => p[1].toFixed(5) + ',' + p[0].toFixed(5)).join(';') + '?overview=full&geometries=geojson')
        .then(r => r.ok ? r.json() : null).then(j => j && j.routes && j.routes[0] ? j.routes[0] : null);
    const roadDone = o => { o.line.setStyle({ dashArray: null, opacity: .55 }); };
    V.forEach(o => {
        osrm(o.path).then(r => {
            if (!r) return;
            const geo = r.geometry.coordinates.map(c => [c[1], c[0]]);
            // indice du point du tracé le plus proche de chaque site
            const idx = o.v.stops.map(s => { let best = 0, bd = 1e9; geo.forEach((g, n) => { const dd = km(g, [s.lat, s.lon]); if (dd < bd) { bd = dd; best = n; } }); return best; });
            for (let n = 1; n < idx.length; n++) if (idx[n] < idx[n - 1]) return;   // ordre incohérent : on garde les lignes droites
            o.line.setLatLngs(geo); roadDone(o);
            setPath(o, geo, idx);
        }).catch(() => {});
    });

    function pointAt(o, d) {
        const c = o.cum; let k = 1;
        while (k < c.length - 1 && c[k] < d) k++;
        const a = o.path[k - 1], b = o.path[k], seg = c[k] - c[k - 1] || 1, f = Math.min(1, Math.max(0, (d - c[k - 1]) / seg));
        return [a[0] + (b[0] - a[0]) * f, a[1] + (b[1] - a[1]) * f];
    }
    function state(o, t) {
        if (t < o.depart) return { d: 0, txt: 'Au district · départ ' + hhmm(o.depart), done: 0, p: 0 };
        if (t >= o.back) return { d: o.total, txt: 'Rentré au district à ' + hhmm(o.back), done: o.plan.length, p: 1 };
        let d = 0, prevT = o.depart, prevD = 0;
        for (const s of o.plan) {
            const sd = o.stopAt[s.k];
            if (t < s.arrive) { d = prevD + (sd - prevD) * (t - prevT) / (s.arrive - prevT); return { d, txt: 'En route vers ' + o.v.stops[s.k].nom, done: s.k, p: d / o.total }; }
            if (t < s.leave) return { d: sd, txt: 'Livraison à ' + o.v.stops[s.k].nom, done: s.k, p: sd / o.total, stop: true };
            prevT = s.leave; prevD = sd;
        }
        d = prevD + (o.total - prevD) * (t - prevT) / (o.back - prevT);
        return { d, txt: 'Retour au district', done: o.plan.length, p: d / o.total };
    }
    const hhmm = m => String(Math.floor(m / 60) % 24).padStart(2, '0') + ':' + String(Math.floor(m % 60)).padStart(2, '0');

    let speed = 60, playing = true, sim = START - 5, last = performance.now();
    const list = document.getElementById('cg-list'), clock = document.getElementById('cg-clock');
    list.innerHTML = V.map(o => '<div class="cg-veh" data-i="' + o.i + '"><span class="cg-sw" style="background:' + o.v.color + '"></span><div style="flex:1;min-width:0"><b>' + o.v.immatriculation + '</b> <small style="display:inline">· ' + o.v.circuit + '</small><small>' + o.v.modele + '</small><div class="cg-st" id="cg-st-' + o.i + '"></div><div class="cg-bar"><span id="cg-bar-' + o.i + '" style="background:' + o.v.color + ';width:0"></span></div></div></div>').join('');
    list.querySelectorAll('.cg-veh').forEach(e => e.addEventListener('click', () => { const o = V[+e.dataset.i]; map.panTo(o.marker.getLatLng()); o.marker.openTooltip(); }));
    document.getElementById('cg-ctrl').addEventListener('click', e => {
        const b = e.target.closest('button'); if (!b) return;
        if (b.dataset.speed) { speed = +b.dataset.speed; document.querySelectorAll('#cg-ctrl [data-speed]').forEach(x => x.classList.toggle('on', x === b)); }
        if (b.dataset.act === 'play') { playing = !playing; b.textContent = playing ? 'Pause' : 'Lecture'; }
        if (b.dataset.act === 'reset') { sim = START - 5; }
    });

    function tick(now) {
        if (!document.body.contains(el)) return;   // page quittée
        const dt = (now - last) / 1000; last = now;
        if (playing) sim += dt * speed / 60;
        const end = Math.max(...V.map(o => o.back)) + 20;
        if (sim > end) sim = START - 5;   // la journée recommence
        clock.textContent = hhmm(Math.max(sim, 0));
        V.forEach(o => {
            const s = state(o, sim);
            o.marker.setLatLng(pointAt(o, s.d));
            o.sites.forEach((m, k) => { const el2 = m.getElement(); el2 && el2.firstChild.classList.toggle('done', k < s.done || (s.stop && k === s.done)); });
            const st = document.getElementById('cg-st-' + o.i), bar = document.getElementById('cg-bar-' + o.i);
            if (st) st.textContent = s.txt + ' · ' + Math.round(s.d) + ' / ' + Math.round(o.total) + ' km';
            if (bar) bar.style.width = (s.p * 100).toFixed(1) + '%';
        });
        requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
};
