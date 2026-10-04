<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0a1226">
    <meta name="description" content="LogiMaster Pro : planifiez les sorties, suivez chaque livraison en temps réel et pilotez les 9 indicateurs DDKM. Application convoyeur installable, même sans réseau.">
    <title>LogiMaster Pro — La logistique des districts sanitaires, en temps réel</title>
    <link rel="icon" href="/pwa/icon-192.png">
    <link rel="manifest" href="/m/manifest.webmanifest">
    <style>
@verbatim
        :root {
            --navy:#0a1226; --navy-2:#101a33; --ink:#0f172a; --text:#1e293b; --muted:#5b6b82; --line:#e2e8f2; --bg:#f6f8fc;
            --blue:#2563eb; --blue-2:#3b82f6; --blue-soft:#e0ebff; --cyan:#22d3ee; --green:#22c55e; --green-soft:#dcfce7; --amber:#f59e0b; --amber-soft:#fef3c7; --red:#ef4444;
            --radius:20px; --shadow:0 30px 60px -28px rgba(15,23,42,.35);
        }
        * { box-sizing:border-box; }
        html { scroll-behavior:smooth; }
        body { margin:0; background:var(--bg); color:var(--text); font-family:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; line-height:1.6; overflow-x:hidden; -webkit-font-smoothing:antialiased; }
        a { color:inherit; }
        .wrap { max-width:1160px; margin:0 auto; padding:0 24px; }
        .i { width:1.15em; height:1.15em; stroke:currentColor; fill:none; stroke-width:1.8; stroke-linecap:round; stroke-linejoin:round; flex:none; vertical-align:-.2em; }

        header.nav { position:fixed; inset:0 0 auto 0; z-index:50; transition:background .3s, box-shadow .3s; }
        header.nav.solid { background:rgba(10,18,38,.85); backdrop-filter:blur(14px); box-shadow:0 1px 0 rgba(255,255,255,.08); }
        header.nav .wrap { display:flex; align-items:center; justify-content:space-between; height:72px; gap:16px; }
        .brand { display:flex; align-items:center; gap:11px; font-weight:700; font-size:19px; text-decoration:none; color:#fff; letter-spacing:-.01em; }
        .brand img { width:36px; height:36px; border-radius:10px; }
        .brand small { display:block; font-size:11px; font-weight:500; opacity:.6; letter-spacing:.06em; text-transform:uppercase; margin-top:-3px; }
        nav.links { display:flex; align-items:center; gap:4px; }
        nav.links a.l { text-decoration:none; font-size:14.5px; padding:9px 13px; border-radius:10px; color:rgba(255,255,255,.72); transition:color .2s, background .2s; }
        nav.links a.l:hover { color:#fff; background:rgba(255,255,255,.08); }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:9px; padding:14px 22px; border-radius:14px; font-size:15.5px; font-weight:600; text-decoration:none; cursor:pointer; border:1px solid transparent; transition:transform .25s, box-shadow .25s, background .25s; font-family:inherit; position:relative; overflow:hidden; }
        .btn:hover { transform:translateY(-2px); }
        .btn.primary { background:var(--blue); color:#fff; box-shadow:0 10px 30px -10px rgba(37,99,235,.9); }
        .btn.primary:hover { background:var(--blue-2); }
        .btn.primary::after { content:""; position:absolute; top:0; bottom:0; width:60px; left:-80px; background:linear-gradient(100deg,transparent,rgba(255,255,255,.35),transparent); transform:skewX(-20deg); animation:shine 4.5s 1.5s infinite; }
        @keyframes shine { 0%,70% { left:-80px; } 100% { left:130%; } }
        .btn.ghost { color:#fff; border-color:rgba(255,255,255,.22); background:rgba(255,255,255,.04); }
        .btn.ghost:hover { background:rgba(255,255,255,.1); }
        .btn.sm { padding:10px 16px; font-size:14px; border-radius:11px; }
        @media (max-width:760px) { nav.links a.l { display:none; } }

        .hero { position:relative; background:var(--navy); color:#fff; overflow:hidden; padding:128px 0 90px; isolation:isolate; }
        .hero::before { content:""; position:absolute; inset:0; z-index:-2; background-image:linear-gradient(rgba(255,255,255,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.045) 1px,transparent 1px); background-size:56px 56px; mask-image:radial-gradient(ellipse 80% 70% at 50% 35%,#000 30%,transparent 80%); animation:gridmove 22s linear infinite; }
        @keyframes gridmove { to { background-position:56px 56px; } }
        .blob { position:absolute; border-radius:50%; filter:blur(90px); z-index:-1; opacity:.55; animation:drift 14s ease-in-out infinite alternate; }
        .blob.b1 { width:520px; height:520px; background:#2563eb; top:-160px; right:-100px; }
        .blob.b2 { width:420px; height:420px; background:#0891b2; bottom:-180px; left:-120px; animation-delay:-6s; opacity:.35; }
        @keyframes drift { to { transform:translate(60px,40px) scale(1.12); } }
        .hero-grid { display:grid; grid-template-columns:1fr 1.08fr; gap:48px; align-items:center; }
        @media (max-width:980px) { .hero-grid { grid-template-columns:1fr; } .hero { padding-top:112px; } }
        .eyebrow { display:inline-flex; align-items:center; gap:9px; padding:7px 14px 7px 8px; border-radius:999px; background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.12); font-size:13.5px; color:rgba(255,255,255,.85); }
        .eyebrow b { background:var(--blue); color:#fff; font-size:11.5px; padding:3px 9px; border-radius:999px; letter-spacing:.04em; }
        h1.title { font-size:clamp(38px,5.6vw,64px); line-height:1.04; letter-spacing:-.035em; margin:22px 0 20px; font-weight:800; }
        h1.title .w { display:inline-block; overflow:hidden; vertical-align:top; padding-bottom:.12em; }
        h1.title .w > span { display:inline-block; transform:translateY(110%); animation:wordup .9s cubic-bezier(.2,.8,.2,1) forwards; }
        h1.title em { font-style:normal; background:linear-gradient(95deg,#60a5fa,#22d3ee); -webkit-background-clip:text; background-clip:text; color:transparent; }
        @keyframes wordup { to { transform:none; } }
        .lead { font-size:19px; color:rgba(255,255,255,.72); max-width:520px; margin:0 0 30px; opacity:0; animation:fade 1s .9s forwards; }
        .cta { display:flex; gap:12px; flex-wrap:wrap; opacity:0; animation:fade 1s 1.1s forwards; }
        .trust { display:flex; gap:26px; flex-wrap:wrap; margin-top:38px; padding-top:26px; border-top:1px solid rgba(255,255,255,.1); opacity:0; animation:fade 1s 1.3s forwards; }
        .trust div b { display:block; font-size:25px; font-weight:800; letter-spacing:-.02em; }
        .trust div span { font-size:13px; color:rgba(255,255,255,.6); }
        @keyframes fade { to { opacity:1; } }

        .scene { position:relative; min-height:520px; opacity:0; animation:fade 1.2s .7s forwards; }
        .browser { position:absolute; top:0; right:0; width:min(100%,560px); background:#f8fafc; color:var(--text); border-radius:16px; box-shadow:0 50px 90px -30px rgba(0,0,0,.65), 0 0 0 1px rgba(255,255,255,.06); overflow:hidden; transform:perspective(1400px) rotateY(-7deg) rotateX(3deg); transform-origin:right center; }
        .bar { display:flex; align-items:center; gap:7px; padding:11px 14px; background:#e8edf5; }
        .bar i { width:10px; height:10px; border-radius:50%; background:#cbd5e1; } .bar i:nth-child(1) { background:#fb7185; } .bar i:nth-child(2) { background:#fbbf24; } .bar i:nth-child(3) { background:#4ade80; }
        .bar span { margin-left:12px; font-size:11.5px; color:#64748b; background:#fff; border-radius:7px; padding:3px 12px; flex:1; max-width:260px; }
        .dash { display:grid; grid-template-columns:54px 1fr; }
        .side { background:var(--navy-2); padding:14px 0; display:flex; flex-direction:column; align-items:center; gap:13px; }
        .side i { width:24px; height:24px; border-radius:7px; background:rgba(255,255,255,.1); } .side i.on { background:var(--blue); }
        .main { padding:16px; }
        .kpis { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
        .kpi { background:#fff; border:1px solid var(--line); border-radius:12px; padding:11px 12px; }
        .kpi small { display:block; font-size:10.5px; color:var(--muted); }
        .kpi b { font-size:22px; letter-spacing:-.02em; color:var(--ink); }
        .kpi em { font-style:normal; font-size:10.5px; color:var(--green); margin-left:4px; }
        .mapbox { margin-top:10px; background:#fff; border:1px solid var(--line); border-radius:12px; padding:8px 10px 6px; }
        .mapbox h5 { margin:0; font-size:11.5px; color:var(--ink); display:flex; justify-content:space-between; } .mapbox h5 span { color:var(--muted); font-weight:500; }
        svg.route { width:100%; height:auto; display:block; }
        .feed { margin-top:10px; background:#fff; border:1px solid var(--line); border-radius:12px; padding:8px 12px; min-height:92px; }
        .feed h5 { margin:0 0 4px; font-size:11.5px; color:var(--ink); }
        .ev { display:flex; align-items:center; gap:8px; font-size:11.5px; padding:4px 0; border-top:1px solid #f1f5f9; animation:evin .5s both; }
        .ev:first-child { border-top:0; }
        .ev i { width:8px; height:8px; border-radius:50%; flex:none; } .ev small { margin-left:auto; color:var(--muted); font-size:10.5px; }
        @keyframes evin { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:none; } }
        .phone { position:absolute; left:-6px; bottom:-18px; width:206px; background:#fff; color:var(--text); border-radius:30px; border:7px solid #0b1220; box-shadow:0 40px 70px -20px rgba(0,0,0,.7); padding:14px 12px; animation:bob 6s ease-in-out infinite; z-index:3; }
        @keyframes bob { 50% { transform:translateY(-10px); } }
        .phone .notch { width:60px; height:5px; border-radius:3px; background:#0b1220; margin:-4px auto 10px; }
        .phone h6 { margin:0; font-size:13px; display:flex; justify-content:space-between; align-items:center; } .phone h6 span { font-size:10px; color:var(--green); font-weight:600; }
        .pbar { height:6px; border-radius:3px; background:#eef2f7; margin:8px 0; overflow:hidden; } .pbar span { display:block; height:100%; width:0; background:var(--green); transition:width .8s; }
        .st { display:flex; align-items:center; gap:8px; padding:7px 8px; border-radius:10px; background:#f4f7fb; margin-top:6px; font-size:11.5px; font-weight:500; transition:background .4s; }
        .st .d { width:18px; height:18px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:800; background:#fff; border:1.5px solid #cbd5e1; color:#94a3b8; flex:none; transition:all .4s; }
        .st.g { background:var(--green-soft); } .st.g .d { background:var(--green); border-color:var(--green); color:#fff; }
        .st.w { background:var(--amber-soft); } .st.w .d { background:var(--amber); border-color:var(--amber); color:#fff; }
        .pbtn { margin-top:10px; padding:9px; border-radius:11px; background:var(--ink); color:#fff; text-align:center; font-size:11.5px; font-weight:700; transition:background .4s; } .pbtn.done { background:var(--green); }
        .toasts { position:absolute; right:-8px; top:-26px; width:250px; display:flex; flex-direction:column; gap:9px; z-index:4; pointer-events:none; }
        .toast { background:rgba(255,255,255,.97); color:var(--text); border-radius:14px; padding:10px 13px; font-size:12px; box-shadow:0 18px 40px -14px rgba(0,0,0,.55); display:flex; gap:10px; align-items:flex-start; animation:toastin .55s cubic-bezier(.2,.9,.3,1.2) both; }
        .toast.out { animation:toastout .4s forwards; }
        .toast .ic { width:28px; height:28px; border-radius:9px; display:flex; align-items:center; justify-content:center; flex:none; background:var(--blue-soft); color:var(--blue); font-weight:800; }
        .toast.ok .ic { background:var(--green-soft); color:#15803d; } .toast.warn .ic { background:var(--amber-soft); color:#b45309; }
        .toast b { display:block; font-size:12.5px; color:var(--ink); } .toast span { color:var(--muted); }
        @keyframes toastin { from { opacity:0; transform:translateX(30px) scale(.94); } to { opacity:1; transform:none; } }
        @keyframes toastout { to { opacity:0; transform:translateX(30px); } }
        @media (max-width:980px) { .scene { min-height:560px; margin-top:20px; } .browser { transform:none; position:relative; width:100%; } .phone { left:8px; bottom:-40px; } .toasts { right:4px; top:-34px; } }
        @media (max-width:560px) { .kpi b { font-size:17px; } .phone { width:176px; } .toasts { width:210px; } }
        .scroll-hint { position:absolute; bottom:20px; left:50%; width:26px; height:42px; border:2px solid rgba(255,255,255,.3); border-radius:14px; transform:translateX(-50%); }
        .scroll-hint::after { content:""; position:absolute; left:50%; top:8px; width:4px; height:8px; border-radius:2px; background:#fff; transform:translateX(-50%); animation:wheel 1.8s infinite; }
        @keyframes wheel { 0% { opacity:0; transform:translate(-50%,0); } 30% { opacity:1; } 100% { opacity:0; transform:translate(-50%,14px); } }
        @media (max-width:980px) { .scroll-hint { display:none; } }

        section { padding:104px 0; position:relative; }
        .head { max-width:700px; margin:0 0 54px; }
        .head.center { margin-left:auto; margin-right:auto; text-align:center; }
        .tag { display:inline-flex; gap:8px; align-items:center; font-size:13px; font-weight:700; letter-spacing:.07em; text-transform:uppercase; color:var(--blue); }
        .tag::before { content:""; width:22px; height:2px; background:var(--blue); border-radius:2px; }
        h2 { font-size:clamp(30px,4vw,46px); line-height:1.1; letter-spacing:-.03em; margin:14px 0 14px; color:var(--ink); font-weight:800; }
        .sub { font-size:18px; color:var(--muted); margin:0; }
        .reveal { opacity:0; transform:translateY(34px); transition:opacity .8s cubic-bezier(.2,.7,.2,1), transform .8s cubic-bezier(.2,.7,.2,1); transition-delay:var(--d,0s); }
        .reveal.in { opacity:1; transform:none; }

        .journey { position:relative; display:grid; gap:26px; max-width:900px; margin:0 auto; }
        .journey .rail { position:absolute; left:34px; top:30px; bottom:30px; width:4px; background:var(--line); border-radius:2px; }
        .journey .rail span { position:absolute; left:0; top:0; width:100%; height:var(--p,0%); background:linear-gradient(var(--blue),var(--cyan)); border-radius:2px; box-shadow:0 0 18px rgba(34,211,238,.7); }
        .step { position:relative; display:grid; grid-template-columns:72px 1fr; gap:22px; align-items:start; }
        .step .n { width:72px; height:72px; border-radius:50%; background:#fff; border:2px solid var(--line); display:flex; align-items:center; justify-content:center; font-size:26px; color:var(--muted); z-index:2; transition:all .5s; }
        .step.on .n { background:var(--blue); border-color:var(--blue); color:#fff; box-shadow:0 0 0 8px rgba(37,99,235,.14), 0 14px 30px -10px rgba(37,99,235,.7); transform:scale(1.04); }
        .step .card { background:#fff; border:1px solid var(--line); border-radius:var(--radius); padding:22px 26px; transition:border-color .5s, box-shadow .5s, transform .5s; }
        .step.on .card { border-color:#bfd3fb; box-shadow:var(--shadow); transform:translateX(6px); }
        .step h3 { margin:0 0 4px; font-size:20px; color:var(--ink); letter-spacing:-.01em; } .step p { margin:0; color:var(--muted); }
        .step .chips { display:flex; gap:8px; flex-wrap:wrap; margin-top:12px; } .chip { font-size:12.5px; padding:4px 11px; border-radius:999px; background:var(--blue-soft); color:#1d4ed8; font-weight:600; }
        @media (max-width:560px) { .step { grid-template-columns:52px 1fr; gap:14px; } .step .n { width:52px; height:52px; font-size:20px; } .journey .rail { left:24px; } }

        .bento { display:grid; grid-template-columns:repeat(6,1fr); gap:18px; }
        .cell { background:#fff; border:1px solid var(--line); border-radius:var(--radius); padding:26px; position:relative; overflow:hidden; transition:transform .35s, box-shadow .35s, border-color .35s; }
        .cell:hover { transform:translateY(-6px); box-shadow:var(--shadow); border-color:#bfd3fb; }
        .cell.c2 { grid-column:span 2; } .cell.c3 { grid-column:span 3; } .cell.c4 { grid-column:span 4; }
        @media (max-width:900px) { .cell.c2, .cell.c3, .cell.c4 { grid-column:span 6; } }
        .cell .ico { width:44px; height:44px; border-radius:13px; background:var(--blue-soft); color:var(--blue); display:flex; align-items:center; justify-content:center; font-size:22px; margin-bottom:16px; }
        .cell h3 { margin:0 0 6px; font-size:19px; color:var(--ink); letter-spacing:-.01em; } .cell p { margin:0; color:var(--muted); font-size:15px; }
        .viz { margin-top:20px; background:var(--bg); border-radius:14px; padding:14px; min-height:132px; position:relative; overflow:hidden; }
        .cal { display:grid; grid-template-columns:repeat(5,1fr); gap:6px; font-size:10.5px; }
        .cal b { text-align:center; color:var(--muted); font-weight:600; } .cal div { height:34px; background:#fff; border-radius:7px; border:1px solid var(--line); position:relative; }
        .chipc { position:absolute; left:3px; right:3px; top:4px; bottom:4px; border-radius:5px; background:var(--blue); opacity:.9; }
        .drag { position:absolute; width:52px; height:26px; border-radius:6px; background:var(--cyan); box-shadow:0 8px 16px -4px rgba(8,145,178,.6); animation:dragmove 7s ease-in-out infinite; z-index:2; display:flex; align-items:center; justify-content:center; color:#fff; font-size:10px; font-weight:700; }
        @keyframes dragmove { 0%,12% { left:7%; top:46px; transform:scale(1); } 22% { transform:scale(1.12) rotate(-3deg); } 45%,62% { left:61%; top:46px; transform:scale(1) rotate(0); } 63%,100% { left:61%; top:46px; } }
        .lockb { position:absolute; right:12px; bottom:10px; background:var(--green-soft); color:#15803d; font-size:11.5px; font-weight:700; padding:4px 10px; border-radius:999px; opacity:0; animation:lockin 7s infinite; }
        @keyframes lockin { 0%,66% { opacity:0; transform:translateY(8px); } 74%,95% { opacity:1; transform:none; } 100% { opacity:0; } }
        .live { display:flex; flex-direction:column; gap:7px; }
        .lv { background:#fff; border-radius:9px; padding:7px 10px; display:flex; align-items:center; gap:9px; font-size:12px; animation:lvin 9s infinite; opacity:0; border:1px solid var(--line); }
        .lv:nth-child(2) { animation-delay:2.2s; } .lv:nth-child(3) { animation-delay:4.4s; } .lv:nth-child(4) { animation-delay:6.6s; }
        .lv i { width:9px; height:9px; border-radius:50%; background:var(--green); animation:ping 1.6s infinite; } .lv small { margin-left:auto; color:var(--muted); }
        @keyframes lvin { 0% { opacity:0; transform:translateY(10px); } 6%,86% { opacity:1; transform:none; } 100% { opacity:0; } }
        @keyframes ping { 0% { box-shadow:0 0 0 0 rgba(34,197,94,.6); } 70%,100% { box-shadow:0 0 0 8px rgba(34,197,94,0); } }
        .rings { display:flex; gap:14px; justify-content:space-around; align-items:center; flex-wrap:wrap; }
        .ring { width:84px; text-align:center; font-size:11px; color:var(--muted); }
        .ring svg { width:84px; height:84px; transform:rotate(-90deg); display:block; margin-bottom:2px; }
        .ring circle { fill:none; stroke-width:8; stroke-linecap:round; } .ring .bg { stroke:#e2e8f0; } .ring .fg { stroke:var(--blue); stroke-dasharray:226; stroke-dashoffset:226; transition:stroke-dashoffset 1.6s cubic-bezier(.2,.8,.2,1) .3s; }
        .ring .num { margin-top:-58px; margin-bottom:34px; font-size:16px; font-weight:800; color:var(--ink); position:relative; }
        .in .ring .fg { stroke-dashoffset:var(--o); }
        .receipt { width:150px; margin:0 auto; background:#fff; border-radius:8px; padding:10px 12px 14px; font-size:10px; color:#334155; box-shadow:0 12px 24px -12px rgba(15,23,42,.45); position:relative; font-family:ui-monospace,Consolas,monospace; transform:rotate(-2deg); overflow:hidden; }
        .receipt hr { border:0; border-top:1px dashed #cbd5e1; margin:6px 0; } .receipt .tot { display:flex; justify-content:space-between; font-weight:700; color:var(--ink); }
        .scan { position:absolute; left:0; right:0; height:22px; background:linear-gradient(transparent,rgba(34,211,238,.5),transparent); animation:scanr 2.6s ease-in-out infinite; top:-22px; }
        @keyframes scanr { 100% { top:100%; } }
        .okmark { position:absolute; right:14px; bottom:14px; background:var(--green); color:#fff; border-radius:999px; font-size:11px; font-weight:700; padding:4px 11px; animation:okpop 5.2s infinite; opacity:0; }
        @keyframes okpop { 0%,50% { opacity:0; transform:scale(.6); } 60%,92% { opacity:1; transform:none; } 100% { opacity:0; } }
        .net { display:flex; align-items:center; justify-content:center; gap:16px; min-height:104px; }
        .netc { width:60px; height:60px; border-radius:50%; background:#fff; border:1px solid var(--line); display:flex; align-items:center; justify-content:center; font-size:26px; color:var(--muted); animation:netmode 7s infinite; }
        @keyframes netmode { 0%,40% { color:var(--red); border-color:#fecaca; background:#fef2f2; } 50%,100% { color:var(--green); border-color:#bbf7d0; background:#f0fdf4; } }
        .netq { position:relative; background:#fff; border-radius:12px; border:1px solid var(--line); padding:8px 14px; font-size:12px; min-width:140px; height:56px; } .netq b { font-size:20px; color:var(--ink); display:block; line-height:1.2; }
        .netq span { position:absolute; left:14px; top:8px; } .netq .a { animation:qa 7s infinite; } .netq .b { opacity:0; animation:qb 7s infinite; }
        @keyframes qa { 0%,44% { opacity:1; } 50%,100% { opacity:0; } } @keyframes qb { 0%,46% { opacity:0; } 54%,100% { opacity:1; } }
        .roles { display:flex; flex-wrap:wrap; gap:8px; }
        .role { font-size:12.5px; padding:6px 13px; border-radius:999px; background:#fff; border:1px solid var(--line); color:var(--muted); font-weight:600; animation:rolehi 10s infinite; }
        .role:nth-child(2) { animation-delay:2s; } .role:nth-child(3) { animation-delay:4s; } .role:nth-child(4) { animation-delay:6s; } .role:nth-child(5) { animation-delay:8s; }
        @keyframes rolehi { 0%,2% { background:#fff; color:var(--muted); border-color:var(--line); } 6%,18% { background:var(--blue); color:#fff; border-color:var(--blue); transform:scale(1.06); } 24%,100% { background:#fff; color:var(--muted); border-color:var(--line); transform:none; } }

        .mobile { background:var(--navy); color:#fff; overflow:hidden; }
        .mobile h2 { color:#fff; } .mobile .sub { color:rgba(255,255,255,.7); } .mobile .tag { color:#60a5fa; } .mobile .tag::before { background:#60a5fa; }
        .mgrid { display:grid; grid-template-columns:1fr 1fr; gap:60px; align-items:center; }
        @media (max-width:900px) { .mgrid { grid-template-columns:1fr; } }
        .benefits { list-style:none; padding:0; margin:28px 0 34px; display:grid; gap:14px; }
        .benefits li { display:flex; gap:14px; align-items:flex-start; color:rgba(255,255,255,.85); }
        .benefits .bi { width:36px; height:36px; border-radius:11px; background:rgba(255,255,255,.08); display:flex; align-items:center; justify-content:center; color:#60a5fa; font-size:18px; flex:none; }
        .benefits b { color:#fff; display:block; font-size:16px; }
        .big-phone { position:relative; width:290px; height:590px; margin:0 auto; border-radius:46px; border:9px solid #1e293b; background:#fff; color:var(--text); box-shadow:0 60px 100px -30px rgba(0,0,0,.8), 0 0 0 2px #334155; overflow:hidden; animation:bob 7s ease-in-out infinite; }
        .big-phone::before { content:""; position:absolute; top:8px; left:50%; width:84px; height:22px; background:#0b1220; border-radius:14px; transform:translateX(-50%); z-index:5; }
        .ps { position:absolute; inset:0; padding:46px 16px 78px; opacity:0; transform:translateX(26px); transition:opacity .5s, transform .5s; overflow:hidden; }
        .ps.on { opacity:1; transform:none; }
        .ps h4 { margin:0 0 10px; font-size:18px; color:var(--ink); }
        .hero-card { background:var(--blue); color:#fff; border-radius:18px; padding:16px; } .hero-card small { opacity:.8; font-size:12px; } .hero-card b { display:block; font-size:22px; margin:2px 0 6px; } .hero-card .go { margin-top:12px; background:#fff; color:#1d4ed8; border-radius:12px; padding:11px; text-align:center; font-weight:700; font-size:13.5px; }
        .li { display:flex; gap:11px; align-items:center; background:#f4f7fb; border-radius:13px; padding:11px 12px; margin-top:9px; font-size:13.5px; font-weight:600; } .li small { display:block; font-weight:500; color:var(--muted); font-size:11.5px; }
        .li .d { width:26px; height:26px; border-radius:50%; background:#fff; border:1.5px solid #cbd5e1; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:800; color:#94a3b8; flex:none; }
        .li.g .d { background:var(--green); border-color:var(--green); color:#fff; } .li.w .d { background:var(--amber); border-color:var(--amber); color:#fff; }
        .field { background:#f4f7fb; border-radius:12px; padding:10px 12px; margin-top:9px; font-size:12px; color:var(--muted); } .field b { display:block; color:var(--ink); font-size:15px; }
        .photo { margin-top:10px; border-radius:12px; background:#e8eef7; height:84px; display:flex; align-items:center; justify-content:center; gap:8px; font-size:12.5px; color:#1d4ed8; font-weight:700; position:relative; overflow:hidden; }
        .tabsb { position:absolute; left:0; right:0; bottom:0; background:#fff; border-top:1px solid #e2e8f0; display:flex; justify-content:space-around; padding:9px 4px 14px; z-index:4; }
        .tabsb span { display:flex; flex-direction:column; align-items:center; gap:2px; font-size:9.5px; color:#94a3b8; transition:color .3s; } .tabsb span .i { width:21px; height:21px; } .tabsb span.on { color:var(--blue); }
        .qrcard { display:grid; grid-template-columns:auto 1fr; gap:22px; align-items:center; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.12); border-radius:22px; padding:20px; }
        @media (max-width:560px) { .qrcard { grid-template-columns:1fr; justify-items:center; text-align:center; } }
        .qr { position:relative; background:#fff; border-radius:16px; padding:11px; line-height:0; overflow:hidden; } .qr svg { width:148px; height:148px; display:block; }
        .qr .qs { position:absolute; left:0; right:0; height:3px; background:var(--blue); box-shadow:0 0 14px var(--blue); animation:qrs 2.6s ease-in-out infinite; top:11px; } @keyframes qrs { 50% { top:calc(100% - 14px); } }
        .qrcard h4 { margin:0 0 4px; font-size:19px; } .qrcard p { margin:0 0 10px; color:rgba(255,255,255,.7); font-size:14px; }
        .url { display:inline-block; font-family:ui-monospace,Consolas,monospace; font-size:12.5px; background:rgba(255,255,255,.1); color:#bfdbfe; padding:6px 11px; border-radius:9px; margin-bottom:12px; word-break:break-all; }

        .stats { display:grid; grid-template-columns:repeat(4,1fr); gap:18px; }
        @media (max-width:760px) { .stats { grid-template-columns:repeat(2,1fr); } }
        .stat { text-align:center; padding:28px 12px; background:#fff; border:1px solid var(--line); border-radius:var(--radius); }
        .stat b { display:block; font-size:clamp(34px,4.4vw,52px); font-weight:800; letter-spacing:-.03em; color:var(--ink); line-height:1.1; } .stat span { color:var(--muted); font-size:14.5px; }

        .faq { max-width:820px; margin:0 auto; display:grid; gap:12px; }
        details { background:#fff; border:1px solid var(--line); border-radius:16px; padding:0 22px; transition:border-color .3s, box-shadow .3s; }
        details[open] { border-color:#bfd3fb; box-shadow:var(--shadow); }
        summary { cursor:pointer; list-style:none; padding:20px 0; font-weight:700; font-size:17px; color:var(--ink); display:flex; justify-content:space-between; gap:16px; align-items:center; }
        summary::-webkit-details-marker { display:none; } summary .i { transition:transform .3s; color:var(--blue); } details[open] summary .i { transform:rotate(180deg); }
        details p { margin:0 0 20px; color:var(--muted); }

        .final { background:var(--navy); color:#fff; text-align:center; overflow:hidden; isolation:isolate; }
        .final::before { content:""; position:absolute; inset:0; z-index:-1; background:radial-gradient(ellipse 60% 70% at 50% 120%, rgba(37,99,235,.75), transparent 70%); }
        .final h2 { color:#fff; margin-left:auto; margin-right:auto; max-width:740px; } .final .sub { color:rgba(255,255,255,.7); max-width:600px; margin:0 auto 34px; }
        .final .cta { justify-content:center; opacity:1; animation:none; }

        footer { background:#070d1c; color:rgba(255,255,255,.6); padding:48px 0 36px; font-size:14px; }
        .fgrid { display:flex; justify-content:space-between; gap:28px; flex-wrap:wrap; align-items:flex-start; }
        footer .brand { color:#fff; } footer a { text-decoration:none; } footer a:hover { color:#fff; }
        .flinks { display:flex; gap:22px; flex-wrap:wrap; } .copy { margin-top:30px; padding-top:20px; border-top:1px solid rgba(255,255,255,.08); font-size:13px; }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation:none !important; transition:none !important; }
            html { scroll-behavior:auto; } .reveal, .lead, .cta, .trust, .scene { opacity:1; transform:none; } h1.title .w > span { transform:none; }
            .ring .fg { stroke-dashoffset:var(--o); } .lv { opacity:1; }
        }
@endverbatim
    </style>
</head>
<body>
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <defs>
@verbatim
        <symbol id="i-qr" viewBox="0 0 24 24"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h3M20 14v3M14 20h3M20 20h.01M17 17v3"/></symbol>
        <symbol id="i-dash" viewBox="0 0 24 24"><path d="M4 4h7v9H4zM13 4h7v5h-7zM13 11h7v9h-7zM4 15h7v5H4z"/></symbol>
        <symbol id="i-cal" viewBox="0 0 24 24"><path d="M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zM16 3v4M8 3v4M4 10h16M9 15l2 2 4-4"/></symbol>
        <symbol id="i-bell" viewBox="0 0 24 24"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 8 3 8H3s3-1 3-8M10 20a2 2 0 0 0 4 0"/></symbol>
        <symbol id="i-route" viewBox="0 0 24 24"><circle cx="6" cy="19" r="2.2"/><circle cx="18" cy="5" r="2.2"/><path d="M8.2 19H15a3 3 0 0 0 0-6H9a3 3 0 0 1 0-6h6.8"/></symbol>
        <symbol id="i-chart" viewBox="0 0 24 24"><path d="M4 19h16M5 15l4-5 4 3 6-8"/></symbol>
        <symbol id="i-wifi" viewBox="0 0 24 24"><path d="M3 3l18 18M8.5 16.4a5 5 0 0 1 7 0M5 12.9a10 10 0 0 1 4-2.4M19 12.9a10 10 0 0 0-4-2.4M2 8.8A15 15 0 0 1 8 5.7M22 8.8a15 15 0 0 0-5.5-3.2M12 20h.01"/></symbol>
        <symbol id="i-fuel" viewBox="0 0 24 24"><path d="M4 21V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v16M3 21h12M14 9h2.5a1.5 1.5 0 0 1 1.5 1.5v6a1.5 1.5 0 0 0 3 0V8l-3-3M7 8h4"/></symbol>
        <symbol id="i-lock" viewBox="0 0 24 24"><path d="M6 11h12a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1zM8 11V8a4 4 0 0 1 8 0v3"/></symbol>
        <symbol id="i-phone" viewBox="0 0 24 24"><path d="M7 3h10a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zM11 18h2"/></symbol>
        <symbol id="i-print" viewBox="0 0 24 24"><path d="M7 8V4h10v4M7 17H5a1 1 0 0 1-1-1v-6a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1h-2M7 14h10v6H7z"/></symbol>
        <symbol id="i-check" viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></symbol>
        <symbol id="i-down" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></symbol>
        <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3l8 3v6c0 4.5-3.2 8-8 9-4.8-1-8-4.5-8-9V6zM9 12l2 2 4-4"/></symbol>
        <symbol id="i-file" viewBox="0 0 24 24"><path d="M7 3h7l5 5v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zM14 3v5h5M9 13h6M9 17h4"/></symbol>
        <symbol id="i-home" viewBox="0 0 24 24"><path d="M3 11l9-8 9 8M5 10v10h5v-6h4v6h5V10"/></symbol>
        <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></symbol>
@endverbatim
    </defs>
</svg>

<header class="nav" id="nav">
    <div class="wrap">
        <a class="brand" href="/"><img src="/pwa/icon-192.png" alt=""><span>LogiMaster<small>Pro · DDKM</small></span></a>
        <nav class="links">
            <a class="l" href="#parcours">Parcours</a>
            <a class="l" href="#fonctions">Fonctionnalités</a>
            <a class="l" href="#mobile">Application mobile</a>
            <a class="l" href="#faq">Questions</a>
            @if ($adminOpen)<a class="btn ghost sm" href="/admin"><svg class="i"><use href="#i-lock"/></svg>Administration</a>@endif
            <a class="btn primary sm" href="#installer"><svg class="i"><use href="#i-qr"/></svg>Installer</a>
        </nav>
    </div>
</header>

<main>
<div class="hero">
    <div class="blob b1"></div><div class="blob b2"></div>
    <div class="wrap hero-grid">
        <div>
            <span class="eyebrow"><b>NOUVEAU</b>Application convoyeur · installable, même hors réseau</span>
            <h1 class="title" aria-label="La logistique des districts sanitaires, en temps réel">
                <span class="w"><span style="animation-delay:.15s">La</span></span>
                <span class="w"><span style="animation-delay:.22s">logistique</span></span>
                <span class="w"><span style="animation-delay:.29s">des</span></span>
                <span class="w"><span style="animation-delay:.36s">districts</span></span>
                <span class="w"><span style="animation-delay:.43s">sanitaires,</span></span>
                <span class="w"><span style="animation-delay:.52s"><em>en temps réel</em></span></span>
            </h1>
            <p class="lead">Du chronogramme aux livraisons : le convoyeur exécute son circuit depuis son téléphone, le bureau voit tout, immédiatement.</p>
            <div class="cta">
                <a class="btn primary" href="#installer"><svg class="i"><use href="#i-qr"/></svg>Installer l'application</a>
                @if ($adminOpen)<a class="btn ghost" href="/admin"><svg class="i"><use href="#i-dash"/></svg>Accéder à l'administration</a>@endif
            </div>
            <div class="trust">
                <div><b data-n="{{ $stats['districts'] }}">{{ $stats['districts'] }}</b><span>districts sanitaires</span></div>
                <div><b data-n="9">9</b><span>indicateurs DDKM</span></div>
                <div><b>100 %</b><span>utilisable sans réseau</span></div>
            </div>
        </div>

        <div class="scene" aria-hidden="true">
            <div class="toasts" id="toasts"></div>
            <div class="browser">
                <div class="bar"><i></i><i></i><i></i><span>logimaster · suivi des livraisons</span></div>
                <div class="dash">
                    <div class="side"><i class="on"></i><i></i><i></i><i></i><i></i></div>
                    <div class="main">
                        <div class="kpis">
                            <div class="kpi"><small>Sites livrés</small><b id="kLiv">0</b><em>/ 4</em></div>
                            <div class="kpi"><small>Distance</small><b id="kKm">0</b><em>km</em></div>
                            <div class="kpi"><small>Dans les délais</small><b id="kPct">—</b></div>
                        </div>
                        <div class="mapbox">
                            <h5>CIRCUIT 5 · MEAGUI <span id="mapState">Départ 07:30</span></h5>
                            <svg class="route" viewBox="0 0 520 210">
                                <path id="routeBg" d="M36,172 C120,178 150,70 240,92 S396,170 484,44" fill="none" stroke="#e2e8f0" stroke-width="5" stroke-linecap="round"/>
                                <path id="routeFg" d="M36,172 C120,178 150,70 240,92 S396,170 484,44" fill="none" stroke="#2563eb" stroke-width="5" stroke-linecap="round"/>
                                <g id="stopsG"></g>
                                <g id="veh"><circle r="15" fill="rgba(37,99,235,.22)"><animate attributeName="r" values="12;19;12" dur="1.6s" repeatCount="indefinite"/></circle><circle r="8" fill="#2563eb" stroke="#fff" stroke-width="3"/></g>
                            </svg>
                        </div>
                        <div class="feed"><h5>Activité en direct</h5><div id="feed"></div></div>
                    </div>
                </div>
            </div>
            <div class="phone">
                <div class="notch"></div>
                <h6>Circuit en cours <span>● À jour</span></h6>
                <div class="pbar"><span id="pBar"></span></div>
                <div id="pStops"></div>
                <div class="pbtn" id="pBtn">Démarrer le circuit</div>
            </div>
        </div>
    </div>
    <div class="scroll-hint" aria-hidden="true"></div>
</div>

<section id="parcours">
    <div class="wrap">
        <div class="head center reveal"><span class="tag">Le parcours</span><h2>Du bureau au terrain, sans ressaisie</h2><p class="sub">Une seule information, de la planification à l'indicateur. Chaque étape s'allume au fil de votre lecture.</p></div>
        <div class="journey" id="journey">
            <div class="rail"><span></span></div>
            <div class="step reveal"><div class="n"><svg class="i"><use href="#i-cal"/></svg></div><div class="card"><h3>1 · Le bureau planifie et valide</h3><p>Sorties placées par glisser-déposer, équipe affectée, puis validation du mois par le superviseur : le planning est verrouillé.</p><div class="chips"><span class="chip">Glisser-déposer</span><span class="chip">Validation superviseur</span><span class="chip">Équipe par sortie</span></div></div></div>
            <div class="step reveal"><div class="n"><svg class="i"><use href="#i-bell"/></svg></div><div class="card"><h3>2 · L'équipe est prévenue</h3><p>Le chef de mission et les passagers reçoivent une notification sur leur téléphone dès la validation. Rien à téléphoner, rien à imprimer.</p><div class="chips"><span class="chip">Notification push</span><span class="chip">Accès créé avec la fiche</span></div></div></div>
            <div class="step reveal"><div class="n"><svg class="i"><use href="#i-route"/></svg></div><div class="card"><h3>3 · Le circuit s'exécute</h3><p>Site par site : livré, en transit ou non livré avec la raison. Carburant, photo de la facture et kilométrage sont saisis en route, même sans réseau.</p><div class="chips"><span class="chip">Livraison par site</span><span class="chip">Carburant + photo</span><span class="chip">Mode hors réseau</span></div></div></div>
            <div class="step reveal"><div class="n"><svg class="i"><use href="#i-chart"/></svg></div><div class="card"><h3>4 · Le suivi se met à jour</h3><p>Les livraisons apparaissent aussitôt dans le suivi web, et alimentent les 9 indicateurs DDKM, les rapports PDF et Excel et les alertes.</p><div class="chips"><span class="chip">Suivi en direct</span><span class="chip">9 indicateurs</span><span class="chip">Rapports PDF / Excel</span></div></div></div>
        </div>
    </div>
</section>

<section id="fonctions" style="background:#fff;border-block:1px solid var(--line)">
    <div class="wrap">
        <div class="head reveal"><span class="tag">Fonctionnalités</span><h2>Tout ce qu'il faut pour piloter une flotte de district</h2><p class="sub">Une plateforme web pour le bureau, une application pour le terrain, une seule source de vérité.</p></div>
        <div class="bento">
            <div class="cell c4 reveal"><div class="ico"><svg class="i"><use href="#i-cal"/></svg></div><h3>Chronogramme intelligent</h3><p>Vues semaine et mois, génération selon la fréquence des circuits, glisser-déposer, détection des doubles réservations et validation par le superviseur.</p>
                <div class="viz"><div class="cal"><b>Lun</b><b>Mar</b><b>Mer</b><b>Jeu</b><b>Ven</b><div><span class="chipc"></span></div><div></div><div><span class="chipc" style="background:#0d9488"></span></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div></div><span class="drag">C5</span><span class="lockb">Validé · verrouillé</span></div></div>
            <div class="cell c2 reveal" style="--d:.08s"><div class="ico"><svg class="i"><use href="#i-route"/></svg></div><h3>Suivi des livraisons</h3><p>Chaque site livré apparaît en direct, avec qui, quand et où.</p>
                <div class="viz"><div class="live"><div class="lv"><i></i>CSR TOUADJI 1<small>à l'instant</small></div><div class="lv"><i></i>CSR KOREAGUI 2<small>il y a 2 s</small></div><div class="lv"><i style="background:var(--amber)"></i>CSU OUPOYO · transit<small>il y a 5 s</small></div><div class="lv"><i></i>DR BONDOUKOU<small>il y a 8 s</small></div></div></div></div>

            <div class="cell c2 reveal"><div class="ico"><svg class="i"><use href="#i-chart"/></svg></div><h3>9 indicateurs DDKM</h3><p>Respect du chronogramme, utilisation, carburant, livraison ESPC…</p>
                <div class="viz"><div class="rings">
                    <div class="ring" style="--o:10"><svg viewBox="0 0 84 84"><circle class="bg" cx="42" cy="42" r="36"/><circle class="fg" cx="42" cy="42" r="36"/></svg><div class="num">96%</div>Livraison</div>
                    <div class="ring" style="--o:60"><svg viewBox="0 0 84 84"><circle class="bg" cx="42" cy="42" r="36"/><circle class="fg" cx="42" cy="42" r="36" style="stroke:#0d9488"/></svg><div class="num">73%</div>Circuits</div>
                </div></div></div>
            <div class="cell c2 reveal" style="--d:.08s"><div class="ico"><svg class="i"><use href="#i-fuel"/></svg></div><h3>Carburant maîtrisé</h3><p>Pleins rattachés à la sortie, photo de la facture, détection des anomalies.</p>
                <div class="viz"><div class="receipt"><b>TOTAL MÉAGUI</b><hr>Gasoil · 40,0 L<br>715 F / L<hr><div class="tot"><span>TOTAL</span><span>28 600 F</span></div><span class="scan"></span></div><span class="okmark">Facture jointe</span></div></div>
            <div class="cell c2 reveal" style="--d:.16s"><div class="ico"><svg class="i"><use href="#i-wifi"/></svg></div><h3>Pensé pour la route</h3><p>Sans réseau, rien ne se perd : tout est envoyé au retour.</p>
                <div class="viz"><div class="net"><div class="netc"><svg class="i"><use href="#i-wifi"/></svg></div><div class="netq"><span class="a"><b>3</b>actions en attente</span><span class="b"><b>0</b>tout est synchronisé</span></div></div></div></div>

            <div class="cell c3 reveal"><div class="ico"><svg class="i"><use href="#i-shield"/></svg></div><h3>Droits par rôle et par périmètre</h3><p>National, région, district, bailleur, convoyeur : chacun ne voit que son périmètre, et chaque écriture est journalisée.</p>
                <div class="viz" style="min-height:92px"><div class="roles"><span class="role">Administrateur national</span><span class="role">Responsable de région</span><span class="role">Gestionnaire de district</span><span class="role">Superviseur bailleur</span><span class="role">Convoyeur</span></div></div></div>
            <div class="cell c3 reveal" style="--d:.08s"><div class="ico"><svg class="i"><use href="#i-file"/></svg></div><h3>Rapports et import Excel</h3><p>Rapports PDF et Excel envoyés par e-mail, planifiables ; import des classeurs existants avec harmonisation automatique des noms, marques et circuits.</p>
                <div class="viz" style="min-height:92px;display:flex;gap:10px;align-items:center;flex-wrap:wrap"><span class="chip">Rapport DDKM</span><span class="chip">Carburant</span><span class="chip">Flotte</span><span class="chip">Maintenance</span><span class="chip">Par bailleur</span><span class="chip" style="background:var(--green-soft);color:#15803d">Import .xlsm</span></div></div>
        </div>
    </div>
</section>

<section class="mobile" id="mobile">
    <div class="wrap mgrid">
        <div>
            <div class="reveal"><span class="tag">Application convoyeur</span><h2>Dans la poche du chef de mission</h2><p class="sub">Une application installable, rapide, qui se manie d'une main. Pas de magasin d'applications, pas de mise à jour à gérer.</p></div>
            <ul class="benefits reveal" style="--d:.1s">
                <li><span class="bi"><svg class="i"><use href="#i-bell"/></svg></span><div><b>Prévenu dès la validation</b>La sortie arrive sur le téléphone, avec son équipe et son circuit.</div></li>
                <li><span class="bi"><svg class="i"><use href="#i-check"/></svg></span><div><b>Un geste par site</b>Livré, transit ou non livré avec sa raison, et le prochain site s'affiche.</div></li>
                <li><span class="bi"><svg class="i"><use href="#i-wifi"/></svg></span><div><b>Fonctionne sans réseau</b>Les actions sont gardées, puis envoyées avec leur heure réelle.</div></li>
                <li><span class="bi"><svg class="i"><use href="#i-lock"/></svg></span><div><b>Accès automatique et sûr</b>Identifiant et code créés avec la fiche, mot de passe choisi à la première connexion.</div></li>
            </ul>
            <div class="qrcard reveal" id="installer" style="--d:.15s">
                <div class="qr">{!! $qr !!}<span class="qs"></span></div>
                <div>
                    <h4>Installez-la en 10 secondes</h4>
                    <p>Scannez avec l'appareil photo. Android : « Installer l'application ». iPhone : Safari → Partager → « Sur l'écran d'accueil ».</p>
                    <span class="url">{{ $url }}</span><br>
                    <a class="btn primary sm" href="/m"><svg class="i"><use href="#i-phone"/></svg>Ouvrir l'application</a>
                    <a class="btn ghost sm" href="/m/installer"><svg class="i"><use href="#i-print"/></svg>Affiche à imprimer</a>
                </div>
            </div>
        </div>
        <div class="reveal" style="--d:.1s" aria-hidden="true">
            <div class="big-phone">
                <div class="ps on"><h4>Bonjour Ibrahim</h4><div class="hero-card"><small>Sortie validée · aujourd'hui 07:30</small><b>CIRCUIT 5</b><small>MEAGUI · 7 sites</small><div class="go">Démarrer le circuit</div></div><div class="li"><span class="d">→</span><div>CIRCUIT 2<small>Jeudi 16 oct. · 6 sites</small></div></div><div class="li g"><span class="d">✓</span><div>CIRCUIT 3<small>8 oct. · 7/7 livrés</small></div></div></div>
                <div class="ps"><h4>Circuit en cours</h4><div class="li g"><span class="d">✓</span><div>CSR TOUADJI 1<small>Livré 07:52</small></div></div><div class="li w"><span class="d">⇄</span><div>CSR KOREAGUI 2<small>En transit 08:40</small></div></div><div class="li"><span class="d">•</span><div>CSU OUPOYO<small>14 km · prochain site</small></div></div><div class="field"><b>2 / 7 sites traités</b>Terminer la sortie quand tout est fait</div></div>
                <div class="ps"><h4>Carburant</h4><div class="field">Litres<b>40</b></div><div class="field">Station<b>Total Méagui</b></div><div class="photo"><svg class="i"><use href="#i-file"/></svg>Photo de la facture</div></div>
                <div class="ps"><h4>Alertes</h4><div class="li w"><span class="d">!</span><div>Chronogramme validé<small>CIRCUIT 5 · mardi 14 oct.</small></div></div><div class="li"><span class="d">✓</span><div>Livraisons enregistrées<small>CIRCUIT 3 · hier</small></div></div></div>
                <div class="ps"><h4>Profil</h4><div class="li g"><span class="d">KI</span><div>Koné Ibrahim<small>Chef de mission</small></div></div><div class="field">Actions en attente<b>0</b></div><div class="field">Notifications<b>Activées</b></div></div>
                <div class="tabsb"><span class="on"><svg class="i"><use href="#i-home"/></svg>Accueil</span><span><svg class="i"><use href="#i-route"/></svg>Circuit</span><span><svg class="i"><use href="#i-fuel"/></svg>Carburant</span><span><svg class="i"><use href="#i-bell"/></svg>Alertes</span><span><svg class="i"><use href="#i-user"/></svg>Profil</span></div>
            </div>
        </div>
    </div>
</section>

<section style="padding:80px 0 40px">
    <div class="wrap">
        <div class="stats">
            <div class="stat reveal"><b data-n="{{ $stats['districts'] }}">{{ $stats['districts'] }}</b><span>districts sanitaires couverts</span></div>
            <div class="stat reveal" style="--d:.06s"><b data-n="9">9</b><span>indicateurs DDKM calculés</span></div>
            <div class="stat reveal" style="--d:.12s"><b data-n="5">5</b><span>rôles, chacun son périmètre</span></div>
            <div class="stat reveal" style="--d:.18s"><b data-n="5">5</b><span>écrans, un pouce suffit</span></div>
        </div>
    </div>
</section>

<section id="faq">
    <div class="wrap">
        <div class="head center reveal"><span class="tag">Questions fréquentes</span><h2>Ce qu'on nous demande le plus</h2></div>
        <div class="faq">
            <details class="reveal" open><summary>Comment un convoyeur obtient-il son accès ?<svg class="i"><use href="#i-down"/></svg></summary><p>Tout chef de mission ou passager actif est convoyeur d'office : son accès est créé avec sa fiche. Le bureau lui remet son identifiant et un code provisoire ; il choisit son mot de passe à la première connexion.</p></details>
            <details class="reveal" style="--d:.05s"><summary>Et s'il n'y a pas de réseau sur la route ?<svg class="i"><use href="#i-down"/></svg></summary><p>L'application garde les livraisons, le carburant et les photos sur le téléphone, puis les envoie au retour du réseau, avec l'heure réelle de chaque action. Rien n'est perdu ni compté deux fois.</p></details>
            <details class="reveal" style="--d:.1s"><summary>Faut-il installer quelque chose ?<svg class="i"><use href="#i-down"/></svg></summary><p>Non : on scanne le QR code, puis on touche « Installer l'application ». Elle fonctionne sur Android et sur iPhone, sans passer par un magasin d'applications.</p></details>
            <details class="reveal" style="--d:.15s"><summary>Qui voit quoi ?<svg class="i"><use href="#i-down"/></svg></summary><p>Chaque rôle a son périmètre : le convoyeur ne voit que ses sorties validées, le gestionnaire son district, le responsable sa région, le national tout. Chaque écriture est journalisée.</p></details>
            <details class="reveal" style="--d:.2s"><summary>Peut-on reprendre nos classeurs Excel ?<svg class="i"><use href="#i-down"/></svg></summary><p>Oui : l'import lit les classeurs Logimaster existants (sites, véhicules, circuits, activité) et harmonise les noms, marques, bailleurs et circuits pour qu'ils correspondent à l'application.</p></details>
        </div>
    </div>
</section>

<section class="final">
    <div class="wrap reveal">
        <h2>Prêt à voir vos livraisons en temps réel ?</h2>
        <p class="sub">Installez l'application sur un téléphone, validez une sortie, et regardez les sites se livrer.</p>
        <div class="cta">
            <a class="btn primary" href="#installer"><svg class="i"><use href="#i-qr"/></svg>Installer l'application</a>
            @if ($adminOpen)<a class="btn ghost" href="/admin"><svg class="i"><use href="#i-lock"/></svg>Se connecter à l'administration</a>@endif
        </div>
    </div>
</section>
</main>

<footer>
    <div class="wrap">
        <div class="fgrid">
            <div><a class="brand" href="/"><img src="/pwa/icon-192.png" alt=""><span>LogiMaster<small>Pro · DDKM</small></span></a><p style="margin:12px 0 0;max-width:340px">Gestion de flotte et de livraisons pour les districts sanitaires.</p></div>
            <div class="flinks"><a href="#parcours">Parcours</a><a href="#fonctions">Fonctionnalités</a><a href="/m">Application convoyeur</a><a href="/m/installer">Affiche d'installation</a>@if ($adminOpen)<a href="/admin">Administration</a>@endif</div>
        </div>
        <div class="copy">© LogiMaster Pro · Plateforme de gestion de flotte DDKM</div>
    </div>
</footer>

<script>
@verbatim
(function () {
    var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    var $ = function (id) { return document.getElementById(id); };

    var nav = $('nav');
    window.addEventListener('scroll', function () { nav.classList.toggle('solid', window.scrollY > 24); updateRail(); }, { passive: true });

    function count(el) {
        if (reduce || el.dataset.done) return; el.dataset.done = 1;
        var n = +el.dataset.n, t = 0, step = Math.max(1, Math.ceil(n / 40)); el.textContent = '0';
        var iv = setInterval(function () { t = Math.min(n, t + step); el.textContent = t; if (t >= n) clearInterval(iv); }, 32);
    }
    var io = ('IntersectionObserver' in window) ? new IntersectionObserver(function (es) {
        es.forEach(function (e) {
            if (!e.isIntersecting) return;
            e.target.classList.add('in'); io.unobserve(e.target);
            [].forEach.call(e.target.querySelectorAll('[data-n]'), count);
            if (e.target.hasAttribute('data-n')) count(e.target);
        });
    }, { threshold: .18 }) : null;
    [].forEach.call(document.querySelectorAll('.reveal'), function (el) { io ? io.observe(el) : el.classList.add('in'); });
    [].forEach.call(document.querySelectorAll('.trust [data-n]'), function (el) { setTimeout(function () { count(el); }, 1200); });

    var journey = $('journey'), steps = [].slice.call(journey.querySelectorAll('.step'));
    function updateRail() {
        var r = journey.getBoundingClientRect(), vh = window.innerHeight, p = Math.max(0, Math.min(1, (vh * .6 - r.top) / r.height));
        journey.style.setProperty('--p', (p * 100).toFixed(1) + '%');
        steps.forEach(function (s) { s.classList.toggle('on', s.getBoundingClientRect().top < vh * .62); });
    }
    updateRail(); window.addEventListener('resize', updateRail);

    var ps = [].slice.call(document.querySelectorAll('.ps')), tabs = [].slice.call(document.querySelectorAll('.tabsb span')), pi = 0;
    function showPs(i) { ps.forEach(function (p, j) { p.classList.toggle('on', j === i); }); tabs.forEach(function (t, j) { t.classList.toggle('on', j === i); }); }
    if (!reduce) setInterval(function () { pi = (pi + 1) % ps.length; showPs(pi); }, 3200);

    // héros : camion, téléphone, tableau de bord et notifications racontent la même histoire
    var fg = $('routeFg'), bg = $('routeBg'), veh = $('veh'), L = bg.getTotalLength(), stopsG = $('stopsG'), NS = 'http://www.w3.org/2000/svg';
    fg.setAttribute('stroke-dasharray', L);
    var STOPS = [
        { f: .27, name: 'CSR TOUADJI 1', st: 'g', msg: 'Livré sur site' },
        { f: .52, name: 'CSR KOREAGUI 2', st: 'g', msg: 'Livré sur site' },
        { f: .76, name: 'CSU OUPOYO', st: 'w', msg: 'Livré en transit' },
        { f: 1, name: 'DR BONDOUKOU', st: 'g', msg: 'Livré sur site' }
    ];
    function circle(pt, r, fill, stroke) {
        var c = document.createElementNS(NS, 'circle'); c.setAttribute('cx', pt.x); c.setAttribute('cy', pt.y); c.setAttribute('r', r); c.setAttribute('fill', fill);
        if (stroke) { c.setAttribute('stroke', stroke); c.setAttribute('stroke-width', 3); } stopsG.appendChild(c); return c;
    }
    circle(bg.getPointAtLength(0), 6, '#0f172a');
    STOPS.forEach(function (s) { s.el = circle(bg.getPointAtLength(L * s.f), 7, '#fff', '#cbd5e1'); });

    var pStops = $('pStops'), feed = $('feed'), toasts = $('toasts');
    function renderPhone(done) {
        pStops.innerHTML = STOPS.map(function (s, i) { return '<div class="st ' + (i < done ? s.st : '') + '"><span class="d">' + (i < done ? (s.st === 'w' ? '⇄' : '✓') : '•') + '</span>' + s.name + '</div>'; }).join('');
        $('pBar').style.width = (done / STOPS.length * 100) + '%'; $('kLiv').textContent = done;
    }
    function toast(kind, icon, title, text) {
        var t = document.createElement('div'); t.className = 'toast ' + kind; t.innerHTML = '<span class="ic">' + icon + '</span><div><b>' + title + '</b><span>' + text + '</span></div>'; toasts.appendChild(t);
        while (toasts.children.length > 3) toasts.removeChild(toasts.firstChild);
        setTimeout(function () { t.classList.add('out'); setTimeout(function () { t.remove(); }, 420); }, 3400);
    }
    function addEvent(color, text) {
        var e = document.createElement('div'); e.className = 'ev'; e.innerHTML = '<i style="background:' + color + '"></i>' + text + '<small>à l\'instant</small>'; feed.insertBefore(e, feed.firstChild);
        while (feed.children.length > 3) feed.removeChild(feed.lastChild);
    }
    function place(f) { var pt = bg.getPointAtLength(L * f); veh.setAttribute('transform', 'translate(' + pt.x + ',' + pt.y + ')'); fg.setAttribute('stroke-dashoffset', L * (1 - f)); $('kKm').textContent = Math.round(f * 62); }
    function paint(s) { s.el.setAttribute('fill', s.st === 'w' ? '#f59e0b' : '#22c55e'); s.el.setAttribute('stroke', '#fff'); }

    if (reduce) {
        renderPhone(4); place(1); $('kPct').textContent = '100 %'; $('mapState').textContent = 'Terminé · 4/4'; STOPS.forEach(paint);
        $('pBtn').textContent = 'Sortie terminée'; $('pBtn').classList.add('done'); return;
    }

    // chronologie d'un cycle (ms) : validation → départ → 4 trajets avec arrêt → fin
    var TRAVEL = 2300, DWELL = 1500, LEAD = 2200, TAIL = 4200, marks = [], t = LEAD, prev = 0;
    STOPS.forEach(function (s) { marks.push({ from: t, to: t + TRAVEL, f0: prev, f1: s.f, arrive: t + TRAVEL }); t += TRAVEL + DWELL; prev = s.f; });
    var CYCLE = t + TAIL, fired = {}, t0 = performance.now(), lastCycle = -1;

    function reset() {
        fired = {}; renderPhone(0); feed.innerHTML = ''; place(0); $('kPct').textContent = '—'; $('mapState').textContent = 'Départ 07:30';
        $('pBtn').textContent = 'Démarrer le circuit'; $('pBtn').classList.remove('done');
        STOPS.forEach(function (s) { s.el.setAttribute('fill', '#fff'); s.el.setAttribute('stroke', '#cbd5e1'); });
    }
    function ease(x) { return x < .5 ? 2 * x * x : 1 - Math.pow(-2 * x + 2, 2) / 2; }
    function frame(now) {
        var cyc = Math.floor((now - t0) / CYCLE), el = (now - t0) % CYCLE;
        if (cyc !== lastCycle) { lastCycle = cyc; reset(); }
        if (el > 500 && !fired.valid) { fired.valid = 1; toast('', '✓', 'Chronogramme validé', 'CIRCUIT 5 · MEAGUI · 07:30'); addEvent('#2563eb', 'Chronogramme validé · superviseur'); }
        if (el > LEAD - 300 && !fired.go) { fired.go = 1; $('pBtn').textContent = 'Circuit en cours'; $('mapState').textContent = 'En cours'; addEvent('#0f172a', 'Circuit démarré · Koné Ibrahim'); }
        var f = 0;
        marks.forEach(function (m, i) {
            if (el >= m.from && el <= m.to) f = m.f0 + (m.f1 - m.f0) * ease((el - m.from) / TRAVEL); else if (el > m.to) f = m.f1;
            if (el >= m.arrive && !fired['s' + i]) {
                fired['s' + i] = 1; var s = STOPS[i], col = s.st === 'w' ? '#f59e0b' : '#22c55e';
                paint(s); renderPhone(i + 1); addEvent(col, s.name + ' · ' + s.msg.toLowerCase());
                toast(s.st === 'w' ? 'warn' : 'ok', s.st === 'w' ? '⇄' : '✓', 'Livraison enregistrée', s.name + ' · ' + s.msg);
                $('kPct').textContent = (i === 2 ? '75' : '100') + ' %';
            }
        });
        place(f);
        if (el >= CYCLE - TAIL + 600 && !fired.end) { fired.end = 1; $('pBtn').textContent = 'Sortie terminée'; $('pBtn').classList.add('done'); $('mapState').textContent = 'Terminé · 4/4'; toast('', '★', 'Sortie terminée', '62 km · 4 sites traités'); addEvent('#2563eb', 'Sortie terminée · 62 km'); }
        requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
})();
@endverbatim
</script>
</body>
</html>
