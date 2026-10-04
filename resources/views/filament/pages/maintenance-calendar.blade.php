<x-filament-panels::page>
    @php
        $colors = ['urgent' => ['#fee2e2', '#b91c1c'], 'attention' => ['#ffedd5', '#c2410c'], 'ok' => ['#dcfce7', '#15803d']];
    @endphp

    <x-filament::section>
        <x-slot name="heading">{{ ucfirst($month->translatedFormat('F Y')) }}</x-slot>
        <x-slot name="afterHeader">
            <div style="display:flex;gap:.5rem">
                <x-filament::button size="sm" color="gray" wire:click="previousMonth" icon="heroicon-m-chevron-left" />
                <x-filament::button size="sm" color="gray" wire:click="currentMonth">Aujourd'hui</x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="nextMonth" icon="heroicon-m-chevron-right" />
            </div>
        </x-slot>

        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;table-layout:fixed;min-width:760px">
                <thead>
                    <tr>
                        @foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $day)
                            <th style="padding:.4rem;border-bottom:1px solid #e5e7eb;font-size:.8rem">{{ $day }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($weeks as $week)
                        <tr>
                            @foreach ($week as $day)
                                <td style="height:96px;vertical-align:top;padding:4px;border:1px solid #f3f4f6;{{ $day->month !== $month->month ? 'opacity:.4;' : '' }}{{ $day->isToday() ? 'background:#eff6ff;' : '' }}">
                                    <div style="font-size:.75rem;font-weight:600">{{ $day->day }}</div>
                                    @foreach ($events->get($day->toDateString(), collect()) as $event)
                                        <div title="{{ $event['label'] }}" style="font-size:.68rem;line-height:1.2;margin-top:2px;padding:2px 4px;border-radius:4px;background:{{ $colors[$event['level']->value][0] }};color:{{ $colors[$event['level']->value][1] }};overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                            {{ $event['level']->emoji() }} {{ $event['label'] }}
                                        </div>
                                    @endforeach
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p style="margin-top:.5rem;font-size:.75rem;color:#6b7280">
            🔴 Urgent (&lt; 100 km ou &lt; 7 jours) · 🟠 Attention (&lt; 500 km ou &lt; 30 jours) · 🟢 OK.
            Les dates de vidange sont estimées d'après le rythme moyen du véhicule sur 90 jours.
        </p>
    </x-filament::section>

    <x-filament::section heading="Alertes prioritaires">
        @forelse ($alerts as $alert)
            <div style="display:flex;gap:.5rem;align-items:center;padding:.3rem 0;border-bottom:1px solid #f3f4f6;font-size:.85rem">
                <x-filament::badge :color="$alert['level']->color()">{{ $alert['level']->emoji() }} {{ $alert['level']->label() }}</x-filament::badge>
                <span>{{ $alert['message'] }}</span>
            </div>
        @empty
            <p style="font-size:.85rem">🟢 Aucune alerte : tout est à jour.</p>
        @endforelse
    </x-filament::section>
</x-filament-panels::page>
