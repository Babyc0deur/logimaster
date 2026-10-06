<x-filament-panels::page>
    @if (! $external)
        <x-filament::section>
            <p style="color:#b45309;margin:0"><strong>Aucun stockage externe configuré.</strong> Les sauvegardes sont écrites sur le serveur de l'application et disparaissent avec lui.
                Renseignez BACKUP_S3_BUCKET, BACKUP_S3_ENDPOINT, BACKUP_S3_KEY et BACKUP_S3_SECRET (Cloudflare R2, Backblaze B2…) sur le serveur.</p>
        </x-filament::section>
    @endif
    @if ($error)
        <x-filament::section><p style="color:#dc2626;margin:0">Stockage des sauvegardes inaccessible : {{ $error }}</p></x-filament::section>
    @elseif ($due)
        <x-filament::section><p style="color:#dc2626;margin:0">Aucune sauvegarde depuis plus de 26 heures.</p></x-filament::section>
    @endif

    <x-filament::section heading="Sauvegardes disponibles" description="Automatique chaque jour (base + photos), vérifiée chaque dimanche. Conservation : 30 jours, puis une par mois sur 12 mois.">
        @if ($list === [])
            <p style="opacity:.7;margin:0">Aucune sauvegarde pour le moment.</p>
        @else
            <table style="width:100%;border-collapse:collapse;font-size:.9rem">
                <thead><tr style="text-align:left;opacity:.7"><th style="padding:6px">Date</th><th style="padding:6px">Fichier</th><th style="padding:6px">Taille</th><th></th></tr></thead>
                <tbody>
                    @foreach ($list as $b)
                        <tr style="border-top:1px solid rgba(127,127,127,.2)">
                            <td style="padding:6px">{{ $b['at']->setTimezone(config('app.timezone'))->translatedFormat('d M Y, H:i') }}</td>
                            <td style="padding:6px;font-family:monospace;font-size:.8rem">{{ basename($b['path']) }}</td>
                            <td style="padding:6px">{{ number_format($b['size'] / 1048576, 1, ',', ' ') }} Mo</td>
                            <td style="padding:6px;text-align:right"><x-filament::link tag="button" wire:click="download(@js($b['path']))" icon="heroicon-m-arrow-down-tray">Télécharger</x-filament::link></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-panels::page>
