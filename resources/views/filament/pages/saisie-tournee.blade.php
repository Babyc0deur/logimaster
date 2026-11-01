<x-filament-panels::page>
    @php($existing = $this->existing())
    <div x-data x-on:scroll-top.window="window.scrollTo({ top: 0, behavior: 'smooth' })"></div>

    <form wire:submit="save">
        {{ $this->form }}

        <div style="margin-top:1rem;display:flex;gap:.75rem;flex-wrap:wrap">
            <x-filament::button type="submit">Enregistrer la tournée</x-filament::button>
            <x-filament::button tag="a" color="gray" :href="$this->backToFollowUp()">Voir le suivi des livraisons</x-filament::button>
            <x-filament::button tag="a" color="gray" :href="\App\Filament\Resources\Sorties\SortieVehiculeResource::getUrl()">Liste des sorties</x-filament::button>
        </div>
    </form>

    @if ($existing['pleins']->isNotEmpty() || $existing['frais']->isNotEmpty())
        <div style="margin-top:1.5rem;display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(280px,1fr))">
            @if ($existing['pleins']->isNotEmpty())
                <div class="fi-section" style="border:1px solid rgba(127,127,127,.25);border-radius:.75rem;padding:1rem">
                    <div style="font-weight:600;margin-bottom:.5rem">Pleins déjà enregistrés</div>
                    @foreach ($existing['pleins'] as $p)
                        <div style="display:flex;justify-content:space-between;gap:1rem;padding:.25rem 0;border-top:1px solid rgba(127,127,127,.15)">
                            <span>{{ $p->date_ravitaillement?->format('d/m/Y') }} · {{ rtrim(rtrim(number_format((float) $p->litres, 2, ',', ' '), '0'), ',') }} L{{ $p->station ? ' · '.$p->station : '' }}</span>
                            <strong>{{ number_format((float) $p->litres * (float) $p->prix_unitaire, 0, ',', ' ') }} FCFA</strong>
                        </div>
                    @endforeach
                </div>
            @endif
            @if ($existing['frais']->isNotEmpty())
                <div class="fi-section" style="border:1px solid rgba(127,127,127,.25);border-radius:.75rem;padding:1rem">
                    <div style="font-weight:600;margin-bottom:.5rem">Frais déjà enregistrés</div>
                    @foreach ($existing['frais'] as $f)
                        <div style="display:flex;justify-content:space-between;gap:1rem;padding:.25rem 0;border-top:1px solid rgba(127,127,127,.15)">
                            <span>{{ \App\Filament\Resources\Sorties\Schemas\SortieForm::EXPENSE_TYPES[$f->type] ?? $f->type }}{{ $f->beneficiaire ? ' · '.$f->beneficiaire : '' }}</span>
                            <strong>{{ number_format((float) $f->montant, 0, ',', ' ') }} FCFA</strong>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @php($tours = $this->tours())
    <div class="fi-section" style="margin-top:2rem;border:1px solid rgba(127,127,127,.25);border-radius:.75rem;padding:1rem">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:.75rem">
            <div style="font-weight:600">Liste des tournées <span style="font-weight:400;opacity:.65">({{ $this->toursTotal() }})</span></div>
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="Rechercher : date jj/mm/aaaa, circuit, véhicule…"
                   style="min-width:18rem;padding:.4rem .7rem;border:1px solid rgba(127,127,127,.4);border-radius:.5rem;background:transparent;color:inherit">
        </div>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:.9rem">
                <thead>
                    <tr style="text-align:left;opacity:.7">
                        <th style="padding:.4rem .5rem">Date</th><th style="padding:.4rem .5rem">Circuit</th><th style="padding:.4rem .5rem">Véhicule</th>
                        <th style="padding:.4rem .5rem">Sites livrés</th><th style="padding:.4rem .5rem">Statut</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($tours as $t)
                    <tr style="border-top:1px solid rgba(127,127,127,.18);{{ ($data['plan_id'] ?? null) === $t->id ? 'background:rgba(194,65,12,.08)' : '' }}">
                        <td style="padding:.4rem .5rem;white-space:nowrap">{{ $t->date_prevue->format('d/m/Y') }}</td>
                        <td style="padding:.4rem .5rem">{{ $t->circuit?->nom ?? ($t->destination ?: 'Sortie') }}</td>
                        <td style="padding:.4rem .5rem">{{ $t->vehicle?->immatriculation ?? '—' }}</td>
                        <td style="padding:.4rem .5rem">{{ $t->livraisons_count ? $t->livrees_count.' / '.$t->livraisons_count : '—' }}</td>
                        <td style="padding:.4rem .5rem">{{ \App\Models\Chronogramme::STATUTS[$t->statut] ?? $t->statut }}</td>
                        <td style="padding:.4rem .5rem;text-align:right">
                            <x-filament::button size="xs" color="gray" wire:click="openTour('{{ $t->id }}')">Ouvrir</x-filament::button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:1rem;text-align:center;opacity:.65">Aucune tournée trouvée.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($tours->count() < $this->toursTotal())
            <div style="margin-top:.75rem;text-align:center">
                <x-filament::button size="sm" color="gray" wire:click="moreTours">Voir plus</x-filament::button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
