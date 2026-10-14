@php
    $colors = [
        'livre' => ['#16a34a', 'rgba(22,163,74,.14)', 'Livrée', 'heroicon-m-check'],
        'alerte' => ['#d97706', 'rgba(217,119,6,.16)', 'Transit / retard', 'heroicon-m-arrows-right-left'],
        'non_livre' => ['#dc2626', 'rgba(220,38,38,.14)', 'Non livrée', 'heroicon-m-x-mark'],
        'avenir' => ['#9ca3af', 'rgba(156,163,175,.16)', 'À venir', 'heroicon-m-minus'],
    ];
    $neutral = '#6b7280';
    $card = 'border:1px solid rgba(127,127,127,.28);border-radius:12px;padding:1rem 1.25rem;background:rgba(127,127,127,.04)';
    $metric = 'background:rgba(127,127,127,.09);border-radius:8px;padding:10px 12px';
    $node = 'width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex:none;border:2px solid';
    $km = fn ($v) => str_replace('.', ',', $v);
@endphp

<x-filament-panels::page>
    <style>
        .sc-pulse { position:relative; }
        .sc-pulse::after { content:""; position:absolute; inset:-5px; border-radius:50%; border:2px solid #c2410c; opacity:.0; animation:sc-ring 1.8s ease-out infinite; }
        @keyframes sc-ring { 0% { transform:scale(.8); opacity:.7; } 100% { transform:scale(1.5); opacity:0; } }
        .sc-dot { width:9px; height:9px; border-radius:50%; background:#c2410c; display:inline-block; animation:sc-blink 1.4s ease-in-out infinite; }
        @keyframes sc-blink { 0%, 100% { opacity:1; transform:scale(1); } 50% { opacity:.35; transform:scale(.8); } }
        .sc-strip { display:flex; gap:10px; overflow-x:auto; padding-bottom:6px; }
        .sc-chip { flex:none; min-width:210px; display:flex; flex-direction:column; gap:3px; padding:10px 14px; border-radius:10px; border:1px solid rgba(127,127,127,.3); background:rgba(127,127,127,.05); text-decoration:none; color:inherit; }
        .sc-chip.run { border-color:#c2410c; background:rgba(194,65,12,.08); box-shadow:0 0 0 1px #c2410c inset; }
        .sc-track { overflow-x:auto; padding:6px 2px 10px; }
        .sc-row { display:flex; min-width:max-content; align-items:flex-start; }
        .sc-step { width:218px; flex:none; display:flex; flex-direction:column; gap:8px; }
        .sc-head { display:flex; align-items:center; }
        .sc-line { flex:1; height:3px; border-radius:2px; margin:0 6px; }
        @media (prefers-reduced-motion: reduce) { .sc-pulse::after, .sc-dot { animation:none; } }
        @media print { .sc-strip { display:none; } }
    </style>
    <div wire:poll.15s style="display:flex;flex-direction:column;gap:1rem">
    <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
        <x-filament::button size="sm" color="gray" wire:click="previousDay" icon="heroicon-m-chevron-left" aria-label="Jour précédent" />
        <x-filament::button size="sm" color="gray" wire:click="today">Aujourd'hui</x-filament::button>
        <x-filament::button size="sm" color="gray" wire:click="nextDay" icon="heroicon-m-chevron-right" aria-label="Jour suivant" />
        <strong style="margin-left:.5rem">{{ ucfirst($day->translatedFormat('l d F Y')) }}</strong>
        <input type="date" wire:model.live="date" style="margin-left:auto;border:1px solid rgba(127,127,127,.35);border-radius:8px;padding:4px 8px;background:transparent;color:inherit" />
    </div>

    @if ($plans->isNotEmpty())
        <div>
            <p style="margin:0 0 6px;font-size:.75rem;opacity:.7;text-transform:uppercase;letter-spacing:.05em">Circuits du jour</p>
            <div class="sc-strip">
                @foreach ($plans as $q)
                    @php
                        $qp = $q['plan'];
                        $qRun = ! $q['termine'] && $qp->sortie_id && ($qp->sortie?->statut ?? null) === 'en_cours';
                    @endphp
                    <a href="#circuit-{{ $qp->id }}" class="sc-chip {{ $qRun ? 'run' : '' }}">
                        <span style="display:flex;align-items:center;gap:7px;font-weight:600">
                            @if ($qRun)<i class="sc-dot"></i>@elseif ($q['termine'])<x-filament::icon icon="heroicon-m-check-circle" style="width:16px;height:16px;color:#16a34a" />@else<x-filament::icon icon="heroicon-m-clock" style="width:16px;height:16px;opacity:.55" />@endif
                            {{ $qp->circuit?->nom ?? ($qp->destination ?: 'Sortie') }}
                        </span>
                        <span style="font-size:.8rem;opacity:.75">{{ $qp->vehicle?->immatriculation ?? 'Sans véhicule' }} · {{ $q['done'] }} / {{ $q['total'] }} sites</span>
                        <span style="font-size:.75rem;font-weight:600;color:{{ $qRun ? '#c2410c' : ($q['termine'] ? '#16a34a' : '#6b7280') }}">{{ $qRun ? 'En cours' : ($q['termine'] ? 'Terminé' : 'Planifié') }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @forelse ($plans as $p)
        @php
            $plan = $p['plan'];
            $running = ! $p['termine'] && $plan->sortie_id && ($plan->sortie?->statut ?? null) === 'en_cours';
            $etat = $p['termine'] ? 'terminé' : ($running ? 'en cours' : ($day->isFuture() ? 'planifié' : ($p['done'] > 0 ? 'en cours' : 'planifié')));
            $current = $running ? $p['stops']->search(fn ($x) => $x['etat'] === 'avenir') : false;   // prochain site à livrer
        @endphp
        <div id="circuit-{{ $plan->id }}" style="{{ $card }}{{ $running ? ';border-color:#c2410c;box-shadow:0 0 0 1px #c2410c inset' : '' }}">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
                <div>
                    <p style="margin:0;font-size:.75rem;opacity:.7;display:flex;align-items:center;gap:7px">@if ($running)<i class="sc-dot"></i><strong style="color:#c2410c;opacity:1">En cours</strong> ·@else Circuit {{ $etat }} ·@endif {{ $day->translatedFormat('l j F') }}</p>
                    <p style="margin:2px 0 0;font-size:1.15rem;font-weight:600">{{ $plan->circuit?->nom ?? ($plan->destination ?: 'Sortie sans circuit') }}</p>
                    <p style="margin:2px 0 0;font-size:.85rem;opacity:.7">
                        <x-filament::icon icon="heroicon-m-truck" style="width:14px;height:14px;display:inline;vertical-align:-2px" /> {{ $plan->vehicle?->immatriculation ?? 'Sans véhicule' }}
                        · <x-filament::icon icon="heroicon-m-user" style="width:14px;height:14px;display:inline;vertical-align:-2px" /> {{ $plan->driver?->nom_complet ?? 'Sans chauffeur' }}
                    </p>
                </div>
                @php $validation = $plan->validation_statut ?? 'brouillon'; @endphp
                <span style="font-size:.75rem;padding:2px 10px;border-radius:8px;background:{{ $validation === 'valide' ? 'rgba(194,65,12,.14)' : 'rgba(127,127,127,.15)' }};color:{{ $validation === 'valide' ? '#c2410c' : $neutral }}">
                    {{ \App\Models\Chronogramme::VALIDATIONS[$validation] ?? 'Brouillon' }}{{ $validation === 'valide' ? ' 🔒' : '' }}
                </span>
            </div>

            <div style="margin:14px 0 6px;display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px">
                <div style="{{ $metric }}"><div style="font-size:.75rem;opacity:.7">Sites livrés</div><div style="font-size:1.15rem;font-weight:600">{{ $p['done'] }} / {{ $p['total'] }}</div></div>
                <div style="{{ $metric }}"><div style="font-size:.75rem;opacity:.7">Dans les délais</div><div style="font-size:1.15rem;font-weight:600">{{ $p['onTime'] !== null ? $p['onTime'].' %' : '—' }}</div></div>
                <div style="{{ $metric }}"><div style="font-size:.75rem;opacity:.7">Distance</div><div style="font-size:1.15rem;font-weight:600">{{ $p['km'] ? $km($p['km']).' km' : '—' }}</div></div>
                <div style="{{ $metric }}"><div style="font-size:.75rem;opacity:.7">Retour prévu</div><div style="font-size:1.15rem;font-weight:600">{{ $p['retour_heure'] ?? '—' }}</div></div>
            </div>

            @if ($p['total'])
                <div style="display:flex;height:8px;border-radius:4px;overflow:hidden;gap:2px;margin:10px 0 6px" aria-hidden="true">
                    @foreach ($p['stops'] as $s)
                        <div style="flex:1;background:{{ $colors[$s['etat']][0] }};{{ $s['etat'] === 'avenir' ? 'opacity:.35' : '' }}"></div>
                    @endforeach
                </div>
                <div style="display:flex;gap:14px;flex-wrap:wrap;font-size:.75rem;opacity:.8;margin-bottom:14px">
                    @foreach (['livre', 'alerte', 'non_livre', 'avenir'] as $k)
                        <span style="display:inline-flex;align-items:center;gap:5px"><i style="width:10px;height:10px;border-radius:50%;background:{{ $colors[$k][0] }};display:inline-block"></i>{{ $colors[$k][2] }}</span>
                    @endforeach
                </div>

                <div class="sc-track">
                    <div class="sc-row">
                        {{-- Départ du district --}}
                        <div class="sc-step" style="width:170px">
                            <div class="sc-head">
                                <div style="{{ $node }} {{ $neutral }};color:{{ $neutral }}"><x-filament::icon icon="heroicon-m-flag" style="width:14px;height:14px" /></div>
                                <div class="sc-line" style="background:{{ $p['stops']->first() && $p['stops']->first()['etat'] !== 'avenir' ? $colors[$p['stops']->first()['etat']][0] : 'rgba(127,127,127,.4)' }}"></div>
                            </div>
                            <div style="padding-right:14px">
                                <strong>{{ $p['depart'] }}</strong>
                                <div><span style="font-size:.75rem;padding:2px 8px;border-radius:8px;background:rgba(127,127,127,.15);color:{{ $neutral }}">Départ</span></div>
                                @if ($p['depart_heure'])<div style="font-size:.8rem;opacity:.7;margin-top:3px">prévu {{ $p['depart_heure'] }}</div>@endif
                            </div>
                        </div>

                        @foreach ($p['stops'] as $s)
                            @php
                                $l = $s['livraison']; [$fg, $bg, $label, $icon] = $colors[$s['etat']];
                                $isNext = $current !== false && $loop->index === $current;
                                if ($isNext) { [$fg, $bg, $icon] = ['#c2410c', 'rgba(194,65,12,.14)', 'heroicon-m-truck']; }
                                $nextDistance = $p['stops']->get($loop->index + 1)['distance'] ?? ($loop->last ? $p['retour_km'] : null);
                                $nextEtat = $p['stops']->get($loop->index + 1)['etat'] ?? ($p['termine'] ? 'livre' : 'avenir');
                                $lineColor = $s['etat'] === 'avenir' ? 'rgba(127,127,127,.4)' : $colors[$s['etat']][0];
                            @endphp
                            <div class="sc-step" style="{{ $canEdit ? 'cursor:pointer' : '' }}" x-data="{ open: false }" x-on:click="open = ! open">
                                <div class="sc-head">
                                    <div class="{{ $isNext ? 'sc-pulse' : '' }}" style="{{ $node }} {{ $fg }};background:{{ $bg }};color:{{ $fg }}"><x-filament::icon :icon="$icon" style="width:14px;height:14px" /></div>
                                    <div class="sc-line" style="background:{{ $lineColor }}"></div>
                                </div>
                                <div style="padding-right:14px;min-width:0">
                                    <strong style="display:block;overflow-wrap:anywhere">{{ $l->espc?->nom ?? 'Site supprimé' }}</strong>
                                    <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-top:2px">
                                        <span style="font-size:.75rem;padding:2px 8px;border-radius:8px;background:{{ $bg }};color:{{ $fg }}">{{ $isNext ? 'Prochain site' : ($s['etat'] === 'alerte' ? ($l->lieu_livraison === 'transit' ? 'En transit' : 'Livrée en retard') : $colors[$s['etat']][2]) }}</span>
                                        @if ($s['heure'])<span style="font-size:.8rem;opacity:.7">prévu {{ $s['heure'] }}</span>@endif
                                    </div>
                                    @if ($l->statut === 'livre')
                                        <div style="font-size:.8rem;opacity:.8;margin-top:4px">
                                            {{ $l->date_livraison?->format('d/m/Y') }} · {{ \App\Models\LivraisonEspc::LIEUX[$l->lieu_livraison] ?? 'sur site' }}
                                            @if ($l->retard_jours > 0) · <span style="color:#d97706">+{{ $l->retard_jours }} j</span>@endif
                                        </div>
                                        @if ($l->saisiPar)<div style="font-size:.75rem;opacity:.65">saisi par {{ $l->saisiPar->name }}</div>@endif
                                        @if ($l->km_compteur !== null)
                                            <div style="font-size:.85rem;margin-top:3px">Compteur : <strong>{{ number_format($l->km_compteur, 0, ',', ' ') }} km</strong></div>
                                        @endif
                                        @if ($l->gps_ecart_m !== null && \App\Domain\Mobile\SiteGeolocation::isSuspicious($l))
                                            <div style="font-size:.8rem;margin-top:3px;color:#dc2626">⚠ Position à {{ number_format($l->gps_ecart_m / 1000, 1, ',', ' ') }} km du centre</div>
                                        @elseif ($l->lat !== null)
                                            <div style="font-size:.78rem;margin-top:3px;opacity:.7">📍 <a href="https://www.openstreetmap.org/?mlat={{ $l->lat }}&mlon={{ $l->lon }}#map=16/{{ $l->lat }}/{{ $l->lon }}" target="_blank" rel="noopener" x-on:click.stop style="text-decoration:underline">Position relevée</a>{{ $l->gps_precision_m ? ' · '.$l->gps_precision_m.' m' : '' }}</div>
                                        @endif
                                    @endif
                                    @if ($l->raison_non_livraison)
                                        <div style="font-size:.8rem;margin-top:3px;{{ $l->statut === 'non_livre' ? 'color:#dc2626' : 'opacity:.8' }}">{{ $l->statut === 'non_livre' ? 'Raison : ' : 'Note : ' }}{{ $l->raison_non_livraison }}</div>
                                    @endif
                                    @if ($canEdit)
                                        <div x-show="open" x-cloak x-on:click.stop style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;align-items:center">
                                            @if ($l->statut !== 'livre') {{ ($this->livreAction)(['id' => $l->id]) }} @endif
                                            @if ($l->statut !== 'non_livre') {{ ($this->nonLivreAction)(['id' => $l->id]) }} @endif
                                            @if ($l->statut !== 'planifie') {{ ($this->reinitAction)(['id' => $l->id]) }} @endif
                                        </div>
                                    @endif
                                    @if ($nextDistance)
                                        <div style="font-size:.75rem;opacity:.6;margin-top:6px">→ {{ $km($nextDistance) }} km jusqu'{{ $loop->last ? 'au retour' : 'à l\'étape suivante' }}</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        {{-- Retour : la livraison commence et se termine au district --}}
                        <div class="sc-step" style="width:170px">
                            <div class="sc-head">
                                <div style="{{ $node }} {{ $p['termine'] ? '#16a34a' : $neutral }};color:{{ $p['termine'] ? '#16a34a' : $neutral }}"><x-filament::icon icon="heroicon-m-home" style="width:14px;height:14px" /></div>
                            </div>
                            <div>
                                <strong>{{ $p['depart'] }}</strong>
                                <div><span style="font-size:.75rem;padding:2px 8px;border-radius:8px;background:rgba(127,127,127,.15);color:{{ $neutral }}">{{ $p['termine'] ? 'Retour · tournée terminée' : 'Retour' }}</span></div>
                                @if ($p['retour_heure'])<div style="font-size:.8rem;opacity:.7;margin-top:3px">prévu {{ $p['retour_heure'] }}</div>@endif
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;padding-top:12px;border-top:1px solid rgba(127,127,127,.28)">
                    @if ($plan->circuit)
                        <x-filament::button size="sm" color="gray" tag="a" :href="\App\Filament\Resources\Circuits\CircuitResource::getUrl('view', ['record' => $plan->circuit])" icon="heroicon-m-map">Voir sur la carte</x-filament::button>
                    @endif
                    <x-filament::button size="sm" color="gray" x-on:click="window.print()" icon="heroicon-m-printer">Imprimer la feuille de route</x-filament::button>
                </div>
            @else
                <p style="opacity:.7;margin:10px 0 0">Aucun site rattaché à cette sortie : ajoutez des ESPC au circuit pour suivre les livraisons.</p>
            @endif
        </div>
    @empty
        <div style="{{ $card }}">
            <p style="margin:0;opacity:.75">Aucune sortie planifiée ce jour. Utilisez les flèches pour changer de date, ou planifiez une sortie dans le chronogramme.</p>
        </div>
    @endforelse

    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
