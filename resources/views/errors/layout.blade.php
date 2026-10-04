<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · LogiMaster Pro</title>
    <style>
        :root { --bg: #f5f7fb; --card: #ffffff; --text: #111827; --muted: #6b7280; --border: #e5e7eb; --accent: #2563eb; --accent-text: #ffffff; --code: #dbeafe; --code-text: #1d4ed8; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #0f172a; --card: #1e293b; --text: #f1f5f9; --muted: #94a3b8; --border: #334155; --accent: #3b82f6; --accent-text: #ffffff; --code: #1e3a8a; --code-text: #bfdbfe; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; background: var(--bg); color: var(--text); font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .card { width: 100%; max-width: 480px; background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 40px 32px; text-align: center; }
        .brand { font-size: 14px; font-weight: 600; color: var(--muted); letter-spacing: .02em; margin-bottom: 28px; }
        .code { display: inline-block; font-size: 14px; font-weight: 600; padding: 4px 14px; border-radius: 999px; background: var(--code); color: var(--code-text); }
        h1 { margin: 16px 0 8px; font-size: 26px; font-weight: 600; }
        p { margin: 0; color: var(--muted); line-height: 1.6; }
        .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 28px; }
        .btn { display: inline-block; padding: 10px 18px; border-radius: 10px; font-size: 15px; font-weight: 500; text-decoration: none; cursor: pointer; border: 1px solid var(--border); background: transparent; color: var(--text); font-family: inherit; }
        .btn.primary { background: var(--accent); border-color: var(--accent); color: var(--accent-text); }
        .ref { margin-top: 24px; font-size: 12px; color: var(--muted); }
    </style>
</head>
<body>
    <main class="card" role="main">
        <div class="brand">LogiMaster Pro</div>
        <span class="code">Erreur {{ $code }}</span>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <div class="actions">
            <a class="btn primary" href="{{ url('/admin') }}">Retour à l'accueil</a>
            <button class="btn" type="button" onclick="history.length > 1 ? history.back() : location.reload()">Page précédente</button>
        </div>
        @isset($ref)
            <div class="ref">Référence : {{ $ref }}</div>
        @endisset
    </main>
</body>
</html>
