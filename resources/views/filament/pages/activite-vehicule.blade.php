<x-filament-panels::page>
    @php
        $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
        $line = 'padding:.35rem 0;border-top:1px solid rgba(127,127,127,.18)';
        $box = 'border:1px solid rgba(127,127,127,.25);border-radius:.75rem;padding:1rem;margin-bottom:1rem';
        $types = \App\Models\Expense::TYPES;
        $motifs = \App\Models\SortieVehicule::MOTIFS;
    @endphp

    <div style="{{ $box }}">
        <div style="display:flex;flex-wrap:wrap;gap:1.5rem;align-items:flex-start;justify-content:space-between">
            <div>
                <div style="font-size:1.1rem;font-weight:700">{{ $vehicle->immatriculation }}</div>
                <div style="opacity:.8">{{ trim($vehicle->marque.' '.$vehicle->modele) ?: 'Véhicule' }}@if ($vehicle->km_actuel) · {{ $fmt($vehicle->km_actuel) }} km @endif</div>
                <div style="margin-top:.35rem">Propriétaire : <strong>{{ $vehicle->district?->name ?? '—' }}</strong>@if (! $owner) <span style="opacity:.7">· mis à la disposition de votre district</span>@endif</div>
            </div>
            <div style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:end">
                <label style="font-size:.85rem">Du<br><input type="date" wire:model.live="du" max="{{ today()->toDateString() }}" style="border:1px solid rgba(127,127,127,.4);border-radius:.5rem;padding:.4rem .6rem;background:transparent"></label>
                <label style="font-size:.85rem">Au<br><input type="date" wire:model.live="au" max="{{ today()->toDateString() }}" style="border:1px solid rgba(127,127,127,.4);border-radius:.5rem;padding:.4rem .6rem;background:transparent"></label>
            </div>
        </div>

        <div style="margin-top:.9rem">
            <div style="font-weight:600;margin-bottom:.25rem">Mises à disposition</div>
            @forelse ($periods as $p)
                <div style="{{ $line }}">
                    {{ $vehicle->district?->name }} → <strong>{{ $p->district?->name }}</strong> · du {{ $p->date_debut->format('d/m/Y') }} {{ $p->date_fin ? 'au '.$p->date_fin->format('d/m/Y') : '(sans fin)' }}
                    · {{ \App\Models\VehicleMutualisation::ETATS[$p->etat()] }}@if ($p->motif) · <span style="opacity:.75">{{ $p->motif }}</span>@endif
                </div>
            @empty
                <div style="opacity:.7">Ce véhicule n'est mis à la disposition d'aucun autre district.</div>
            @endforelse
            @if ($ranges !== null)
                <div style="margin-top:.5rem;font-size:.85rem;opacity:.75">Vous voyez l'activité du véhicule pendant ces périodes seulement.</div>
            @endif
        </div>
    </div>

    <div style="{{ $box }}">
        <div style="font-weight:600;margin-bottom:.5rem">Résumé par district (période choisie)</div>
        @forelse ($activity['totaux'] as $t)
            <div style="{{ $line }};display:grid;grid-template-columns:minmax(120px,1.4fr) repeat(5,minmax(70px,1fr));gap:.5rem;align-items:center">
                <strong>{{ $t['district'] }}</strong>
                <span>{{ $t['sorties'] }} sortie{{ $t['sorties'] > 1 ? 's' : '' }}</span>
                <span>{{ $fmt($t['km']) }} km</span>
                <span>Carburant {{ $fmt($t['carburant']) }}</span>
                <span>Frais {{ $fmt($t['frais']) }}</span>
                <strong>{{ $fmt($t['total']) }} FCFA</strong>
            </div>
        @empty
            <div style="opacity:.7">Aucune activité sur cette période.</div>
        @endforelse
    </div>

    <div style="display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(320px,1fr))">
        <div style="{{ $box }}">
            <div style="font-weight:600;margin-bottom:.5rem">Sorties</div>
            @forelse ($activity['sorties'] as $s)
                <div style="{{ $line }}">
                    {{ $s->date_sortie?->format('d/m/Y') }} · <strong>{{ $s->district?->name }}</strong> · {{ $motifs[$s->motif] ?? $s->motif }}{{ $s->circuit ? ' · '.$s->circuit->nom : '' }}
                    @if ($s->km_arrivee !== null && $s->km_depart !== null) · {{ $fmt($s->km_arrivee - $s->km_depart) }} km @endif
                </div>
            @empty
                <div style="opacity:.7">Aucune sortie.</div>
            @endforelse
        </div>
        <div style="{{ $box }}">
            <div style="font-weight:600;margin-bottom:.5rem">Carburant</div>
            @forelse ($activity['pleins'] as $p)
                <div style="{{ $line }}">{{ $p->date_ravitaillement?->format('d/m/Y') }} · <strong>{{ $p->district?->name }}</strong> · {{ rtrim(rtrim(number_format((float) $p->litres, 2, ',', ' '), '0'), ',') }} L · {{ $fmt($p->litres * $p->prix_unitaire) }} FCFA</div>
            @empty
                <div style="opacity:.7">Aucun plein.</div>
            @endforelse
        </div>
        <div style="{{ $box }}">
            <div style="font-weight:600;margin-bottom:.5rem">Autres frais</div>
            @forelse ($activity['frais'] as $f)
                <div style="{{ $line }}">{{ $f->date_depense?->format('d/m/Y') }} · <strong>{{ $f->district?->name }}</strong> · {{ $types[$f->type] ?? $f->type }}{{ $f->beneficiaire ? ' · '.$f->beneficiaire : '' }} · {{ $fmt($f->montant) }} FCFA</div>
            @empty
                <div style="opacity:.7">Aucun frais.</div>
            @endforelse
        </div>
        <div style="{{ $box }}">
            <div style="font-weight:600;margin-bottom:.5rem">Vidanges et immobilisations</div>
            @forelse ($activity['entretiens'] as $e)
                <div style="{{ $line }}">{{ \Carbon\Carbon::parse($e['date'])->format('d/m/Y') }} · <strong>{{ $e['district'] }}</strong> · {{ $e['libelle'] }}@if ($e['montant'] > 0) · {{ $fmt($e['montant']) }} FCFA @endif</div>
            @empty
                <div style="opacity:.7">Aucun événement.</div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
