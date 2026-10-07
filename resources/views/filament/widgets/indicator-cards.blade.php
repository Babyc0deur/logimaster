<x-filament-widgets::widget>
    <x-filament::section heading="Indicateurs DDKM"
                         description="Cliquez sur un indicateur pour voir son détail, son évolution et les écarts.">
        @if ($empty)
            <p style="font-size:.85rem;color:#6b7280">Aucun indicateur calculé pour cette période et ce périmètre. Les indicateurs sont recalculés chaque nuit
                (<code>php artisan indicators:compute</code>) ; vous pouvez aussi les recalculer depuis la page « Indicateurs DDKM ».</p>
        @endif
        <style>
            .lm-ind-grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:.75rem; }
            @media (max-width: 1100px) { .lm-ind-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); } }
            @media (max-width: 560px) { .lm-ind-grid { grid-template-columns:1fr; } }
        </style>
        <div class="lm-ind-grid">
            @foreach ($cards as $card)
                @php
                    $border = ['success' => '#10b981', 'warning' => '#f59e0b', 'danger' => '#ef4444', 'gray' => 'var(--lm-rule, #d1d5db)'][$card['color']];
                @endphp
                <a href="{{ $card['url'] }}" title="{{ $card['definition'] }}"
                   style="display:block;text-decoration:none;color:inherit;border:1px solid var(--lm-rule, #e5e7eb);border-left:4px solid {{ $border }};border-radius:.6rem;padding:.7rem .8rem">
                    <div style="font-size:.72rem;color:#6b7280;display:flex;gap:.35rem;align-items:center">
                        <x-filament::icon :icon="$card['icon']" style="width:1rem;height:1rem" /> {{ $card['label'] }}
                    </div>
                    <div style="font-size:1.45rem;font-weight:700;margin-top:.15rem">{{ $card['value'] }}</div>
                    <div style="font-size:.72rem;margin-top:.1rem;min-height:1rem;color:#6b7280">
                        @if ($card['delta'])
                            <span style="color:{{ $card['delta_good'] === null ? '#6b7280' : ($card['delta_good'] ? '#059669' : '#dc2626') }};font-weight:600">
                                {{ $card['delta_good'] === false ? '▼' : '▲' }} {{ $card['delta'] }}
                            </span> {{ $compare }}
                        @endif
                    </div>
                    <div style="font-size:.7rem;color:#9ca3af">{{ $card['sub'] }}</div>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
