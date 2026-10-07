<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f3efe6">
    <meta name="description" content="LogiMaster Pro : planification des sorties, suivi des livraisons aux centres de santé et indicateurs DDKM pour les districts sanitaires. Application convoyeur utilisable sans réseau.">
    <title>LogiMaster Pro — Flotte et livraisons des districts sanitaires</title>
    <link rel="icon" href="/pwa/icon-192.png">
    <link rel="manifest" href="/m/manifest.webmanifest">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,400;6..72,500;6..72,600&family=Public+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
@verbatim
        :root {
            --paper:#f3efe6; --paper-2:#ebe5d8; --card:#fbf9f4; --ink:#16150f; --ink-2:#3d3a31; --muted:#6e695c; --rule:#d6cfbf; --rule-2:#bfb6a2;
            --signal:#c2410c; --signal-soft:#f6dccb; --ok:#1f6f4a; --ok-soft:#d7e8dc; --late:#b42318;
            --serif:"Newsreader", Georgia, "Times New Roman", serif; --sans:"Public Sans", "Segoe UI", sans-serif; --mono:"IBM Plex Mono", ui-monospace, Consolas, monospace;
        }
        * { box-sizing:border-box; }
        html { scroll-behavior:smooth; }
        body { margin:0; background:var(--paper); color:var(--ink); font:16px/1.6 var(--sans); -webkit-font-smoothing:antialiased; }
        a { color:inherit; }
        img { max-width:100%; display:block; }
        .wrap { max-width:1180px; margin:0 auto; padding:0 28px; }
        .mono { font-family:var(--mono); font-size:.78rem; letter-spacing:.04em; text-transform:uppercase; color:var(--muted); }

        /* bandeau institutionnel + navigation */
        .strip { background:var(--ink); color:#d9d3c4; font:500 12px/1 var(--mono); letter-spacing:.06em; text-transform:uppercase; }
        .strip .wrap { display:flex; justify-content:space-between; gap:16px; padding-top:9px; padding-bottom:9px; }
        .strip span:last-child { color:#a39d8c; }
        header.top { border-bottom:1px solid var(--rule); background:var(--paper); position:sticky; top:0; z-index:20; }
        header.top .wrap { display:flex; align-items:center; justify-content:space-between; height:68px; gap:20px; }
        .brand { display:flex; align-items:center; gap:10px; text-decoration:none; }
        .brand img { width:30px; height:30px; border-radius:6px; }
        .brand b { font:600 1.25rem/1 var(--serif); letter-spacing:-.01em; }
        .brand i { font:normal 500 11px/1 var(--mono); color:var(--muted); border:1px solid var(--rule-2); padding:3px 5px; border-radius:3px; margin-left:2px; }
        nav.links { display:flex; align-items:center; gap:26px; font-size:.92rem; }
        nav.links a.l { text-decoration:none; color:var(--ink-2); }
        nav.links a.l:hover { color:var(--signal); }
        .btn { display:inline-flex; align-items:center; gap:8px; padding:11px 18px; border-radius:4px; font:600 .93rem/1 var(--sans); text-decoration:none; border:1px solid var(--ink); transition:background .15s, color .15s; }
        .btn.dark { background:var(--ink); color:var(--paper); }
        .btn.dark:hover { background:var(--signal); border-color:var(--signal); }
        .btn.line { background:transparent; color:var(--ink); }
        .btn.line:hover { background:var(--ink); color:var(--paper); }
        .btn.sm { padding:8px 13px; font-size:.85rem; }
        .btn .arr { font-family:var(--mono); font-weight:500; }

        /* ouverture */
        .hero { padding:72px 0 0; }
        .hero-grid { display:grid; grid-template-columns:1.15fr .85fr; gap:56px; align-items:end; }
        h1 { font:500 clamp(2.6rem, 5.4vw, 4.6rem)/1.02 var(--serif); letter-spacing:-.025em; margin:18px 0 0; }
        h1 em { font-style:italic; color:var(--signal); }
        .lead { font-size:1.12rem; color:var(--ink-2); max-width:34em; margin:0 0 26px; }
        .cta { display:flex; gap:12px; flex-wrap:wrap; }
        .facts { margin-top:44px; display:grid; grid-template-columns:repeat(3, 1fr); border-top:1px solid var(--ink); }
        .facts div { padding:14px 16px 0 0; }
        .facts b { display:block; font:500 2rem/1.1 var(--serif); }
        .facts span { font-size:.85rem; color:var(--muted); }
        .shot { margin-top:56px; border:1px solid var(--ink); background:var(--card); box-shadow:10px 10px 0 var(--paper-2); }
        .shot .cap { display:flex; justify-content:space-between; gap:12px; padding:9px 14px; border-bottom:1px solid var(--ink); font:500 12px/1.3 var(--mono); text-transform:uppercase; letter-spacing:.05em; color:var(--ink-2); }
        .shot img { width:100%; height:auto; }
        figure { margin:0; }
        figcaption { margin-top:12px; font-size:.86rem; color:var(--muted); max-width:60em; }
        figcaption b { color:var(--ink); font-weight:600; }

        /* sections numérotées */
        section { padding:96px 0 0; }
        .sh { display:grid; grid-template-columns:200px 1fr; gap:32px; border-top:1px solid var(--ink); padding-top:18px; margin-bottom:40px; }
        .sh .mono { padding-top:8px; }
        h2 { font:500 clamp(1.9rem, 3.4vw, 2.7rem)/1.1 var(--serif); letter-spacing:-.02em; margin:0; max-width:20em; }
        .sh p { color:var(--ink-2); margin:14px 0 0; max-width:40em; }

        .steps { display:grid; grid-template-columns:repeat(4, 1fr); border-left:1px solid var(--rule); }
        .steps li { list-style:none; padding:4px 22px 0; border-right:1px solid var(--rule); }
        .steps ol { margin:0; padding:0; display:contents; }
        .steps .n { font:500 2.6rem/1 var(--serif); color:var(--signal); }
        .steps h3 { font:600 1.02rem/1.35 var(--sans); margin:14px 0 8px; }
        .steps p { font-size:.93rem; color:var(--ink-2); margin:0; }
        .steps .who { display:block; margin-top:14px; }

        .split { display:grid; grid-template-columns:.8fr 1.2fr; gap:48px; align-items:start; }
        .split ul { margin:0; padding:0; list-style:none; border-top:1px solid var(--rule); }
        .split li { padding:14px 0; border-bottom:1px solid var(--rule); font-size:.96rem; color:var(--ink-2); }
        .split li b { color:var(--ink); display:block; font-weight:600; }
        .key { display:flex; gap:18px; margin-top:18px; font-size:.85rem; color:var(--ink-2); flex-wrap:wrap; }
        .key i { display:inline-block; width:12px; height:12px; border-radius:2px; margin-right:6px; vertical-align:-1px; }

        table.ind { width:100%; border-collapse:collapse; font-size:.95rem; }
        table.ind th { text-align:left; font:500 11.5px/1 var(--mono); text-transform:uppercase; letter-spacing:.06em; color:var(--muted); padding:0 12px 10px 0; border-bottom:1px solid var(--ink); }
        table.ind td { padding:13px 12px 13px 0; border-bottom:1px solid var(--rule); vertical-align:top; }
        table.ind td:first-child { font-family:var(--mono); color:var(--muted); width:46px; }
        table.ind td.t { font-family:var(--mono); white-space:nowrap; text-align:right; }
        table.ind td.u { color:var(--muted); font-size:.88rem; }

        .field { display:grid; grid-template-columns:1fr 340px; gap:56px; align-items:start; }
        .field ul { margin:0; padding:0; list-style:none; columns:2; column-gap:40px; }
        .field li { break-inside:avoid; padding:0 0 18px; }
        .field li b { display:block; font-weight:600; margin-bottom:2px; }
        .field li span { font-size:.93rem; color:var(--ink-2); }
        .qrcard { border:1px solid var(--ink); background:var(--card); padding:22px; }
        .qrcard .qr { background:#fff; border:1px solid var(--rule); padding:12px; width:200px; }
        .qrcard .qr svg { width:100%; height:auto; display:block; }
        .qrcard h4 { font:600 1.05rem/1.3 var(--sans); margin:18px 0 6px; }
        .qrcard p { font-size:.88rem; color:var(--ink-2); margin:0 0 10px; }
        .qrcard .url { font:500 12.5px/1.4 var(--mono); word-break:break-all; display:block; margin-bottom:16px; color:var(--signal); }
        .qrcard .cta { gap:8px; }

        .faq { border-top:1px solid var(--ink); }
        .faq details { border-bottom:1px solid var(--rule); }
        .faq summary { cursor:pointer; list-style:none; display:flex; justify-content:space-between; gap:20px; padding:18px 0; font:500 1.2rem/1.35 var(--serif); }
        .faq summary::-webkit-details-marker { display:none; }
        .faq summary::after { content:"+"; font:400 1.3rem/1 var(--mono); color:var(--signal); }
        .faq details[open] summary::after { content:"−"; }
        .faq details p { margin:0 0 20px; max-width:46em; color:var(--ink-2); }

        .closing { margin-top:110px; background:var(--ink); color:var(--paper); padding:64px 0; }
        .closing .wrap { display:flex; justify-content:space-between; align-items:end; gap:32px; flex-wrap:wrap; }
        .closing h2 { color:var(--paper); max-width:16em; }
        .closing .btn.dark { background:var(--paper); color:var(--ink); border-color:var(--paper); }
        .closing .btn.dark:hover { background:var(--signal); color:#fff; border-color:var(--signal); }
        .closing .btn.line { color:var(--paper); border-color:#6e695c; }
        .closing .btn.line:hover { background:var(--paper); color:var(--ink); }
        footer { background:var(--ink); color:#a39d8c; border-top:1px solid #34322a; font-size:.85rem; }
        footer .wrap { display:flex; justify-content:space-between; gap:20px; flex-wrap:wrap; padding-top:22px; padding-bottom:28px; }
        footer a { text-decoration:none; margin-right:18px; }
        footer a:hover { color:var(--paper); }

        .up { opacity:0; transform:translateY(10px); animation:up .7s cubic-bezier(.2,.7,.2,1) forwards; }
        @keyframes up { to { opacity:1; transform:none; } }
        @media (prefers-reduced-motion: reduce) { .up { animation:none; opacity:1; transform:none; } }

        @media (max-width: 980px) {
            .hero-grid, .split, .field { grid-template-columns:1fr; gap:32px; }
            .sh { grid-template-columns:1fr; gap:6px; }
            .steps { grid-template-columns:1fr 1fr; row-gap:32px; }
            nav.links a.l { display:none; }
        }
        @media (max-width: 600px) {
            .wrap { padding:0 16px; }
            .strip span:last-child { display:none; }
            .steps { grid-template-columns:1fr; }
            .facts b { font-size:1.5rem; }
            .field ul { columns:1; }
            .shot { box-shadow:5px 5px 0 var(--paper-2); }
            nav.links .btn.line { display:none; }
            table.ind td.u { display:none; }
        }
@endverbatim
    </style>
</head>
<body>

<div class="strip"><div class="wrap"><span>Districts sanitaires · Côte d'Ivoire</span><span>Gestion de flotte et livraisons DDKM</span></div></div>

<header class="top">
    <div class="wrap">
        <a class="brand" href="/"><img src="/pwa/icon-192.png" alt=""><b>LogiMaster</b><i>PRO</i></a>
        <nav class="links">
            <a class="l" href="#fonctionnement">Fonctionnement</a>
            <a class="l" href="#saisie">Suivi de la saisie</a>
            <a class="l" href="#indicateurs">Indicateurs</a>
            <a class="l" href="#terrain">Application terrain</a>
            @if ($adminOpen)<a class="btn line sm" href="/admin">Se connecter</a>@endif
            <a class="btn dark sm" href="#installer">Installer l'application</a>
        </nav>
    </div>
</header>

<main>
<div class="hero">
    <div class="wrap">
        <div class="hero-grid">
            <div>
                <span class="mono up">Outil de travail des équipes logistiques</span>
                <h1 class="up" style="animation-delay:.05s">Chaque sortie de véhicule, suivie <em>du planning à l'indicateur.</em></h1>
            </div>
            <div class="up" style="animation-delay:.15s">
                <p class="lead">Du chronogramme aux livraisons, en temps réel : le bureau planifie les circuits, le chef de mission enregistre chaque site depuis son téléphone, et les indicateurs DDKM du district se calculent seuls.</p>
                <div class="cta">
                    @if ($adminOpen)<a class="btn dark" href="/admin">Accéder à l'administration <span class="arr">→</span></a>@endif
                    <a class="btn line" href="#installer">Application convoyeur</a>
                </div>
            </div>
        </div>
        <div class="facts up" style="animation-delay:.25s">
            <div><b>{{ $stats['districts'] }}</b><span>districts sanitaires</span></div>
            <div><b>9</b><span>indicateurs DDKM par mois</span></div>
            <div><b>Hors réseau</b><span>saisie sur la route, envoi au retour</span></div>
        </div>
        <figure class="up" style="animation-delay:.35s">
            <div class="shot">
                <div class="cap"><span>Tableau de bord · district de Méagui</span><span>Octobre 2025</span></div>
                <img src="/landing/tableau-de-bord.jpg" width="2160" height="1350" alt="Tableau de bord de LogiMaster : filtres par PRES, région, district et période, puis les neuf indicateurs DDKM du mois d'octobre 2025 pour le district de Méagui.">
            </div>
            <figcaption><b>Capture de l'application.</b> Les neuf indicateurs du mois, comparés au mois précédent, pour le district choisi. Un clic sur un indicateur ouvre son détail et son évolution.</figcaption>
        </figure>
    </div>
</div>

<section id="fonctionnement">
    <div class="wrap">
        <div class="sh"><span class="mono">01 — Fonctionnement</span><div><h2>Une information saisie une seule fois</h2><p>La sortie planifiée au bureau est celle que le convoyeur exécute, puis celle que les indicateurs comptent. Personne ne recopie un classeur.</p></div></div>
        <ol class="steps">
            <li><span class="n">1</span><h3>Le bureau planifie</h3><p>Sorties du mois au chronogramme, véhicule, chauffeur et équipe affectés. Le superviseur valide : le planning est verrouillé.</p><span class="mono who">Gestionnaire · superviseur</span></li>
            <li><span class="n">2</span><h3>L'équipe est prévenue</h3><p>Le chef de mission reçoit la sortie sur son téléphone, avec le circuit et les sites à livrer.</p><span class="mono who">Notification</span></li>
            <li><span class="n">3</span><h3>Le circuit s'exécute</h3><p>Site par site : livré, en transit ou non livré avec la raison. Carburant, kilométrage et photo de la facture saisis en route.</p><span class="mono who">Chef de mission</span></li>
            <li><span class="n">4</span><h3>Le district est suivi</h3><p>Suivi des livraisons, indicateurs, rapports PDF et Excel, alertes de vidange et d'immobilisation.</p><span class="mono who">District · région · national</span></li>
        </ol>
    </div>
</section>

<section id="saisie">
    <div class="wrap">
        <div class="sh"><span class="mono">02 — Suivi de la saisie</span><div><h2>Qui a renseigné ses véhicules, et qui ne l'a pas fait</h2><p>Un tableau des districts sur les douze mois de l'année. Le niveau régional et national voit d'un coup d'œil où relancer.</p></div></div>
        <div class="split">
            <ul>
                <li><b>Un district par ligne, un mois par colonne</b>Le chiffre de chaque case est le nombre de sorties saisies dans le mois.</li>
                <li><b>Filtre par région et recherche</b>Les {{ $stats['districts'] }} districts, ou seulement ceux d'une région.</li>
                <li><b>« Seulement les districts en retard »</b>Ne garde que ceux à qui il manque au moins un mois écoulé.</li>
            </ul>
            <figure>
                <div class="shot">
                    <div class="cap"><span>Suivi de la saisie · région NAWA</span><span>2025</span></div>
                    <img src="/landing/suivi-saisie.jpg" width="1665" height="690" alt="Tableau du suivi de la saisie pour la région NAWA en 2025 : quatre districts, douze mois, cases vertes pour les mois renseignés et rouges pour les mois sans saisie.">
                </div>
                <div class="key"><span><i style="background:#16a34a"></i>Renseigné</span><span><i style="background:#dc2626"></i>Non renseigné</span><span><i style="background:#d6d3cc"></i>Mois à venir</span></div>
            </figure>
        </div>
    </div>
</section>

<section id="indicateurs">
    <div class="wrap">
        <div class="sh"><span class="mono">03 — Indicateurs</span><div><h2>Les neuf indicateurs DDKM, calculés chaque mois</h2><p>Par district, région ou PRES, avec l'objectif de référence. Les objectifs se règlent dans l'administration.</p></div></div>
        <table class="ind">
            <thead><tr><th>N°</th><th>Indicateur</th><th class="u">Unité</th><th style="text-align:right">Objectif</th></tr></thead>
            <tbody>
                <tr><td>01</td><td>Distance totale parcourue</td><td class="u">km</td><td class="t">—</td></tr>
                <tr><td>02</td><td>Taux de respect du chronogramme</td><td class="u">%</td><td class="t">≥ 90 %</td></tr>
                <tr><td>03</td><td>Taux d'immobilisation</td><td class="u">%</td><td class="t">≤ 10 %</td></tr>
                <tr><td>04</td><td>Taux d'utilisation des véhicules</td><td class="u">%</td><td class="t">≥ 60 %</td></tr>
                <tr><td>05</td><td>Coût global de prise en charge</td><td class="u">FCFA</td><td class="t">—</td></tr>
                <tr><td>06</td><td>Utilisation rationnelle du carburant</td><td class="u">%</td><td class="t">≥ 85 %</td></tr>
                <tr><td>07</td><td>Carburant par motif de déplacement</td><td class="u">L</td><td class="t">—</td></tr>
                <tr><td>08</td><td>Taux de respect des circuits</td><td class="u">%</td><td class="t">≥ 90 %</td></tr>
                <tr><td>09</td><td>Taux de respect de la livraison ESPC sur site</td><td class="u">%</td><td class="t">≥ 95 %</td></tr>
            </tbody>
        </table>
    </div>
</section>

<section id="terrain">
    <div class="wrap">
        <div class="sh"><span class="mono">04 — Application terrain</span><div><h2>Pour le chef de mission, sur son téléphone</h2><p>Une application web qui s'installe depuis le navigateur, sans magasin d'applications, sur Android comme sur iPhone.</p></div></div>
        <div class="field">
            <ul>
                <li><b>Ses sorties validées</b><span>Le planning du jour et des jours suivants, avec l'équipe et le circuit.</span></li>
                <li><b>Un geste par site</b><span>Livré, en transit ou non livré avec la raison ; photo du bon signé.</span></li>
                <li><b>Carburant</b><span>Litres, station et photo de la facture, rattachés à la sortie.</span></li>
                <li><b>Sans réseau</b><span>Les actions restent sur le téléphone et partent au retour du réseau, avec leur heure réelle.</span></li>
                <li><b>Signalements</b><span>Panne, accident ou anomalie, signalés au district depuis la route.</span></li>
                <li><b>Accès créé avec la fiche</b><span>Identifiant et code remis par le bureau ; mot de passe choisi à la première connexion.</span></li>
            </ul>
            <div class="qrcard" id="installer">
                <div class="qr">{!! $qr !!}</div>
                <h4>Installer l'application</h4>
                <p>Scanner le code avec l'appareil photo. Android : « Installer l'application ». iPhone : Safari, Partager, « Sur l'écran d'accueil ».</p>
                <span class="url">{{ $url }}</span>
                <div class="cta">
                    <a class="btn dark sm" href="/m">Ouvrir l'application</a>
                    <a class="btn line sm" href="/m/installer">Affiche à imprimer</a>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="questions">
    <div class="wrap">
        <div class="sh"><span class="mono">05 — Questions</span><div><h2>Questions fréquentes</h2></div></div>
        <div class="faq">
            <details open><summary>Comment un convoyeur obtient-il son accès ?</summary><p>Tout chef de mission ou passager actif a un accès créé avec sa fiche dans le personnel. Le bureau lui remet son identifiant et un code provisoire ; il choisit son mot de passe à la première connexion.</p></details>
            <details><summary>Et s'il n'y a pas de réseau sur la route ?</summary><p>Les livraisons, le carburant et les photos sont gardés sur le téléphone, puis envoyés au retour du réseau avec l'heure réelle de chaque action. Rien n'est perdu ni compté deux fois.</p></details>
            <details><summary>Qui voit quoi ?</summary><p>Chaque rôle a son périmètre : le convoyeur ses sorties validées, le gestionnaire son district, le responsable sa région, le niveau national l'ensemble. Chaque modification est journalisée.</p></details>
            <details><summary>Peut-on reprendre nos classeurs Excel ?</summary><p>Oui. L'import lit les classeurs Logimaster existants (sites, véhicules, circuits, activité) et harmonise les noms, marques, bailleurs et circuits.</p></details>
        </div>
    </div>
</section>

<div class="closing">
    <div class="wrap">
        <h2>Ouvrir le tableau de bord de votre district</h2>
        <div class="cta">
            @if ($adminOpen)<a class="btn dark" href="/admin">Se connecter <span class="arr">→</span></a>@endif
            <a class="btn line" href="/m">Application convoyeur</a>
        </div>
    </div>
</div>
</main>

<footer>
    <div class="wrap">
        <span>LogiMaster Pro · gestion de flotte et livraisons des districts sanitaires</span>
        <span><a href="#fonctionnement">Fonctionnement</a><a href="/m">Application convoyeur</a><a href="/m/installer">Affiche d'installation</a>@if ($adminOpen)<a href="/admin">Administration</a>@endif</span>
    </div>
</footer>

</body>
</html>
