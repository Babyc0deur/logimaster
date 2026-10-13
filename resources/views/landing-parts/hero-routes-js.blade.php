@verbatim
<script>
/* Carte du hero : circuits tracés en douceur, puis un véhicule par circuit qui les parcourt au ralenti (accélération et freinage à chaque étape, arrêt sur les sites).
   L'animation tourne en continu (elle s'interrompt seulement quand la carte sort de l'écran). */
(function () {
    var svg = document.querySelector('.hero-routes'); if (!svg) return;
    var ease = function (t) { return 0.5 - 0.5 * Math.cos(Math.PI * t); };        // sinus : départ et arrivée en douceur
    var SPEED = 26, DWELL = 1500, DRAW = 1800, STAGGER = 500, OVERLAP = .45;      // unités SVG par seconde, millisecondes
    var routes = [].map.call(svg.querySelectorAll('.rt'), function (g, i) {
        var legs = [].slice.call(g.querySelectorAll('.leg')).map(function (p) { var L = p.getTotalLength(); p.style.strokeDasharray = L; p.style.strokeDashoffset = L; return { p: p, len: L, ms: Math.max(2600, L / SPEED * 1000) }; });
        return { g: g, i: i, legs: legs, stops: [].slice.call(g.querySelectorAll('.hub, .stop')), veh: g.querySelector('.veh'), cycle: legs.reduce(function (s, l) { return s + l.ms + DWELL; }, 0) };
    });
    function place(r, leg, t) { var q = leg.p.getPointAtLength(leg.len * t); r.veh.setAttribute('transform', 'translate(' + q.x.toFixed(1) + ' ' + q.y.toFixed(1) + ')'); }
    var clock = 0, last = null, live = true;
    new IntersectionObserver(function (e) { live = e[0].isIntersecting; if (live) kick(); }).observe(svg);
    function kick() { last = null; requestAnimationFrame(frame); }
    function frame(now) {
        if (!live) return;
        if (last !== null) clock += Math.min(now - last, 100);
        last = now;
        routes.forEach(function (r) {
            var t = clock - r.i * STAGGER;
            r.legs.forEach(function (l, k) {                                          // 1) tracé : tronçon après tronçon
                var a = (t - k * DRAW * OVERLAP) / DRAW;
                l.p.style.strokeDashoffset = l.len * (1 - ease(Math.min(1, Math.max(0, a))));
            });
            r.veh.style.opacity = Math.min(1, Math.max(0, t / 600));                  // le véhicule apparaît au départ, tout de suite
            var ready = DRAW + 200;                                                   // il part dès que son premier tronçon est tracé
            if (t < ready) { place(r, r.legs[0], 0); return; }
            var m = (t - ready) % r.cycle, k = 0;                                     // 2) déplacement, avec arrêt sur chaque site
            while (k < r.legs.length - 1 && m > r.legs[k].ms + DWELL) { m -= r.legs[k].ms + DWELL; k++; }
            var leg = r.legs[k], moving = m < leg.ms;
            place(r, leg, moving ? ease(m / leg.ms) : 1);
            r.veh.classList.toggle('stopped', !moving);
            r.stops.forEach(function (s, j) { s.classList.toggle('on', j <= k + (moving ? 0 : 1)); });
        });
        requestAnimationFrame(frame);
    }
    kick();
})();
</script>
@endverbatim
