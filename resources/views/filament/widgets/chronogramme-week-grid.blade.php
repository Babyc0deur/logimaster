@php
    $motifLabels = \App\Models\SortieVehicule::MOTIFS;
    $statusBg = ['realisee' => '#d1fae5', 'reportee' => '#fef3c7'];
    $chip = function ($plan) use ($colors, $statusBg) {
        $bg = $plan->est_en_retard ? '#fee2e2' : ($statusBg[$plan->statut] ?? '#f8fafc');
        $color = $colors[$plan->motif] ?? '#6b7280';
        return "display:block;margin-bottom:4px;padding:5px 9px;border-radius:8px;text-decoration:none;color:inherit;background:{$bg};border-left:5px solid {$color};font-size:.88rem;line-height:1.35";
    };
    $draggable = fn ($plan) => $canEdit && ! $plan->isLocked() && ! in_array($plan->statut, ['realisee', 'annulee'], true);
@endphp

<x-filament-widgets::widget>
    <x-filament::section :heading="$mode === 'month' ? 'Calendrier du mois' : 'Planning de la semaine'">
        <x-slot name="afterHeader">
            <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                <x-filament::button size="sm" :color="$mode === 'week' ? 'primary' : 'gray'" wire:click="setMode('week')">Semaine</x-filament::button>
                <x-filament::button size="sm" :color="$mode === 'month' ? 'primary' : 'gray'" wire:click="setMode('month')">Mois</x-filament::button>
                <span style="width:1px;height:1.4rem;background:#e5e7eb"></span>
                <x-filament::button size="sm" color="gray" wire:click="previous" icon="heroicon-m-chevron-left" />
                <x-filament::button size="sm" color="gray" wire:click="today">{{ $label }}</x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="next" icon="heroicon-m-chevron-right" />
            </div>
        </x-slot>

        <div x-data="{ dragging: null, over: null }" style="overflow-x:auto">
            @if ($mode === 'week')
                <table style="width:100%;border-collapse:collapse;font-size:.95rem;min-width:1100px">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding:.7rem;border-bottom:1px solid #e5e7eb">Véhicule</th>
                            @foreach ($days as $day)
                                <th style="padding:.7rem;border-bottom:1px solid #e5e7eb;font-size:1rem;{{ $day->isToday() ? 'background:#eff6ff' : '' }}">{{ $day->translatedFormat('D d/m') }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vehicles as $vehicle)
                            <tr>
                                <td style="padding:.7rem;border-bottom:1px solid #f3f4f6;font-weight:600;white-space:nowrap;font-size:1rem">{{ $vehicle->immatriculation }}</td>
                                @foreach ($days as $day)
                                    @php $cellKey = $vehicle->id.'|'.$day->toDateString(); @endphp
                                    <td style="padding:.4rem;height:96px;border-bottom:1px solid #f3f4f6;vertical-align:top;min-width:150px;{{ $day->isToday() ? 'background:#f8fafc;' : '' }}"
                                        :style="over === '{{ $cellKey }}' ? 'outline:2px dashed #2563eb;outline-offset:-2px;background:#eff6ff' : ''"
                                        @if ($canEdit)
                                            x-on:dragover.prevent="over = '{{ $cellKey }}'"
                                            x-on:dragleave="over === '{{ $cellKey }}' && (over = null)"
                                            x-on:drop.prevent="if (dragging) { $wire.movePlan(dragging, '{{ $day->toDateString() }}', '{{ $vehicle->id }}'); } dragging = null; over = null"
                                        @endif>
                                        @foreach ($plansByVehicleDay->get($cellKey, collect()) as $plan)
                                            <a href="{{ \App\Filament\Resources\Chronogrammes\ChronogrammeResource::getUrl('edit', ['record' => $plan]) }}"
                                               style="{{ $chip($plan) }}" title="{{ $motifLabels[$plan->motif] ?? $plan->motif }}"
                                               @if ($draggable($plan)) draggable="true" x-on:dragstart="dragging = '{{ $plan->id }}'; $event.dataTransfer.effectAllowed = 'move'" x-on:dragend="dragging = null; over = null" @endif>
                                                @if ($plan->isLocked()) 🔒 @endif<strong>{{ $plan->heure_depart ? substr($plan->heure_depart, 0, 5) : '' }}</strong>
                                                {{ $plan->circuit?->nom ?? ($motifLabels[$plan->motif] ?? $plan->motif) }}
                                                <br><small style="font-size:.8rem;opacity:.8">{{ $plan->driver?->nom_complet ?? 'Sans chauffeur' }}</small>
                                            </a>
                                        @endforeach
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <table style="width:100%;border-collapse:collapse;table-layout:fixed;min-width:1000px">
                    <thead>
                        <tr>@foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $d)<th style="padding:.7rem;border-bottom:1px solid #e5e7eb;font-size:1rem">{{ $d }}</th>@endforeach</tr>
                    </thead>
                    <tbody>
                        @foreach ($weeks as $week)
                            <tr>
                                @foreach ($week as $day)
                                    @php $cellKey = $day->toDateString(); @endphp
                                    <td style="height:170px;vertical-align:top;padding:8px;border:1px solid #f3f4f6;{{ $day->month !== $month->month ? 'opacity:.45;' : '' }}{{ $day->isToday() ? 'background:#eff6ff;' : '' }}"
                                        :style="over === '{{ $cellKey }}' ? 'outline:2px dashed #2563eb;outline-offset:-2px;background:#eff6ff' : ''"
                                        @if ($canEdit)
                                            x-on:dragover.prevent="over = '{{ $cellKey }}'"
                                            x-on:dragleave="over === '{{ $cellKey }}' && (over = null)"
                                            x-on:drop.prevent="if (dragging) { $wire.movePlan(dragging, '{{ $cellKey }}'); } dragging = null; over = null"
                                        @endif>
                                        <div style="display:flex;justify-content:space-between;font-size:1rem;font-weight:600;margin-bottom:4px">
                                            <span>{{ $day->day }}</span>
                                            @if ($canEdit && ! $day->isBefore(today()))
                                                <a href="{{ $createUrl }}?date={{ $cellKey }}" title="Planifier une sortie ce jour" style="text-decoration:none;color:#2563eb;font-size:1.2rem;line-height:1">＋</a>
                                            @endif
                                        </div>
                                        @foreach ($plansByDay->get($cellKey, collect()) as $plan)
                                            <a href="{{ \App\Filament\Resources\Chronogrammes\ChronogrammeResource::getUrl('edit', ['record' => $plan]) }}"
                                               style="{{ $chip($plan) }}" title="{{ $motifLabels[$plan->motif] ?? $plan->motif }} — {{ $plan->vehicle?->immatriculation ?? 'sans véhicule' }}"
                                               @if ($draggable($plan)) draggable="true" x-on:dragstart="dragging = '{{ $plan->id }}'; $event.dataTransfer.effectAllowed = 'move'" x-on:dragend="dragging = null; over = null" @endif>
                                                @if ($plan->isLocked()) 🔒 @endif{{ $plan->circuit?->nom ?? ($motifLabels[$plan->motif] ?? $plan->motif) }}
                                            </a>
                                        @endforeach
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div style="margin-top:.9rem;display:flex;flex-wrap:wrap;gap:1.1rem;font-size:.85rem;color:#6b7280">
            @foreach ($colors as $motif => $color)
                <span><span style="display:inline-block;width:.7rem;height:.7rem;border-radius:2px;background:{{ $color }};vertical-align:middle"></span> {{ $motifLabels[$motif] ?? $motif }}</span>
            @endforeach
            <span>· Fond vert : réalisée · jaune : reportée · rouge : en retard · 🔒 validée par le superviseur</span>
            @if ($canEdit)<span>· Glissez une sortie pour la reprogrammer</span>@endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
