{{-- Préchargeur de l'administration : le logo LogiMaster se dessine (départ, itinéraire, arrivée) pendant le chargement de la page --}}
<div id="lm-preloader" role="status" aria-live="polite" aria-label="Chargement">
    <div class="lm-pl-box">
        <svg class="lm-pl-logo" viewBox="0 0 512 512" aria-hidden="true">
            <rect width="512" height="512" rx="112" fill="#16150f"/>
            <path class="lm-pl-route" d="M150 150 V300 A62 62 0 0 0 212 362 H340" fill="none" stroke="#f3efe6" stroke-width="46" pathLength="100"/>
            <circle class="lm-pl-start" cx="150" cy="132" r="43" fill="#16150f" stroke="#f3efe6" stroke-width="38"/>
            <circle class="lm-pl-end" cx="368" cy="362" r="49" fill="#16150f" stroke="#e25c1e" stroke-width="46"/>
        </svg>
        <div class="lm-pl-name">LogiMaster</div>
        <div class="lm-pl-text">Chargement…</div>
    </div>
</div>
<style>
    #lm-preloader { position: fixed; inset: 0; z-index: 99999; display: flex; align-items: center; justify-content: center; background: #f3efe6; color: #16150f; transition: opacity .4s ease, visibility .4s; }
    .dark #lm-preloader { background: #14130f; color: #f1ece0; }
    #lm-preloader.lm-hide { opacity: 0; visibility: hidden; pointer-events: none; }
    .lm-pl-box { text-align: center; }
    .lm-pl-logo { width: 84px; height: 84px; display: block; margin: 0 auto 18px; overflow: visible; }
    .lm-pl-route { stroke-dasharray: 100; stroke-dashoffset: 100; animation: lm-route 1.6s cubic-bezier(.6,0,.3,1) infinite; }
    .lm-pl-start { transform-origin: 150px 132px; animation: lm-start 1.6s ease-out infinite; }
    .lm-pl-end { transform-origin: 368px 362px; transform: scale(0); animation: lm-end 1.6s cubic-bezier(.3,1.6,.5,1) infinite; }
    .lm-pl-name { font: 600 21px/1.1 Georgia, "Times New Roman", serif; letter-spacing: -.01em; }
    .lm-pl-name em { color: #c2410c; }
    .dark .lm-pl-name em { color: #f0773a; }
    .lm-pl-text { margin-top: 8px; font: 500 11px/1 ui-monospace, Consolas, monospace; letter-spacing: .12em; text-transform: uppercase; opacity: .6; }
    @keyframes lm-route { 0% { stroke-dashoffset: 100; } 55%, 100% { stroke-dashoffset: 0; } }
    @keyframes lm-start { 0% { transform: scale(.6); } 18%, 100% { transform: scale(1); } }
    @keyframes lm-end { 0%, 50% { transform: scale(0); } 70%, 100% { transform: scale(1); } }
    @media (prefers-reduced-motion: reduce) { #lm-preloader * { animation: none !important; } .lm-pl-route { stroke-dashoffset: 0; } .lm-pl-end { transform: none; } }
</style>
<script>
    (function () {
        var el = document.getElementById('lm-preloader'), t0 = Date.now(), done = false;
        function hide() {
            if (done) return; done = true;
            setTimeout(function () { el.classList.add('lm-hide'); setTimeout(function () { el.remove(); }, 500); }, Math.max(0, 450 - (Date.now() - t0)));
        }
        if (document.readyState === 'complete') hide(); else window.addEventListener('load', hide);
        setTimeout(hide, 8000);   // garde-fou : jamais bloqué
    })();
</script>
