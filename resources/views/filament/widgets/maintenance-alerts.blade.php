<x-filament-widgets::widget>
    <x-filament::section heading="⚠️ Alertes prioritaires">
        @forelse ($alerts as $alert)
            <div style="display:flex;gap:.5rem;align-items:center;padding:.3rem 0;border-bottom:1px solid #f3f4f6;font-size:.85rem">
                <x-filament::badge :color="$alert['level']->color()">{{ $alert['level']->emoji() }} {{ $alert['level']->label() }}</x-filament::badge>
                <span>{{ $alert['message'] }}</span>
            </div>
        @empty
            <p style="font-size:.85rem">🟢 Aucune alerte : vidanges, contrôles techniques, assurances et documents sont à jour.</p>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
