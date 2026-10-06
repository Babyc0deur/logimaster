{{-- Photos de terrain : [['titre' => ..., 'src' => data URL ou null], ...] --}}
<div style="display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
    @foreach ($photos as $p)
        <figure style="margin:0;text-align:center">
            @if ($p['src'])
                <img src="{{ $p['src'] }}" alt="{{ $p['titre'] }}" style="max-width:100%;max-height:60vh;border-radius:12px;border:1px solid rgba(127,127,127,.3)">
            @else
                <div style="padding:40px 12px;border:1px dashed rgba(127,127,127,.4);border-radius:12px;opacity:.7">Pas de photo</div>
            @endif
            <figcaption style="margin-top:6px;font-size:.85rem;opacity:.75">{{ $p['titre'] }}</figcaption>
        </figure>
    @endforeach
</div>
