<div style="text-align:center">
    <div style="display:inline-block;background:#fff;padding:12px;border-radius:14px;border:1px solid rgba(127,127,127,.3);line-height:0">{!! $svg !!}</div>
    <p style="margin:14px 0 4px;font-size:.9rem">Le convoyeur scanne ce code avec l'appareil photo de son téléphone, puis touche « Installer l'application ».</p>
    <p style="font-family:ui-monospace,Consolas,monospace;font-size:.85rem;opacity:.8;word-break:break-all;margin:0 0 12px">{{ $url }}</p>
    @unless (str_starts_with($url, 'https://'))
        <p style="font-size:.8rem;color:#b45309;margin:0 0 12px">Adresse non sécurisée : l'installation et les notifications exigent le HTTPS. Renseignez <code>LOGIMASTER_MOBILE_URL</code> dans .env.</p>
    @endunless
    <a href="/m/installer" target="_blank" style="font-size:.9rem;text-decoration:underline">Ouvrir l'affiche imprimable</a>
</div>
