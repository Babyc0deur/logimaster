<x-filament-panels::page>
    @php($existing = $this->existing())

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
</x-filament-panels::page>
