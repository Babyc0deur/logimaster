<x-filament-widgets::widget id="indicateur-detail" style="scroll-margin-top:80px">
    <x-filament::section :heading="$meta['label'] . ' — ' . ucfirst($month->translatedFormat('F Y'))" :description="$meta['definition']">
        @if ($empty)
            <p style="font-size:.9rem;color:#6b7280">Aucune activité enregistrée pour ce mois et ce périmètre : choisissez d'autres dates ou un autre district.</p>
        @else
            <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end;margin-bottom:1rem">
                @php $c = ['success' => '#059669', 'warning' => '#d97706', 'danger' => '#dc2626', 'gray' => '#111827'][$color]; @endphp
                <div>
                    <div style="font-size:2.1rem;font-weight:800;color:{{ $c }};line-height:1.1">{{ $value }}</div>
                    <div style="font-size:.75rem;color:#6b7280">
                        @if ($previous !== null) Mois précédent : {{ $previous }}@if ($delta_pct !== null) ({{ sprintf('%+.1f', $delta_pct) }} %)@endif @endif
                        @if ($target) · Objectif : {{ $target }} @endif
                        · {{ $districts }} district(s)
                    </div>
                </div>
                @foreach ($detail['kpis'] as $kpi)
                    <div style="border-left:3px solid #e5e7eb;padding-left:.7rem">
                        <div style="font-size:.7rem;color:#6b7280">{{ $kpi['label'] }}</div>
                        <div style="font-weight:700">{{ $kpi['value'] }}</div>
                    </div>
                @endforeach
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:1rem">
                @foreach ($detail['tables'] as $table)
                    <div style="overflow-x:auto">
                        <div style="font-weight:600;font-size:.85rem;margin-bottom:.3rem">{{ $table['title'] }}</div>
                        @if (count($table['rows']))
                            <table style="width:100%;border-collapse:collapse;font-size:.8rem">
                                <thead>
                                    <tr style="text-align:left;border-bottom:1px solid #e5e7eb">
                                        @foreach ($table['headers'] as $h)<th style="padding:.3rem .4rem;font-weight:600">{{ $h }}</th>@endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($table['rows'] as $r)
                                        <tr style="border-bottom:1px solid #f3f4f6">
                                            @foreach ($r as $cell)<td style="padding:.3rem .4rem">{{ $cell }}</td>@endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            <p style="font-size:.8rem;color:#9ca3af">Rien à signaler sur la période.</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
