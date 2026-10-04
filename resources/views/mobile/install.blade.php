<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Installer LogiMaster Convoyeur</title>
    <link rel="icon" href="/pwa/icon-192.png">
    <style>
        :root { --bg:#f5f7fb; --card:#fff; --text:#111827; --muted:#6b7280; --border:#e5e7eb; --accent:#2563eb; }
        @media (prefers-color-scheme: dark) { :root { --bg:#0f172a; --card:#1e293b; --text:#f1f5f9; --muted:#94a3b8; --border:#334155; } }
        * { box-sizing: border-box; }
        body { margin:0; padding:24px; background:var(--bg); color:var(--text); font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; }
        .sheet { max-width:760px; margin:0 auto; background:var(--card); border:1px solid var(--border); border-radius:20px; padding:36px; }
        .head { display:flex; align-items:center; gap:14px; margin-bottom:22px; }
        .head img { width:56px; height:56px; border-radius:14px; }
        h1 { font-size:26px; margin:0; font-weight:600; }
        .sub { color:var(--muted); margin:2px 0 0; }
        .grid { display:grid; grid-template-columns:auto 1fr; gap:32px; align-items:center; }
        .qr { background:#fff; padding:14px; border-radius:16px; border:1px solid var(--border); line-height:0; }
        .qr img { width:260px; height:260px; }
        ol { margin:0 0 14px; padding-left:20px; line-height:1.7; }
        h2 { font-size:15px; margin:14px 0 4px; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); font-weight:600; }
        .url { display:inline-block; margin-top:6px; padding:8px 12px; background:rgba(127,127,127,.12); border-radius:10px; font-family:ui-monospace,Consolas,monospace; font-size:14px; word-break:break-all; }
        .warn { margin-top:20px; padding:12px 14px; border-radius:12px; background:rgba(217,119,6,.14); color:#b45309; font-size:14px; line-height:1.5; }
        .actions { margin-top:22px; display:flex; gap:10px; }
        .btn { padding:10px 18px; border-radius:10px; border:1px solid var(--border); background:var(--accent); color:#fff; font-size:15px; text-decoration:none; cursor:pointer; font-family:inherit; }
        .btn.sec { background:transparent; color:var(--text); }
        @media (max-width:640px) { .grid { grid-template-columns:1fr; justify-items:center; } .sheet { padding:22px; } }
        @media print { body { background:#fff; padding:0; } .sheet { border:none; } .actions, .warn { display:none; } }
    </style>
</head>
<body>
<main class="sheet">
    <div class="head">
        <img src="/pwa/icon-192.png" alt="">
        <div>
            <h1>LogiMaster Convoyeur</h1>
            <p class="sub">L'application pour exécuter vos circuits : livraisons, carburant, notifications.</p>
        </div>
    </div>

    <div class="grid">
        <div class="qr"><img src="/m/qr.svg" alt="QR code d'installation de LogiMaster Convoyeur"></div>
        <div>
            <h2>Android (Chrome)</h2>
            <ol>
                <li>Scannez le QR code avec l'appareil photo.</li>
                <li>Ouvrez le lien, puis touchez <strong>Installer l'application</strong> (ou menu ⋮ → <em>Installer l'application</em>).</li>
                <li>Connectez-vous avec l'identifiant remis par votre district et autorisez les notifications.</li>
            </ol>
            <h2>iPhone (Safari)</h2>
            <ol>
                <li>Scannez le QR code, ouvrez le lien dans <strong>Safari</strong>.</li>
                <li>Touchez <strong>Partager</strong> → <strong>Sur l'écran d'accueil</strong>.</li>
                <li>Ouvrez l'application depuis l'icône pour recevoir les notifications.</li>
            </ol>
            <span class="url">{{ $url }}</span>
        </div>
    </div>

    @unless ($secure)
        <p class="warn">Cette adresse n'est pas en HTTPS : le téléphone ne pourra pas installer l'application ni recevoir de notifications.
            Renseignez l'adresse publique sécurisée dans <code>LOGIMASTER_MOBILE_URL</code> (fichier .env).</p>
    @endunless

    <div class="actions">
        <button class="btn" onclick="window.print()">Imprimer cette affiche</button>
        <a class="btn sec" href="/m">Ouvrir l'application</a>
    </div>
</main>
</body>
</html>
