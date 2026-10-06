<x-filament-widgets::widget>
    <x-filament::section :heading="'Analyse par véhicule et par motif — ' . ucfirst($month->translatedFormat('F Y'))"
        description="Même période et même périmètre que les indicateurs DDKM ci-dessus.">
        <div style="display:grid;gap:22px;grid-template-columns:repeat(auto-fit,minmax(520px,1fr))">
            @foreach ($panels as $p)
                <div>
                    <h4 style="margin:0;font-size:.95rem;font-weight:600">{{ $p['title'] }}</h4>
                    <p style="margin:2px 0 8px;font-size:.8rem;opacity:.65">{{ $p['help'] }}</p>
                    @if ($p['svg'])
                        <div style="border:1px solid rgba(127,127,127,.2);border-radius:12px;overflow:hidden;background:#fff">{!! str_replace('<svg ', '<svg style="width:100%;height:auto;display:block" ', $p['svg']) !!}</div>
                        @if ($p['more'])<p style="margin:4px 0 0;font-size:.75rem;opacity:.6">… et {{ $p['more'] }} autre(s) véhicule(s)</p>@endif
                        @if ($p['note'] ?? null)<p style="margin:4px 0 0;font-size:.75rem;opacity:.6">{{ $p['note'] }}</p>@endif
                    @else
                        <p style="margin:0;padding:24px;text-align:center;font-size:.85rem;opacity:.6;border:1px dashed rgba(127,127,127,.3);border-radius:12px">Aucune donnée sur la période et le périmètre choisis.</p>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
