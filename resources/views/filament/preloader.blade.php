{{-- Préchargeur de l'administration : s'affiche dès le début de la page, disparaît quand tout est chargé --}}
<div id="lm-preloader" role="status" aria-live="polite" aria-label="Chargement">
    <div class="lm-pl-box">
        <div class="lm-pl-logo">
            <span class="lm-pl-ring"></span><span class="lm-pl-ring r2"></span>
            <img src="/pwa/icon-192.png" alt="" width="72" height="72">
        </div>
        <div class="lm-pl-name">LogiMaster <b>Pro</b></div>
        <svg class="lm-pl-road" viewBox="0 0 220 40" aria-hidden="true">
            <path d="M6,30 C50,34 70,8 110,18 S180,34 214,10" fill="none" stroke="rgba(255,255,255,.14)" stroke-width="4" stroke-linecap="round"/>
            <path class="lm-pl-line" d="M6,30 C50,34 70,8 110,18 S180,34 214,10" fill="none" stroke="#60a5fa" stroke-width="4" stroke-linecap="round" pathLength="100"/>
            <circle r="5" fill="#22d3ee"><animateMotion dur="1.6s" repeatCount="indefinite" path="M6,30 C50,34 70,8 110,18 S180,34 214,10"/></circle>
        </svg>
        <div class="lm-pl-text">Chargement du tableau de bord…</div>
    </div>
</div>
<style>
    #lm-preloader { position: fixed; inset: 0; z-index: 99999; display: flex; align-items: center; justify-content: center; background: radial-gradient(ellipse 70% 60% at 50% 40%, #13224a, #0a1226 70%); color: #fff; transition: opacity .45s ease, visibility .45s; }
    #lm-preloader.lm-hide { opacity: 0; visibility: hidden; pointer-events: none; }
    .lm-pl-box { text-align: center; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    .lm-pl-logo { position: relative; width: 72px; height: 72px; margin: 0 auto 18px; }
    .lm-pl-logo img { position: relative; border-radius: 18px; animation: lm-pop 1.8s ease-in-out infinite; }
    .lm-pl-ring { position: absolute; inset: -8px; border-radius: 26px; border: 2px solid rgba(96,165,250,.7); animation: lm-ring 1.8s ease-out infinite; }
    .lm-pl-ring.r2 { animation-delay: .9s; }
    .lm-pl-name { font-size: 22px; font-weight: 700; letter-spacing: -.01em; }
    .lm-pl-name b { color: #60a5fa; }
    .lm-pl-road { width: 220px; height: 40px; margin: 14px auto 4px; display: block; overflow: visible; }
    .lm-pl-line { stroke-dasharray: 100; stroke-dashoffset: 100; animation: lm-draw 1.6s ease-in-out infinite; }
    .lm-pl-text { font-size: 13px; color: rgba(255,255,255,.6); letter-spacing: .03em; }
    @keyframes lm-pop { 50% { transform: scale(1.06); } }
    @keyframes lm-ring { 0% { opacity: .9; transform: scale(.9); } 100% { opacity: 0; transform: scale(1.5); } }
    @keyframes lm-draw { 0% { stroke-dashoffset: 100; } 70%, 100% { stroke-dashoffset: 0; } }
    @media (prefers-reduced-motion: reduce) { #lm-preloader * { animation: none !important; } .lm-pl-line { stroke-dashoffset: 0; } }
</style>
<script>
    (function () {
        var el = document.getElementById('lm-preloader'), t0 = Date.now(), done = false;
        function hide() {
            if (done) return; done = true;
            setTimeout(function () { el.classList.add('lm-hide'); setTimeout(function () { el.remove(); }, 600); }, Math.max(0, 450 - (Date.now() - t0)));
        }
        if (document.readyState === 'complete') hide(); else window.addEventListener('load', hide);
        setTimeout(hide, 8000);   // garde-fou : jamais bloqué
    })();
</script>
