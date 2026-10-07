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
    <div wire:poll.15s style="display:flex;flex-direction:column;gap:1rem">
    <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
        <x-filament::button size="sm" color="gray" wire:click="previousDay" icon="heroicon-m-chevron-left" aria-label="Jour précédent" />
        <x-filament::button size="sm" color="gray" wire:click="today">Aujourd'hui</x-filament::button>
        <x-filament::button size="sm" color="gray" wire:click="nextDay" icon="heroicon-m-chevron-right" aria-label="Jour suivant" />
        <strong style="margin-left:.5rem">{{ ucfirst($day->translatedFormat('l d F Y')) }}</strong>
        <input type="date" wire:model.live="date" style="margin-left:auto;border:1px solid rgba(127,127,127,.35);border-radius:8px;padding:4px 8px;background:transparent;color:inherit" />
    </div>

    @forelse ($plans as $p)
        @php
            $plan = $p['plan'];
            $etat = $p['termine'] ? 'terminé' : ($day->isToday() && $p['done'] > 0 ? 'en cours' : ($day->isFuture() ? 'planifié' : ($p['done'] > 0 ? 'en cours' : 'planifié')));
        @endphp
        <div style="{{ $card }}">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
                <div>
                    <p style="margin:0;font-size:.75rem;opacity:.7">Circuit {{ $etat }} · {{ $day->translatedFormat('l j F') }}</p>
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

                <div>
                    {{-- Départ du district --}}
                    <div style="display:flex;gap:14px">
                        <div style="display:flex;flex-direction:column;align-items:center;width:28px;flex:none">
                            <div style="{{ $node }} {{ $neutral }};color:{{ $neutral }}"><x-filament::icon icon="heroicon-m-flag" style="width:14px;height:14px" /></div>
                            <div style="flex:1;width:3px;min-height:28px;background:rgba(127,127,127,.4);margin:2px 0"></div>
                        </div>
                        <div style="flex:1;padding-bottom:14px">
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                <strong>{{ $p['depart'] }}</strong>
                                <span style="font-size:.75rem;padding:2px 8px;border-radius:8px;background:rgba(127,127,127,.15);color:{{ $neutral }}">Départ</span>
                                @if ($p['depart_heure'])<span style="font-size:.8rem;opacity:.7;margin-left:auto">prévu {{ $p['depart_heure'] }}</span>@endif
                            </div>
                        </div>
                    </div>

                    @foreach ($p['stops'] as $s)
                        @php $l = $s['livraison']; [$fg, $bg, $label, $icon] = $colors[$s['etat']]; $nextDistance = $p['stops']->get($loop->index + 1)['distance'] ?? ($loop->last ? $p['retour_km'] : null); @endphp
                        <div style="display:flex;gap:14px;{{ $canEdit ? 'cursor:pointer' : '' }}" x-data="{ open: false }" x-on:click="open = ! open">
                            <div style="display:flex;flex-direction:column;align-items:center;width:28px;flex:none">
                                <div style="{{ $node }} {{ $fg }};background:{{ $bg }};color:{{ $fg }}"><x-filament::icon :icon="$icon" style="width:14px;height:14px" /></div>
                                <div style="flex:1;width:3px;min-height:28px;background:{{ $s['etat'] === 'avenir' ? 'rgba(127,127,127,.4)' : $fg }};margin:2px 0"></div>
                            </div>
                            <div style="flex:1;min-width:0;padding-bottom:14px">
                                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                    <strong>{{ $l->espc?->nom ?? 'Site supprimé' }}</strong>
                                    <span style="font-size:.75rem;padding:2px 8px;border-radius:8px;background:{{ $bg }};color:{{ $fg }}">{{ $s['etat'] === 'alerte' ? ($l->lieu_livraison === 'transit' ? 'En transit' : 'Livrée en retard') : $colors[$s['etat']][2] }}</span>
                                    @if ($s['heure'])<span style="font-size:.8rem;opacity:.7;margin-left:auto">prévu {{ $s['heure'] }}</span>@endif
                                </div>
                                @if ($l->statut === 'livre')
                                    <div style="font-size:.85rem;opacity:.8;margin-top:2px">
                                        Livrée le {{ $l->date_livraison?->format('d/m/Y') }} · {{ \App\Models\LivraisonEspc::LIEUX[$l->lieu_livraison] ?? 'sur site' }}
                                        @if ($l->saisiPar) · <span style="opacity:.8">saisi par {{ $l->saisiPar->name }} depuis le téléphone</span> @endif
                                        @if ($l->retard_jours > 0) · <span style="color:#d97706">+{{ $l->retard_jours }} j de retard</span>@endif
                                    </div>
                                    @if ($l->receptionnaire || $l->colis !== null || $l->preuve_photo)
                                        <div style="font-size:.85rem;margin-top:2px;display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                                            @if ($l->receptionnaire)<span>Reçu par <strong>{{ $l->receptionnaire }}</strong></span>@endif
                                            @if ($l->colis !== null)<span style="opacity:.8">· {{ $l->colis }} colis</span>@endif
                                            @if (\App\Support\PrivatePhoto::exists($l->preuve_photo)) <span>·</span> {{ ($this->preuveAction)(['id' => $l->id]) }} @endif
                                        </div>
                                    @endif
                                    @if ($l->gps_ecart_m !== null && \App\Domain\Mobile\SiteGeolocation::isSuspicious($l))
                                        <div style="font-size:.85rem;margin-top:2px;color:#dc2626">⚠ Position du téléphone à {{ number_format($l->gps_ecart_m / 1000, 1, ',', ' ') }} km du centre au moment de la livraison</div>
                                    @elseif ($l->lat !== null)
                                        <div style="font-size:.8rem;margin-top:2px;opacity:.65">📍 Position relevée{{ $l->gps_precision_m ? ' (précision '.$l->gps_precision_m.' m)' : '' }}{{ $l->gps_ecart_m !== null ? ' · '.($l->gps_ecart_m < 1000 ? $l->gps_ecart_m.' m' : number_format($l->gps_ecart_m / 1000, 1, ',', ' ').' km').' du centre' : '' }}</div>
                                    @endif
                                @endif
                                @if ($l->raison_non_livraison)
                                    <div style="font-size:.85rem;margin-top:2px;{{ $l->statut === 'non_livre' ? 'color:#dc2626' : 'opacity:.8' }}">{{ $l->statut === 'non_livre' ? 'Raison : ' : 'Note : ' }}{{ $l->raison_non_livraison }}</div>
                                @endif
                                @if ($canEdit)
                                    <div x-show="open" x-cloak x-on:click.stop style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;align-items:center">
                                        @if ($l->statut !== 'livre') {{ ($this->livreAction)(['id' => $l->id]) }} @endif
                                        @if ($l->statut !== 'non_livre') {{ ($this->nonLivreAction)(['id' => $l->id]) }} @endif
                                        @if ($l->statut !== 'planifie') {{ ($this->reinitAction)(['id' => $l->id]) }} @endif
                                    </div>
                                @endif
                                @if ($nextDistance)
                                    <div style="font-size:.75rem;opacity:.6;margin-top:8px">
                                        <x-filament::icon icon="heroicon-m-arrow-down" style="width:12px;height:12px;display:inline;vertical-align:-1px" />
                                        {{ $km($nextDistance) }} km jusqu'{{ $loop->last ? 'au retour' : 'à l\'étape suivante' }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    {{-- Retour : la livraison commence et se termine au district --}}
                    <div style="display:flex;gap:14px">
                        <div style="display:flex;flex-direction:column;align-items:center;width:28px;flex:none">
                            <div style="{{ $node }} {{ $p['termine'] ? '#16a34a' : $neutral }};color:{{ $p['termine'] ? '#16a34a' : $neutral }}"><x-filament::icon icon="heroicon-m-home" style="width:14px;height:14px" /></div>
                        </div>
                        <div style="flex:1">
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                <strong>{{ $p['depart'] }}</strong>
                                <span style="font-size:.75rem;padding:2px 8px;border-radius:8px;background:rgba(127,127,127,.15);color:{{ $neutral }}">{{ $p['termine'] ? 'Retour · tournée terminée' : 'Retour' }}</span>
                                @if ($p['retour_heure'])<span style="font-size:.8rem;opacity:.7;margin-left:auto">prévu {{ $p['retour_heure'] }}</span>@endif
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
