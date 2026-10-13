@verbatim
<script>
/* Carte du hero : circuits tracés en douceur, puis un véhicule par circuit qui les parcourt au ralenti (accélération et freinage à chaque étape, arrêt sur les sites). */
(function () {
    var svg = document.querySelector('.hero-routes'); if (!svg) return;
    var still = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    var ease = function (t) { return 0.5 - 0.5 * Math.cos(Math.PI * t); };        // sinus : départ et arrivée en douceur
    var SPEED = 26, DWELL = 1500, DRAW = 2600, STAGGER = 700;                      // unités SVG par seconde, millisecondes
    var routes = [].map.call(svg.querySelectorAll('.rt'), function (g, i) {
        var legs = [].slice.call(g.querySelectorAll('.leg')).map(function (p) { var L = p.getTotalLength(); return { p: p, len: L, ms: Math.max(2600, L / SPEED * 1000) }; });
        var stops = [].slice.call(g.querySelectorAll('.hub, .stop'));
        legs.forEach(function (l) { l.p.style.strokeDasharray = l.len; l.p.style.strokeDashoffset = still ? 0 : l.len; });
        return { g: g, i: i, legs: legs, stops: stops, veh: g.querySelector('.veh'), cycle: legs.reduce(function (s, l) { return s + l.ms + DWELL; }, 0) };
    });
    function place(r, leg, t) { var q = leg.p.getPointAtLength(leg.len * t); r.veh.setAttribute('transform', 'translate(' + q.x.toFixed(1) + ' ' + q.y.toFixed(1) + ')'); }
    if (still) { routes.forEach(function (r) { r.stops.forEach(function (s) { s.classList.add('on'); }); place(r, r.legs[0], 0); r.veh.style.opacity = 1; }); return; }
    var t0 = performance.now(), live = true;
    new IntersectionObserver(function (e) { live = e[0].isIntersecting; if (live) { t0 += performance.now() - pausedAt; requestAnimationFrame(frame); } else pausedAt = performance.now(); }).observe(svg);
    var pausedAt = 0;
    function frame(now) {
        if (!live) return;
        var el = now - t0;
        routes.forEach(function (r) {
            var start = r.i * STAGGER, t = el - start;
            // 1) tracé : chaque tronçon se dessine l'un après l'autre
            var drawn = 0;
            r.legs.forEach(function (l, k) {
                var a = (t - k * DRAW * .55) / DRAW, p = ease(Math.min(1, Math.max(0, a)));
                l.p.style.strokeDashoffset = l.len * (1 - p); if (p > 0) drawn = k + 1;
            });
            var ready = DRAW * .55 * (r.legs.length - 1) + DRAW + 400;
            if (t < ready) { r.veh.style.opacity = 0; r.veh.setAttribute('transform', 'translate(' + r.legs[0].p.getPointAtLength(0).x + ' ' + r.legs[0].p.getPointAtLength(0).y + ')'); return; }
            // 2) déplacement : tronçon par tronçon, avec arrêt sur chaque site
            var m = (t - ready) % r.cycle, k = 0;
            while (k < r.legs.length - 1 && m > r.legs[k].ms + DWELL) { m -= r.legs[k].ms + DWELL; k++; }
            var leg = r.legs[k], moving = m < leg.ms;
            place(r, leg, moving ? ease(m / leg.ms) : 1);
            r.veh.style.opacity = Math.min(1, (t - ready) / 900);
            r.veh.classList.toggle('stopped', !moving);
            r.stops.forEach(function (s, j) { s.classList.toggle('on', j <= k + (moving ? 0 : 1)); });
        });
        requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
})();
</script>
@endverbatim
