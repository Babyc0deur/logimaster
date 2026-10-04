<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="LogiMaster">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="manifest" href="/m/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/pwa/apple-touch-icon.png">
    <link rel="icon" href="/pwa/icon-192.png">
    <title>LogiMaster Convoyeur</title>
    <style>
@verbatim
        :root { --bg:#f4f6fb; --surface:#fff; --soft:#eef1f7; --text:#111827; --muted:#6b7280; --border:#e2e6ef; --accent:#2563eb; --accent-soft:#dbeafe; --accent-text:#1d4ed8;
                --ok:#16a34a; --ok-soft:#dcfce7; --warn:#d97706; --warn-soft:#fef3c7; --bad:#dc2626; --bad-soft:#fee2e2; }
        @media (prefers-color-scheme: dark) {
            :root { --bg:#0b1220; --surface:#162033; --soft:#1e2a40; --text:#f1f5f9; --muted:#94a3b8; --border:#2b3a55; --accent:#3b82f6; --accent-soft:#1e3a8a; --accent-text:#bfdbfe;
                    --ok:#4ade80; --ok-soft:#14532d; --warn:#fbbf24; --warn-soft:#78350f; --bad:#f87171; --bad-soft:#7f1d1d; }
        }
        * { box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
        html, body { margin:0; height:100%; background:var(--bg); color:var(--text); font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; font-size:16px; }
        body { overscroll-behavior-y:contain; }
        #app { min-height:100%; padding-bottom:calc(92px + env(safe-area-inset-bottom)); }
        header.top { position:sticky; top:0; z-index:5; background:var(--bg); padding:calc(14px + env(safe-area-inset-top)) 18px 10px; display:flex; justify-content:space-between; align-items:center; gap:10px; }
        header.top h1 { margin:0; font-size:20px; font-weight:600; }
        main { padding:0 16px 8px; }
        .pill { font-size:12px; padding:4px 10px; border-radius:999px; background:var(--ok-soft); color:var(--ok); display:inline-flex; align-items:center; gap:5px; white-space:nowrap; }
        .pill.off { background:var(--warn-soft); color:var(--warn); }
        .pill.bad { background:var(--bad-soft); color:var(--bad); }
        .card { background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:14px; margin-bottom:12px; }
        .hero { background:var(--accent); color:#fff; border-radius:20px; padding:18px; margin-bottom:14px; }
        .hero small { display:block; opacity:.85; font-size:13px; }
        .hero h2 { margin:4px 0 6px; font-size:26px; font-weight:600; }
        .hero .btn { background:#fff; color:#1d4ed8; margin-top:14px; }
        .btn { display:block; width:100%; border:none; border-radius:14px; padding:15px; font-size:16px; font-weight:600; cursor:pointer; background:var(--accent); color:#fff; text-align:center; text-decoration:none; font-family:inherit; }
        .btn.sec { background:var(--surface); color:var(--text); border:1px solid var(--border); }
        .btn.ok { background:var(--ok); color:#fff; } .btn.bad { background:var(--bad); color:#fff; } .btn.warn { background:var(--warn); color:#fff; }
        .btn[disabled] { opacity:.55; }
        .btn.small { padding:11px; font-size:14px; border-radius:12px; }
        .row { display:flex; align-items:center; gap:12px; }
        .grow { flex:1; min-width:0; }
        .muted { color:var(--muted); font-size:13px; }
        h3.section { margin:18px 4px 8px; font-size:13px; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.04em; }
        .dot { width:30px; height:30px; border-radius:50%; flex:none; display:flex; align-items:center; justify-content:center; font-size:15px; font-weight:700; border:2px solid; }
        .g { background:var(--ok-soft); color:var(--ok); border-color:var(--ok); } .w { background:var(--warn-soft); color:var(--warn); border-color:var(--warn); }
        .r { background:var(--bad-soft); color:var(--bad); border-color:var(--bad); } .n { background:var(--soft); color:var(--muted); border-color:var(--border); }
        .b { background:var(--accent-soft); color:var(--accent-text); border-color:var(--accent); }
        .line { margin-left:14px; border-left:3px solid var(--border); padding-left:20px; }
        .stop { position:relative; padding:2px 0 16px; }
        .stop .dot { position:absolute; left:-37px; top:0; background-clip:padding-box; }
        .stop h4 { margin:2px 0 0; font-size:16px; font-weight:600; }
        .acts { display:flex; gap:8px; margin-top:10px; }
        .acts .btn { padding:12px 4px; font-size:14px; }
        .progress { height:8px; border-radius:4px; background:var(--soft); overflow:hidden; margin:8px 0 6px; } .progress span { display:block; height:100%; background:var(--ok); }
        label { display:block; font-size:13px; color:var(--muted); margin:12px 2px 5px; }
        input, textarea { width:100%; border:1px solid var(--border); background:var(--surface); color:var(--text); border-radius:12px; padding:14px; font-size:16px; font-family:inherit; }
        input:focus, textarea:focus { outline:2px solid var(--accent); outline-offset:1px; }
        nav.tabs { position:fixed; left:0; right:0; bottom:0; z-index:10; background:var(--surface); border-top:1px solid var(--border); display:flex; justify-content:space-around; align-items:flex-end; padding:6px 4px calc(8px + env(safe-area-inset-bottom)); }
        .tab { flex:1; border:none; background:none; color:var(--muted); font-size:11px; display:flex; flex-direction:column; align-items:center; gap:2px; padding:6px 0; position:relative; font-family:inherit; cursor:pointer; text-decoration:none; }
        .tab svg { width:25px; height:25px; stroke:currentColor; fill:none; stroke-width:1.9; stroke-linecap:round; stroke-linejoin:round; }
        .tab.on { color:var(--accent); }
        .tab.mid { margin-top:-26px; }
        .tab.mid .fab { width:58px; height:58px; border-radius:50%; background:var(--text); color:var(--surface); display:flex; align-items:center; justify-content:center; border:4px solid var(--surface); }
        .tab.mid.on .fab { background:var(--accent); color:#fff; }
        .tab.mid svg { width:28px; height:28px; stroke:currentColor; }
        .badge { position:absolute; top:0; right:calc(50% - 22px); min-width:17px; height:17px; padding:0 5px; border-radius:9px; background:var(--bad); color:#fff; font-size:10px; display:flex; align-items:center; justify-content:center; font-weight:700; }
        .tab.mid .badge { top:-22px; right:calc(50% - 32px); background:var(--accent); }
        .sheet-bg { position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:30; display:flex; align-items:flex-end; }
        .sheet { background:var(--surface); width:100%; border-radius:22px 22px 0 0; padding:20px 18px calc(22px + env(safe-area-inset-bottom)); max-height:88%; overflow:auto; }
        .sheet h3 { margin:0 0 4px; font-size:19px; }
        #toast { position:fixed; left:16px; right:16px; bottom:calc(100px + env(safe-area-inset-bottom)); z-index:40; display:flex; justify-content:center; pointer-events:none; }
        #toast div { background:var(--text); color:var(--bg); padding:11px 16px; border-radius:12px; font-size:14px; max-width:420px; box-shadow:0 6px 24px rgba(0,0,0,.25); }
        .center { min-height:100vh; display:flex; flex-direction:column; justify-content:center; padding:24px; max-width:440px; margin:0 auto; }
        .logo { width:76px; height:76px; border-radius:20px; margin:0 auto 14px; display:block; }
        .err { color:var(--bad); font-size:14px; margin:10px 2px 0; min-height:18px; }
        .empty { text-align:center; padding:40px 16px; color:var(--muted); }
        .tag { font-size:11px; padding:2px 8px; border-radius:8px; margin-left:6px; }
@endverbatim
    </style>
</head>
<body>
<div id="app"></div>
<div id="sheet-root"></div>
<div id="toast"></div>
<script>window.LM_CONFIG = @json($config);</script>
<script>
@verbatim
(function () {
'use strict';
var CFG = window.LM_CONFIG;
var KEY = 'lm_state_v1';
var DEFAULTS = { token: null, user: null, mustChange: false, sorties: [], today: null, details: {}, queue: [], notifs: { unread: 0, data: [] }, syncedAt: null, push: false };
var S = load();
var deferredInstall = null, flushing = false, refreshing = false, openStop = null, errorMsg = '';
var fuelDraft = {}, fuelPhoto = null;   // saisie en cours : conservée si l'écran se rafraîchit pendant la frappe

// ------------------------------------------------------------------ état local
function load() { try { return Object.assign({}, DEFAULTS, JSON.parse(localStorage.getItem(KEY) || '{}')); } catch (e) { return Object.assign({}, DEFAULTS); } }
function save() { try { localStorage.setItem(KEY, JSON.stringify(S)); } catch (e) {} }
function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
// ------------------------------------------------------------------ photos de facture (IndexedDB : trop lourdes pour localStorage)
var idbP = null;
function idb() { return idbP || (idbP = new Promise(function (res, rej) { var r = indexedDB.open('lm_photos', 1); r.onupgradeneeded = function () { r.result.createObjectStore('p'); }; r.onsuccess = function () { res(r.result); }; r.onerror = function () { rej(r.error); }; })); }
function photoPut(k, v) { return idb().then(function (d) { return new Promise(function (res, rej) { var t = d.transaction('p', 'readwrite'); t.objectStore('p').put(v, k); t.oncomplete = res; t.onerror = function () { rej(t.error); }; }); }); }
function photoGet(k) { return idb().then(function (d) { return new Promise(function (res) { var q = d.transaction('p').objectStore('p').get(k); q.onsuccess = function () { res(q.result || null); }; q.onerror = function () { res(null); }; }); }).catch(function () { return null; }); }
function photoDel(k) { return idb().then(function (d) { d.transaction('p', 'readwrite').objectStore('p').delete(k); }).catch(function () {}); }
// photo prise au téléphone (plusieurs Mo) : réduite à 1400 px et recompressée en JPEG (≈ 200 Ko), lisible pour une facture
function compressPhoto(file) {
    return new Promise(function (resolve, reject) {
        var img = new Image(), url = URL.createObjectURL(file);
        img.onload = function () {
            var k = Math.min(1, 1400 / Math.max(img.width, img.height)), c = document.createElement('canvas');
            c.width = Math.round(img.width * k); c.height = Math.round(img.height * k);
            c.getContext('2d').drawImage(img, 0, 0, c.width, c.height); URL.revokeObjectURL(url);
            resolve(c.toDataURL('image/jpeg', 0.72));
        };
        img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('Image illisible')); };
        img.src = url;
    });
}
function uid() { return (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : 'x' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10); }
function $(id) { return document.getElementById(id); }
function fmtDate(iso, short) { if (!iso) return ''; var d = new Date(iso + 'T00:00:00'); return d.toLocaleDateString('fr-FR', short ? { day: 'numeric', month: 'short' } : { weekday: 'long', day: 'numeric', month: 'long' }); }
function toast(msg) { var t = $('toast'); t.innerHTML = '<div>' + esc(msg) + '</div>'; clearTimeout(toast.t); toast.t = setTimeout(function () { t.innerHTML = ''; }, 3200); }

// ------------------------------------------------------------------ API
function api(method, path, body) {
    var headers = { 'Accept': 'application/json', 'Content-Type': 'application/json' };
    if (S.token) headers.Authorization = 'Bearer ' + S.token;
    return fetch(CFG.api + path, { method: method, headers: headers, body: body ? JSON.stringify(body) : undefined }).then(function (r) {
        if (r.status === 204) return null;
        return r.json().catch(function () { return null; }).then(function (d) {
            if (r.ok) return d;
            var e = new Error((d && (d.message || (d.errors && Object.values(d.errors)[0][0]))) || 'Erreur'); e.status = r.status; e.data = d; throw e;
        });
    }, function () { var e = new Error('Pas de réseau'); e.offline = true; throw e; });
}
function onAuthError(e) {
    if (e.status === 401 || e.status === 403) { logoutLocal(); return true; }
    if (e.status === 423) { S.mustChange = true; save(); render(); return true; }
    return false;
}
function isOnline() { return navigator.onLine !== false; }

// ------------------------------------------------------------------ actions en file d'attente (mode hors réseau)
function enqueue(op) { op.id = op.id || uid(); op.at = new Date().toISOString(); S.queue.push(op); applyLocal(op); save(); render(); flush(); }
function recount(p) { p.restants = p.stops.filter(function (s) { return s.etat === 'planifie'; }).length; p.traites = p.stops.length - p.restants; p.livres = p.stops.filter(function (s) { return s.etat === 'livre' || s.etat === 'transit'; }).length; }
function applyLocal(op) {
    var p = S.details[op.plan]; if (!p) return;
    if (op.type === 'start') { p.demarree = true; p.sortie = p.sortie || { id: null, statut: 'en_cours', km_depart: (op.body && op.body.km_depart) || null, km_arrivee: null }; }
    if (op.type === 'livraison') { var st = p.stops.filter(function (s) { return s.id === op.target; })[0]; if (st) { st.etat = op.body.statut; st.raison = op.body.raison || null; } recount(p); }
    if (op.type === 'finish') { p.terminee = true; if (p.sortie) { p.sortie.statut = 'terminee'; p.sortie.km_arrivee = op.body.km_arrivee; } }
    if (op.type === 'fuel') { p.ravitaillements.unshift({ facture: !!op.photo, client_ref: op.body.client_ref, litres: +op.body.litres, prix_unitaire: +op.body.prix_unitaire || null, montant: null, km_compteur: op.body.km_compteur || null, station: op.body.station || null, date: op.at.slice(0, 10), pending: true }); }
    S.sorties.forEach(function (s) { if (s.id === op.plan) { s.demarree = p.demarree; s.terminee = p.terminee; s.traites = p.traites; s.livres = p.livres; } });
}
function send(op) {
    if (op.type === 'start') return api('POST', '/sorties/' + op.plan + '/start', op.body);
    if (op.type === 'finish') return api('POST', '/sorties/' + op.plan + '/finish', op.body);
    if (op.type === 'livraison') return api('POST', '/livraisons/' + op.target, Object.assign({ done_at: op.at }, op.body));
    return (op.photo ? photoGet(op.id) : Promise.resolve(null)).then(function (photo) {
        var body = Object.assign({ done_at: op.at }, op.body);
        if (photo) body.facture_photo = photo;
        return api('POST', '/sorties/' + op.plan + '/ravitaillements', body);
    });
}
function flush() {
    if (flushing || !S.token || !isOnline() || !S.queue.length) return Promise.resolve();
    flushing = true;
    var touched = {};
    function next() {
        if (!S.queue.length) return Promise.resolve(true);
        var op = S.queue[0];
        return send(op).then(function () { touched[op.plan] = true; S.queue.shift(); save(); op.photo && photoDel(op.id); return next(); }, function (e) {
            if (e.offline || (e.status && e.status >= 500)) return false;               // on réessaiera plus tard
            if (onAuthError(e)) return false;
            touched[op.plan] = true; S.queue.shift(); save(); op.photo && photoDel(op.id);   // refus du serveur : l'action est abandonnée et signalée
            toast('Action refusée : ' + e.message); return next();
        });
    }
    return next().then(function (done) {
        flushing = false; render();
        var ids = Object.keys(touched);
        return Promise.all(ids.map(function (id) { return api('GET', '/sorties/' + id).then(function (d) { if (!queued(id)) S.details[id] = d; }, function () {}); })).then(function () { save(); render(); });
    }, function () { flushing = false; });
}
function queued(plan) { return S.queue.some(function (o) { return o.plan === plan; }); }

// ------------------------------------------------------------------ synchronisation
function refresh() {
    if (!S.token || S.mustChange || !isOnline() || refreshing) return Promise.resolve();
    refreshing = true;
    return flush().then(function () { return api('GET', '/sorties'); }).then(function (l) {
        S.sorties = l.data; S.today = l.today;
        var recent = new Date(Date.now() - 4 * 864e5).toISOString().slice(0, 10);
        var ids = l.data.filter(function (p) { return !p.terminee || p.date_prevue >= recent; }).slice(0, 20).map(function (p) { return p.id; });
        return Promise.all(ids.map(function (id) { return api('GET', '/sorties/' + id).then(function (d) { if (!queued(id)) S.details[id] = d; }); }));
    }).then(function () { return api('GET', '/notifications'); }).then(function (n) {
        S.notifs = n; S.syncedAt = new Date().toISOString(); save(); render();
    }).catch(function (e) { if (!e.offline) onAuthError(e); }).then(function () { refreshing = false; });
}
function syncLabel() {
    if (!isOnline()) return S.queue.length ? S.queue.length + ' en attente' : 'Hors réseau';
    if (S.queue.length) return 'Envoi de ' + S.queue.length + '…';
    return 'À jour';
}
function syncPill() { var off = !isOnline() || S.queue.length; return '<span class="pill ' + (off ? 'off' : '') + '">' + (isOnline() ? '●' : '○') + ' ' + esc(syncLabel()) + '</span>'; }

// ------------------------------------------------------------------ données dérivées
function plans() { return Object.keys(S.details).map(function (k) { return S.details[k]; }); }
function currentPlan() {
    var list = plans().filter(function (p) { return !p.terminee; }).sort(function (a, b) { return (a.date_prevue + (a.heure_depart || '')).localeCompare(b.date_prevue + (b.heure_depart || '')); });
    return list.filter(function (p) { return p.demarree; })[0] || list.filter(function (p) { return p.date_prevue === S.today; })[0] || list[0] || null;
}
var ICON = {
    home: '<svg viewBox="0 0 24 24"><path d="M3 11l9-8 9 8M5 10v10h5v-6h4v6h5V10"/></svg>',
    route: '<svg viewBox="0 0 24 24"><circle cx="6" cy="19" r="2.2"/><circle cx="18" cy="5" r="2.2"/><path d="M8.2 19H15a3 3 0 0 0 0-6H9a3 3 0 0 1 0-6h6.8"/></svg>',
    fuel: '<svg viewBox="0 0 24 24"><path d="M4 21V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v16M3 21h12M14 9h2.5a1.5 1.5 0 0 1 1.5 1.5v6a1.5 1.5 0 0 0 3 0V8l-3-3M7 8h4"/></svg>',
    bell: '<svg viewBox="0 0 24 24"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 8 3 8H3s3-1 3-8M10 20a2 2 0 0 0 4 0"/></svg>',
    user: '<svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>'
};

// ------------------------------------------------------------------ rendu
function route() { var h = (location.hash || '#/').replace(/^#\/?/, ''); var p = h.split('/'); return { name: p[0] || 'home', id: p[1] || null }; }
function nav(hash) { location.hash = hash; }
function tab(name, label, icon, mid, badge) {
    var on = route().name === name || (name === 'circuit' && route().name === 'sortie');
    return '<a class="tab ' + (mid ? 'mid ' : '') + (on ? 'on' : '') + '" href="#/' + (name === 'home' ? '' : name) + '">' + (mid ? '<span class="fab">' + icon + '</span>' : icon) + label + (badge ? '<span class="badge">' + esc(badge) + '</span>' : '') + '</a>';
}
function shell(title, body) {
    var cp = currentPlan();
    var circuitBadge = cp && cp.demarree && !cp.terminee ? cp.traites + '/' + cp.sites : '';
    return '<header class="top"><h1>' + esc(title) + '</h1>' + syncPill() + '</header><main>' + body + '</main>' +
        '<nav class="tabs">' + tab('home', 'Accueil', ICON.home) + tab('circuit', 'Circuit', ICON.route, true, circuitBadge) + tab('fuel', 'Carburant', ICON.fuel) +
        tab('alerts', 'Alertes', ICON.bell, false, S.notifs.unread || '') + tab('profile', 'Profil', ICON.user) + '</nav>';
}
function render() {
    var app = $('app');
    if (!S.token) { app.innerHTML = loginView(); bindLogin(); return; }
    if (S.mustChange) { app.innerHTML = passwordView(true); bindPassword(true); return; }
    var r = route(), html;
    if (r.name === 'circuit' || r.name === 'sortie') html = circuitView(r.id);
    else if (r.name === 'fuel') html = fuelView();
    else if (r.name === 'alerts') html = alertsView();
    else if (r.name === 'profile') html = profileView();
    else if (r.name === 'password') { app.innerHTML = passwordView(false); bindPassword(false); return; }
    else html = homeView();
    app.innerHTML = html;
    bindCommon();
}

// ---- connexion
function loginView() {
    return '<div class="center"><img class="logo" src="/pwa/icon-192.png" alt=""><h1 style="text-align:center;margin:0 0 4px">LogiMaster</h1><p class="muted" style="text-align:center;margin:0 0 22px">Connectez-vous pour voir vos circuits.</p>' +
        '<form id="login"><label for="em">Identifiant</label><input id="em" type="text" autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" placeholder="ex. kone.ibrahim" required>' +
        '<label for="pw">Code d\'accès ou mot de passe</label><input id="pw" type="password" autocomplete="current-password" required>' +
        '<p class="muted" style="margin:8px 2px 0">Identifiant et code provisoire remis par votre district.</p>' +
        '<p class="err" id="err">' + esc(errorMsg) + '</p><button class="btn" id="go" type="submit" style="margin-top:8px">Se connecter</button></form>' +
        (deferredInstall ? '<button class="btn sec" id="inst" style="margin-top:12px">Installer l\'application</button>' : '') + '</div>';
}
function bindLogin() {
    $('login').onsubmit = function (ev) {
        ev.preventDefault(); errorMsg = ''; $('go').disabled = true; $('go').textContent = 'Connexion…';
        api('POST', '/login', { identifiant: $('em').value.trim(), password: $('pw').value, device_name: (navigator.userAgent || 'mobile').slice(0, 100) }).then(function (d) {
            S.token = d.token; S.user = d.user; S.mustChange = d.must_change_password; save(); render();
            if (!S.mustChange) { refresh(); maybeResumePush(); }
        }, function (e) { errorMsg = e.offline ? 'Pas de réseau : connexion impossible.' : (e.message || 'Identifiant ou code invalide.'); render(); });
    };
    if ($('inst')) $('inst').onclick = installApp;
}
// ---- mot de passe
function passwordView(forced) {
    return '<div class="center"><h1 style="margin:0 0 4px">' + (forced ? 'Choisissez votre mot de passe' : 'Changer le mot de passe') + '</h1>' +
        '<p class="muted" style="margin:0 0 14px">' + (forced ? 'Le mot de passe provisoire remis par votre district doit être remplacé avant de continuer.' : '8 caractères minimum, avec lettres et chiffres.') + '</p>' +
        '<form id="pwf"><label for="cur">Mot de passe actuel</label><input id="cur" type="password" autocomplete="current-password" required>' +
        '<label for="n1">Nouveau mot de passe</label><input id="n1" type="password" autocomplete="new-password" minlength="8" required>' +
        '<label for="n2">Confirmer</label><input id="n2" type="password" autocomplete="new-password" minlength="8" required>' +
        '<p class="err" id="err"></p><button class="btn" type="submit" style="margin-top:8px">Enregistrer</button>' +
        (forced ? '<button class="btn sec" type="button" id="out" style="margin-top:10px">Se déconnecter</button>' : '<a class="btn sec" href="#/profile" style="margin-top:10px">Annuler</a>') + '</form></div>';
}
function bindPassword(forced) {
    $('pwf').onsubmit = function (ev) {
        ev.preventDefault();
        if ($('n1').value !== $('n2').value) { $('err').textContent = 'Les deux mots de passe ne sont pas identiques.'; return; }
        api('POST', '/password', { current_password: $('cur').value, password: $('n1').value, password_confirmation: $('n2').value }).then(function () {
            S.mustChange = false; save(); toast('Mot de passe modifié'); nav('#/'); render(); refresh(); maybeResumePush();
        }, function (e) { $('err').textContent = e.offline ? 'Pas de réseau.' : e.message; });
    };
    if (forced && $('out')) $('out').onclick = function () { logout(); };
}

// ---- accueil
function homeView() {
    var first = (S.user && S.user.name || '').split(' ')[0];
    var cp = currentPlan(), hero = '';
    if (cp) {
        var started = cp.demarree;
        hero = '<div class="hero"><small>' + (started ? 'Circuit en cours' : 'Sortie validée · ' + (cp.date_prevue === S.today ? 'aujourd\'hui' : fmtDate(cp.date_prevue)) + (cp.heure_depart ? ' ' + esc(cp.heure_depart) : '')) + '</small>' +
            '<h2>' + esc(cp.circuit) + '</h2><small>' + esc(cp.district || '') + (cp.vehicule ? ' · ' + esc(cp.vehicule) : '') + ' · ' + cp.sites + ' site' + (cp.sites > 1 ? 's' : '') + (started ? ' · ' + cp.traites + '/' + cp.sites + ' traités' : '') + '</small>' +
            '<a class="btn" href="#/circuit">' + (started ? 'Reprendre le circuit' : 'Démarrer le circuit') + '</a></div>';
    } else {
        hero = '<div class="card empty">Aucune sortie validée pour le moment.<br>Vous serez notifié dès que le chronogramme est validé.</div>';
    }
    var upcoming = S.sorties.filter(function (p) { return !p.terminee && (!cp || p.id !== cp.id); });
    var done = S.sorties.filter(function (p) { return p.terminee; }).reverse().slice(0, 5);
    function item(p, icon, cls) {
        return '<a class="card row" href="#/sortie/' + p.id + '" style="text-decoration:none;color:inherit"><div class="dot ' + cls + '">' + icon + '</div><div class="grow"><b>' + esc(p.circuit) + '</b><div class="muted">' + esc(fmtDate(p.date_prevue, true)) + ' · ' + (p.terminee ? p.livres + '/' + p.sites + ' livrés' : p.sites + ' sites') + '</div></div></a>';
    }
    return shell('Bonjour ' + first, hero +
        (upcoming.length ? '<h3 class="section">À venir</h3>' + upcoming.map(function (p) { return item(p, '→', 'n'); }).join('') : '') +
        (done.length ? '<h3 class="section">Terminées</h3>' + done.map(function (p) { return item(p, '✓', 'g'); }).join('') : '') +
        '<p class="muted" style="text-align:center;margin:18px 0 0">' + (S.syncedAt ? 'Dernière mise à jour ' + new Date(S.syncedAt).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '') + '</p>');
}

// ---- circuit
var LABEL = { livre: 'Livré', transit: 'En transit', non_livre: 'Non livré', planifie: 'À livrer' };
var DOT = { livre: ['g', '✓'], transit: ['w', '⇄'], non_livre: ['r', '✕'], planifie: ['n', '•'] };
function lastDone() { return plans().filter(function (p) { return p.terminee; }).sort(function (a, b) { return b.date_prevue.localeCompare(a.date_prevue); })[0] || null; }
function planFor(id) { return id ? S.details[id] : (currentPlan() || lastDone()); }
function circuitView(id) {
    var p = planFor(id);
    if (!p) return shell('Circuit', '<div class="card empty">Aucun circuit à exécuter.<br>Les sorties validées par votre district apparaîtront ici.</div>');
    var pct = p.sites ? Math.round(p.traites / p.sites * 100) : 0, next = null;
    p.stops.forEach(function (s) { if (!next && s.etat === 'planifie') next = s; });
    var body = '<div class="card"><div class="row"><div class="grow"><b style="font-size:18px">' + esc(p.circuit) + '</b><div class="muted">' + esc(fmtDate(p.date_prevue)) + (p.vehicule ? ' · ' + esc(p.vehicule) : '') + '</div></div>' +
        '<span class="pill ' + (p.terminee ? '' : (p.demarree ? 'off' : 'bad')) + '">' + (p.terminee ? 'Terminé' : (p.demarree ? 'En cours' : 'À démarrer')) + '</span></div>' +
        '<div class="progress"><span style="width:' + pct + '%"></span></div><div class="muted">' + p.traites + ' / ' + p.sites + ' sites traités · ' + p.livres + ' livrés' + (next ? ' · prochain : ' + esc(next.espc) + (next.distance_km ? ' (' + String(next.distance_km).replace('.', ',') + ' km)' : '') : '') + '</div></div>';
    if (!p.demarree) {
        body += '<button class="btn" id="start">Démarrer le circuit</button><p class="muted" style="text-align:center">Le kilométrage de départ est relevé sur le véhicule ' + esc(p.vehicule || '') + '.</p>';
    }
    body += '<div class="line"><div class="stop"><div class="dot b">D</div><h4>' + esc(p.depart || 'Départ') + '</h4><div class="muted">Départ</div></div>';
    p.stops.forEach(function (s) {
        var d = DOT[s.etat] || DOT.planifie, can = p.demarree && !p.terminee, active = can && (s === next || openStop === s.id);
        body += '<div class="stop"><div class="dot ' + d[0] + '">' + d[1] + '</div><h4>' + esc(s.espc) + (s.type ? '<span class="tag n" style="background:var(--soft);color:var(--muted)">' + esc(String(s.type).replace(/_/g, ' ')) + '</span>' : '') + '</h4>' +
            '<div class="muted">' + LABEL[s.etat] + (s.raison ? ' · ' + esc(s.raison) : '') + '</div>';
        if (can && s.etat !== 'planifie' && openStop !== s.id) body += '<button class="muted" data-edit="' + s.id + '" style="background:none;border:none;padding:4px 0;text-decoration:underline;font-size:13px;color:var(--accent)">Modifier</button>';
        if (active) body += '<div class="acts"><button class="btn ok" data-do="livre" data-id="' + s.id + '">Livré</button><button class="btn warn" data-do="transit" data-id="' + s.id + '">Transit</button><button class="btn bad" data-do="non_livre" data-id="' + s.id + '">Non livré</button></div>';
        body += '</div>';
    });
    body += '<div class="stop"><div class="dot b">R</div><h4>' + esc(p.depart || 'Retour') + '</h4><div class="muted">Retour au district</div></div></div>';
    if (p.demarree && !p.terminee) {
        body += '<button class="btn sec" id="fuelgo" style="margin-top:6px">Ajouter un plein</button>' +
            '<button class="btn ' + (p.restants ? 'sec' : '') + '" id="finish" style="margin-top:10px">Terminer la sortie</button>' + (p.restants ? '<p class="muted" style="text-align:center">' + p.restants + ' site' + (p.restants > 1 ? 's' : '') + ' non traité' + (p.restants > 1 ? 's' : '') + ' : ils resteront « à traiter » au bureau.</p>' : '');
    }
    if (p.terminee && p.sortie) body += '<div class="card" style="margin-top:8px">Sortie terminée · ' + (p.sortie.km_arrivee - p.sortie.km_depart || 0) + ' km parcourus</div>';
    return shell(p.terminee ? 'Sortie' : 'Circuit en cours', body);
}
function bindCommon() {
    var p = planFor(route().id);
    var el = $('start'); if (el) el.onclick = function () { sheetStart(p); };
    el = $('finish'); if (el) el.onclick = function () { sheetFinish(p); };
    el = $('fuelgo'); if (el) el.onclick = function () { nav('#/fuel'); };
    [].forEach.call(document.querySelectorAll('[data-do]'), function (b) { b.onclick = function () { stopAction(p, b.getAttribute('data-id'), b.getAttribute('data-do')); }; });
    [].forEach.call(document.querySelectorAll('[data-edit]'), function (b) { b.onclick = function () { openStop = b.getAttribute('data-edit'); render(); }; });
    if (route().name === 'fuel') bindFuel();
    if (route().name === 'alerts') bindAlerts();
    if (route().name === 'profile') bindProfile();
}
function getPos() {
    return new Promise(function (resolve) {
        if (!navigator.geolocation) return resolve(null);
        var done = false, t = setTimeout(function () { done = true; resolve(null); }, 2500);
        navigator.geolocation.getCurrentPosition(function (g) { if (!done) { clearTimeout(t); resolve({ lat: +g.coords.latitude.toFixed(6), lon: +g.coords.longitude.toFixed(6) }); } }, function () { if (!done) { clearTimeout(t); resolve(null); } }, { maximumAge: 60000, timeout: 2400 });
    });
}
function stopAction(p, stopId, statut) {
    if (statut === 'non_livre') return sheetReason(p, stopId);
    openStop = null;
    getPos().then(function (pos) { enqueue({ type: 'livraison', plan: p.id, target: stopId, body: Object.assign({ statut: statut }, pos || {}) }); });
}

// ---- feuilles (fenêtres du bas)
function sheet(html, onBind) {
    var root = $('sheet-root'); root.innerHTML = '<div class="sheet-bg" id="bg"><div class="sheet">' + html + '</div></div>';
    $('bg').onclick = function (e) { if (e.target.id === 'bg') closeSheet(); };
    var c = root.querySelector('[data-close]'); if (c) c.onclick = closeSheet;
    onBind && onBind();
}
function closeSheet() { $('sheet-root').innerHTML = ''; }
function sheetStart(p) {
    sheet('<h3>Démarrer ' + esc(p.circuit) + '</h3><p class="muted">Kilométrage de départ (facultatif : sinon celui du véhicule).</p><label for="kmd">Kilométrage au compteur</label><input id="kmd" type="number" inputmode="numeric" min="0" placeholder="ex. 42310"><button class="btn" id="ok" style="margin-top:16px">Démarrer</button><button class="btn sec" data-close style="margin-top:8px">Annuler</button>', function () {
        $('ok').onclick = function () { var v = $('kmd').value; closeSheet(); enqueue({ type: 'start', plan: p.id, body: v ? { km_depart: parseInt(v, 10) } : {} }); };
    });
}
function sheetFinish(p) {
    var kd = p.sortie && p.sortie.km_depart;
    sheet('<h3>Terminer la sortie</h3><p class="muted">' + (p.restants ? p.restants + ' site(s) non traité(s) resteront à traiter.' : 'Tous les sites sont traités.') + '</p><label for="kma">Kilométrage d\'arrivée' + (kd ? ' (départ : ' + esc(kd) + ' km)' : '') + '</label><input id="kma" type="number" inputmode="numeric" min="' + esc(kd || 0) + '" required><p class="err" id="err"></p><button class="btn" id="ok" style="margin-top:8px">Terminer</button><button class="btn sec" data-close style="margin-top:8px">Annuler</button>', function () {
        $('ok').onclick = function () {
            var v = parseInt($('kma').value, 10);
            if (isNaN(v)) { $('err').textContent = 'Indiquez le kilométrage d\'arrivée.'; return; }
            if (kd && v < kd) { $('err').textContent = 'Inférieur au kilométrage de départ (' + kd + ' km).'; return; }
            closeSheet(); enqueue({ type: 'finish', plan: p.id, body: { km_arrivee: v } });
        };
    });
}
function sheetReason(p, stopId) {
    var st = p.stops.filter(function (s) { return s.id === stopId; })[0];
    sheet('<h3>Non livré : ' + esc(st ? st.espc : '') + '</h3><label for="rs">Raison (obligatoire)</label><textarea id="rs" rows="3" placeholder="Route coupée, centre fermé, panne…"></textarea><div class="row" style="margin-top:10px;flex-wrap:wrap;gap:6px">' +
        ['Route impraticable', 'Centre fermé', 'Panne du véhicule', 'Pas de stock'].map(function (r) { return '<button class="pill off" data-r="' + esc(r) + '" style="border:none;cursor:pointer;font-size:13px">' + esc(r) + '</button>'; }).join('') + '</div><p class="err" id="err"></p><button class="btn bad" id="ok" style="margin-top:8px">Enregistrer</button><button class="btn sec" data-close style="margin-top:8px">Annuler</button>', function () {
        [].forEach.call(document.querySelectorAll('[data-r]'), function (b) { b.onclick = function () { $('rs').value = b.getAttribute('data-r'); }; });
        $('ok').onclick = function () {
            var v = $('rs').value.trim(); if (!v) { $('err').textContent = 'La raison est obligatoire.'; return; }
            closeSheet(); openStop = null; getPos().then(function (pos) { enqueue({ type: 'livraison', plan: p.id, target: stopId, body: Object.assign({ statut: 'non_livre', raison: v }, pos || {}) }); });
        };
    });
}

// ---- carburant
function fuelView() {
    var p = currentPlan(); p = p && p.demarree ? p : null;
    var body;
    if (!p) body = '<div class="card empty">Démarrez d\'abord un circuit pour déclarer du carburant.</div>';
    else body = '<div class="card"><div class="muted">Rattaché à la sortie</div><b>' + esc(p.circuit) + (p.vehicule ? ' · ' + esc(p.vehicule) : '') + '</b></div>' +
        '<form id="ff"><label for="lt">Litres</label><input id="lt" type="number" inputmode="decimal" step="0.1" min="0.5" required value="' + esc(fuelDraft.lt || '') + '">' +
        '<label for="pu">Prix du litre (FCFA) — laissez vide pour le prix du jour</label><input id="pu" type="number" inputmode="numeric" min="1" value="' + esc(fuelDraft.pu || '') + '">' +
        '<label for="km">Compteur (km)</label><input id="km" type="number" inputmode="numeric" min="0" value="' + esc(fuelDraft.km || '') + '">' +
        '<label for="st">Station</label><input id="st" type="text" maxlength="120" placeholder="ex. Total Méagui" value="' + esc(fuelDraft.st || '') + '">' +
        '<label>Photo de la facture (facultatif)</label><input id="ph" type="file" accept="image/*" capture="environment" style="display:none">' +
        (fuelPhoto ? '<div class="card row" style="margin:0"><img src="' + fuelPhoto + '" alt="Photo de la facture" style="width:64px;height:64px;object-fit:cover;border-radius:10px"><div class="grow"><b>Photo jointe</b><div class="muted">Envoyée avec le plein</div></div><button type="button" class="btn sec small" id="phx" style="width:auto;padding:8px 12px">Retirer</button></div>'
            : '<button type="button" class="btn sec" id="phb">Prendre une photo de la facture</button>') +
        '<p class="err" id="err"></p><button class="btn" type="submit" style="margin-top:6px">Enregistrer le plein</button></form>' +
        (p.ravitaillements.length ? '<h3 class="section">Pleins de cette sortie</h3>' + p.ravitaillements.map(function (r) {
            return '<div class="card row"><div class="grow"><b>' + esc(r.litres) + ' L</b>' + (r.pending ? '<span class="tag" style="background:var(--warn-soft);color:var(--warn)">en attente</span>' : '') + (r.facture ? '<span class="tag" style="background:var(--accent-soft);color:var(--accent-text)">photo</span>' : '') + '<div class="muted">' + esc(r.station || 'Station non précisée') + (r.km_compteur ? ' · ' + esc(r.km_compteur) + ' km' : '') + '</div></div><div class="muted">' + (r.montant ? esc(Number(r.montant).toLocaleString('fr-FR')) + ' F' : '') + '</div></div>';
        }).join('') : '');
    return shell('Carburant', body);
}
function bindFuel() {
    var f = $('ff'); if (!f) return;
    f.onsubmit = function (ev) {
        ev.preventDefault(); var p = currentPlan(); if (!p || !p.demarree) return;
        var litres = parseFloat($('lt').value); if (!(litres >= 0.5)) { $('err').textContent = 'Indiquez les litres.'; return; }
        var body = { client_ref: uid(), litres: litres };
        if ($('pu').value) body.prix_unitaire = parseFloat($('pu').value);
        if ($('km').value) body.km_compteur = parseInt($('km').value, 10);
        if ($('st').value.trim()) body.station = $('st').value.trim();
        var op = { type: 'fuel', plan: p.id, body: body, photo: !!fuelPhoto }, photo = fuelPhoto;
        fuelDraft = {}; fuelPhoto = null;
        var go = function () { enqueue(op); toast(isOnline() ? 'Plein enregistré' : 'Plein enregistré, envoi au retour du réseau'); };
        if (photo) { op.id = uid(); photoPut(op.id, photo).then(go, function () { op.photo = false; toast('Photo non conservée : plein enregistré sans photo'); go(); }); } else go();
    };
    // le brouillon survit aux rafraîchissements de l'écran (synchronisation en arrière-plan)
    [].forEach.call(f.querySelectorAll('input[id]:not([type=file])'), function (i) { i.oninput = function () { fuelDraft[i.id] = i.value; }; });
    var file = $('ph'), pick = $('phb'), drop = $('phx');
    if (pick) pick.onclick = function () { file.click(); };
    if (drop) drop.onclick = function () { fuelPhoto = null; render(); };
    if (file) file.onchange = function () {
        if (!file.files[0]) return;
        compressPhoto(file.files[0]).then(function (d) { fuelPhoto = d; render(); }, function () { toast('Photo illisible, réessayez'); });
    };
}

// ---- alertes
function alertsView() {
    var l = S.notifs.data || [];
    return shell('Alertes', l.length ? '<button class="btn sec small" id="readall" style="margin-bottom:12px">Tout marquer comme lu</button>' + l.map(function (n) {
        return '<div class="card row"><div class="dot ' + (n.read ? 'n' : 'w') + '">' + (n.read ? '✓' : '!') + '</div><div class="grow"><b>' + esc(n.title) + '</b><div class="muted">' + esc(n.body) + '</div><div class="muted" style="margin-top:2px">' + esc(new Date(n.at).toLocaleString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })) + '</div></div></div>';
    }).join('') : '<div class="card empty">Aucune notification.<br>Vous serez prévenu quand un chronogramme est validé.</div>');
}
function bindAlerts() {
    var b = $('readall');
    var mark = function () { api('POST', '/notifications/read').then(function () { S.notifs.unread = 0; S.notifs.data.forEach(function (n) { n.read = true; }); save(); render(); }, function () {}); };
    if (b) b.onclick = mark;
    if (S.notifs.unread && isOnline()) { clearTimeout(bindAlerts.t); bindAlerts.t = setTimeout(mark, 2500); }
}

// ---- profil
function profileView() {
    var u = S.user || {}, perm = ('Notification' in window) ? Notification.permission : 'unsupported';
    var standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
    var ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
    return shell('Profil',
        '<div class="card row"><div class="dot b" style="width:46px;height:46px;font-size:16px">' + esc((u.name || '?').split(' ').map(function (w) { return w[0]; }).slice(0, 2).join('')) + '</div><div class="grow"><b>' + esc(u.name) + '</b><div class="muted">' + esc(u.fonction_label || '') + (u.district ? ' · ' + esc(u.district) : '') + '</div></div></div>' +
        '<div class="card"><div class="row"><span class="grow">Actions en attente d\'envoi</span><b>' + S.queue.length + '</b></div><div class="muted">Envoyées automatiquement au retour du réseau.</div></div>' +
        '<div class="card row"><span class="grow">Notifications</span>' + (perm === 'granted' && S.push ? '<span class="pill">Activées</span><button class="btn sec small" id="pushoff" style="width:auto;padding:8px 12px">Désactiver</button>' : (perm === 'denied' ? '<span class="pill bad">Bloquées</span>' : '<button class="btn small" id="pushon" style="width:auto;padding:10px 14px">Activer</button>')) + '</div>' +
        (perm === 'denied' ? '<p class="muted">Autorisez les notifications dans les réglages du navigateur pour ce site.</p>' : '') +
        (!standalone && deferredInstall ? '<button class="btn sec" id="inst" style="margin-bottom:12px">Installer l\'application</button>' : '') +
        (!standalone && ios ? '<div class="card muted">iPhone : touchez <b>Partager</b> puis <b>Sur l\'écran d\'accueil</b> pour installer l\'application et recevoir les notifications.</div>' : '') +
        '<a class="btn sec" href="#/password" style="margin-bottom:10px">Changer le mot de passe</a><button class="btn sec" id="sync" style="margin-bottom:10px">Synchroniser maintenant</button><button class="btn bad" id="out">Se déconnecter</button>' +
        '<p class="muted" style="text-align:center;margin-top:16px">LogiMaster Convoyeur</p>');
}
function bindProfile() {
    var e;
    if ((e = $('out'))) e.onclick = logout;
    if ((e = $('sync'))) e.onclick = function () { toast(isOnline() ? 'Synchronisation…' : 'Pas de réseau'); refresh(); };
    if ((e = $('pushon'))) e.onclick = registerPush;
    if ((e = $('pushoff'))) e.onclick = unregisterPush;
    if ((e = $('inst'))) e.onclick = installApp;
}

// ------------------------------------------------------------------ session
function logoutLocal() { S = Object.assign({}, DEFAULTS); save(); errorMsg = ''; nav('#/'); render(); }
function logout() {
    var done = function () { logoutLocal(); };
    if (!isOnline()) return done();
    pushEndpoint().then(function (endpoint) { return api('POST', '/logout', endpoint ? { endpoint: endpoint } : {}); }).then(done, done);
}
function installApp() { if (!deferredInstall) return; deferredInstall.prompt(); deferredInstall.userChoice.then(function () { deferredInstall = null; render(); }); }

// ------------------------------------------------------------------ notifications push
function urlB64(s) { var p = '='.repeat((4 - s.length % 4) % 4), b = (s + p).replace(/-/g, '+').replace(/_/g, '/'), r = atob(b), o = new Uint8Array(r.length); for (var i = 0; i < r.length; i++) o[i] = r.charCodeAt(i); return o; }
function pushEndpoint() { if (!('serviceWorker' in navigator)) return Promise.resolve(null); return navigator.serviceWorker.getRegistration('/m').then(function (reg) { return reg && reg.pushManager.getSubscription(); }).then(function (s) { return s ? s.endpoint : null; }).catch(function () { return null; }); }
function registerPush() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) return toast('Notifications non prises en charge sur cet appareil');
    Notification.requestPermission().then(function (perm) {
        if (perm !== 'granted') { toast('Notifications refusées'); render(); return; }
        return navigator.serviceWorker.getRegistration('/m').then(function (reg) { return reg || navigator.serviceWorker.ready; }).then(function (reg) {
            return api('GET', '/config').then(function (cfg) {
                if (!cfg.push || !cfg.vapid_public_key) { toast('Notifications non configurées sur le serveur'); return; }
                return reg.pushManager.getSubscription().then(function (sub) { return sub || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlB64(cfg.vapid_public_key) }); }).then(function (sub) {
                    var j = sub.toJSON(); return api('POST', '/push-subscriptions', { endpoint: j.endpoint, keys: j.keys }).then(function () { S.push = true; save(); render(); toast('Notifications activées'); });
                });
            });
        });
    }).catch(function (e) { toast(e.offline ? 'Pas de réseau' : 'Activation impossible'); });
}
function unregisterPush() {
    navigator.serviceWorker.getRegistration('/m').then(function (reg) { return reg && reg.pushManager.getSubscription(); }).then(function (sub) {
        if (!sub) return; var ep = sub.endpoint; return sub.unsubscribe().then(function () { return api('DELETE', '/push-subscriptions', { endpoint: ep }); });
    }).then(function () { S.push = false; save(); render(); toast('Notifications désactivées'); }, function () {});
}
function maybeResumePush() { if (('Notification' in window) && Notification.permission === 'granted' && !S.push) registerPush(); }

// ------------------------------------------------------------------ démarrage
window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); deferredInstall = e; render(); });
window.addEventListener('online', function () { render(); refresh(); });
window.addEventListener('offline', render);
window.addEventListener('hashchange', function () { openStop = null; closeSheet(); render(); window.scrollTo(0, 0); });
document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') refresh(); });
setInterval(function () { if (document.visibilityState === 'visible') refresh(); }, 60000);
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/m/sw.js', { scope: '/m' }).catch(function () {});
    navigator.serviceWorker.addEventListener('message', function (e) { if (e.data && e.data.type === 'refresh') refresh(); });
}
render();
refresh();
})();
@endverbatim
</script>
</body>
</html>
