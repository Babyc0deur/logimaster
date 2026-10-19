{{-- Habillage de l'administration : papier, encre et accent orange (même style que le site et l'application mobile) --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,500;6..72,600&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
<style>
    :root { --lm-paper:#f3efe6; --lm-card:#fbf9f4; --lm-ink:#16150f; --lm-rule:#d6cfbf; --lm-signal:#c2410c; }
    .dark { --lm-paper:#14130f; --lm-card:#1e1c17; --lm-ink:#f1ece0; --lm-rule:#3b372d; --lm-signal:#f0773a; }

    /* fond papier, barres et cartes légèrement crème, filets à l'encre */
    body.fi-body, .fi-layout, .fi-main-ctn { background: var(--lm-paper) !important; }
    .fi-topbar { background: var(--lm-paper) !important; box-shadow: none !important; border-bottom: 1px solid var(--lm-ink); }
    .fi-sidebar, .fi-sidebar-header { background: var(--lm-paper) !important; }
    .fi-sidebar-header { box-shadow: none !important; border-bottom: 1px solid var(--lm-rule); }
    .fi-section, .fi-wi-stats-overview-stat, .fi-ta-ctn, .fi-wi-chart .fi-section, .fi-fo-field-wrp .fi-input-wrp, .fi-modal-window, .fi-dropdown-panel {
        background-color: var(--lm-card) !important;
    }
    .fi-section, .fi-ta-ctn, .fi-wi-stats-overview-stat { box-shadow: none !important; border-radius: 10px !important; --tw-ring-color: var(--lm-rule) !important; }

    /* formulaires (création, modification) : même carte que les tableaux. Les formulaires déjà découpés en sections,
       onglets ou étapes gardent leurs propres cartes ; ceux qui n'ont que des champs sont entourés d'une carte. */
    .fi-sc-wizard, .fi-sc-tabs { background-color: var(--lm-card) !important; box-shadow: none !important; border-radius: 10px !important; --tw-ring-color: var(--lm-rule) !important; }
    .fi-main-ctn form.fi-sc-form > .fi-grid.fi-sc:first-child:not(:has(.fi-section, .fi-sc-wizard, .fi-sc-tabs)) {
        background-color: var(--lm-card); border-radius: 10px; box-shadow: 0 0 0 1px var(--lm-rule); padding: 1.5rem;
    }

    /* titres à l'empattement, étiquettes en chasse fixe */
    .fi-header-heading, .fi-simple-header-heading { font-family: "Newsreader", Georgia, serif !important; font-weight: 500 !important; letter-spacing: -.02em; }
    .fi-section-header-heading, .fi-wi-stats-overview-stat-label { letter-spacing: .01em; }
    .fi-sidebar-group-label { font-family: "IBM Plex Mono", ui-monospace, Consolas, monospace; text-transform: uppercase; letter-spacing: .07em; font-size: .7rem !important; }

    /* élément actif du menu : encre, repère orange */
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn, .fi-sidebar-item-active > a { background: var(--lm-ink) !important; }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn *, .fi-sidebar-item-active > a * { color: var(--lm-paper) !important; }
    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn { box-shadow: inset 3px 0 0 var(--lm-signal); }

    /* menu replié (barre d'icônes) : au survol il se déploie par-dessus la page, sans la décaler (comme Vuexy) */
    @media (min-width: 64rem) {
        .fi-sidebar.lm-peek { position: sticky; margin-inline-end: calc(4.5rem - var(--sidebar-width)); z-index: 40; box-shadow: 0 0 2rem rgba(22, 21, 15, .18); }
    }
</style>
<script>
    (function () {
        // Filament replie le menu en icônes ; ici on le déplie au survol en basculant seulement l'état Alpine (rien n'est enregistré)
        var timer = null, peeking = false;
        function store() { return window.Alpine && Alpine.store('sidebar'); }
        function bar() { return document.querySelector('.fi-sidebar'); }
        function desktop() { return window.matchMedia('(min-width: 64rem)').matches; }
        function enter() {
            var s = store(), b = bar();
            clearTimeout(timer);
            if (!s || !b || !desktop() || s.isOpen) return;
            peeking = true; b.classList.add('lm-peek'); s.isOpen = true;
        }
        function leave() {
            clearTimeout(timer);
            timer = setTimeout(function () {
                var s = store(), b = bar();
                if (!peeking || !s || !b) return;
                peeking = false; s.isOpen = false; b.classList.remove('lm-peek');
            }, 180);
        }
        document.addEventListener('mouseover', function (e) { if (e.target.closest && e.target.closest('.fi-sidebar')) enter(); });
        document.addEventListener('mouseout', function (e) { if (e.target.closest && e.target.closest('.fi-sidebar') && !(e.relatedTarget && e.relatedTarget.closest && e.relatedTarget.closest('.fi-sidebar'))) leave(); });
    })();
</script>
