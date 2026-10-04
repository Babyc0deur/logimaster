<x-filament-panels::page>
    <form wire:submit="import">
        {{ $this->form }}
        <div style="margin-top:1rem">
            <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="import">Importer</span>
                <span wire:loading wire:target="import">Import en cours…</span>
            </x-filament::button>
        </div>
    </form>

    @if ($result)
        <x-filament::section :heading="($result['committed'] ? '✅ ' : '⛔ ') . $result['summary']">
            <table style="width:100%;border-collapse:collapse;font-size:.85rem">
                <thead>
                    <tr style="text-align:left;border-bottom:1px solid #e5e7eb">
                        <th style="padding:.4rem">Onglet</th><th>Créés</th><th>Mis à jour</th><th>Erreurs</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($result['sheets'] as $sheet)
                        <tr style="border-bottom:1px solid #f3f4f6">
                            <td style="padding:.4rem;font-weight:600">{{ $sheet['name'] }}</td>
                            <td>{{ $sheet['created'] }}</td>
                            <td>{{ $sheet['updated'] }}</td>
                            <td style="color:{{ $sheet['errors'] ? '#dc2626' : 'inherit' }}">{{ $sheet['errors'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($result['missing'])
                <p style="margin-top:.5rem;font-size:.8rem;color:#6b7280">Onglets absents du fichier : {{ implode(', ', $result['missing']) }}.</p>
            @endif
        </x-filament::section>

        @if ($result['errors'])
            <x-filament::section heading="Lignes en erreur" description="Numéros de ligne tels qu'affichés dans Excel.">
                <ul style="font-size:.85rem;line-height:1.6;list-style:disc;padding-left:1.2rem">
                    @foreach ($result['errors'] as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
