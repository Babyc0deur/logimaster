@props([
    'teleport' => false,
])

@php
    use Filament\Actions\Action;
    use Filament\Models\Contracts\HasCurrentTenantLabel;
    use Filament\Support\Facades\FilamentView;
    use Filament\Support\Icons\Heroicon;
    use Filament\Support\View\ComponentAttributeBag;
    use Filament\View\PanelsIconAlias;
    use Filament\View\PanelsRenderHook;
    use Illuminate\Support\Arr;

    $currentTenant = filament()->getTenant();
    $currentTenantName = filament()->getTenantName($currentTenant);

    $items = $this->getTenantMenuItems();

    $tenants = $this->getSwitchableTenants();
    $canSwitchTenants = filled($tenants);

    $isSearchable = $canSwitchTenants && (filament()->isTenantMenuSearchable() ?? (count($tenants) >= 10));

    $itemsBeforeAndAfterTenantSwitcher = collect($items)
        ->groupBy(fn (Action $item): bool => $canSwitchTenants && ($item->getSort() < 0), preserveKeys: true)
        ->all();
    $itemsBeforeTenantSwitcher = $itemsBeforeAndAfterTenantSwitcher[true] ?? collect();
    $itemsAfterTenantSwitcher = $itemsBeforeAndAfterTenantSwitcher[false] ?? collect();

    $multiGroupAfterSwitcher = $this->hasMultipleTenantMenuItemGroups();
    $afterSwitcherItemGroups = $multiGroupAfterSwitcher ? $this->getTenantMenuItemGroupsAfterSwitcher() : [];

    $isSidebarCollapsibleOnDesktop = filament()->isSidebarCollapsibleOnDesktop();
@endphp

{{ FilamentView::renderHook(PanelsRenderHook::TENANT_MENU_BEFORE) }}

<x-filament::dropdown
    placement="bottom-start"
    size
    :teleport="$teleport"
    :attributes="
        \Filament\Support\prepare_inherited_attributes($attributes)
            ->class(['fi-tenant-menu'])
    "
>
    <x-slot name="trigger">
        <button
            @if ($isSidebarCollapsibleOnDesktop)
                x-data="{ tooltip: false }"
                x-effect="
                    tooltip = $store.sidebar.isOpen
                        ? false
                        : {
                              content: @js($currentTenantName),
                              placement: document.dir === 'rtl' ? 'left' : 'right',
                              theme: $store.theme,
                          }
                "
                x-tooltip.html="tooltip"
            @endif
            type="button"
            class="fi-tenant-menu-trigger"
        >
            <x-filament-panels::avatar.tenant
                :tenant="$currentTenant"
                loading="lazy"
            />

            <span
                @if ($isSidebarCollapsibleOnDesktop)
                    x-show="$store.sidebar.isOpen"
                @endif
                class="fi-tenant-menu-trigger-text"
            >
                @if ($currentTenant instanceof HasCurrentTenantLabel)
                    <span class="fi-tenant-menu-trigger-current-tenant-label">
                        {{ $currentTenant->getCurrentTenantLabel() }}
                    </span>
                @endif

                <span class="fi-tenant-menu-trigger-tenant-name">
                    {{ $currentTenantName }}
                </span>
            </span>

            {{
                \Filament\Support\generate_icon_html(Heroicon::ChevronDown, alias: PanelsIconAlias::TENANT_MENU_TOGGLE_BUTTON, attributes: new ComponentAttributeBag([
                    'x-show' => $isSidebarCollapsibleOnDesktop ? '$store.sidebar.isOpen' : null,
                ]))
            }}
        </button>
    </x-slot>

    @if ($itemsBeforeTenantSwitcher->isNotEmpty())
        <x-filament::dropdown.list>
            @foreach ($itemsBeforeTenantSwitcher as $item)
                {{ $item }}
            @endforeach
        </x-filament::dropdown.list>
    @endif

    @if ($canSwitchTenants)
        {{-- Recherche à la frappe : la liste n'est pas chargée, les districts sont demandés au serveur pendant la saisie --}}
        <div
            x-data="{
                q: '',
                results: [],
                busy: false,
                timer: null,
                seq: 0,
                search() {
                    clearTimeout(this.timer)
                    if (this.q.trim().length < 1) { this.results = []; this.busy = false; return }
                    this.busy = true
                    this.timer = setTimeout(async () => {
                        const n = ++this.seq
                        try {
                            const r = await fetch(@js(route('districts.search')) + '?q=' + encodeURIComponent(this.q.trim()), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                            const d = r.ok ? await r.json() : []
                            if (n === this.seq) this.results = d
                        } catch (e) { if (n === this.seq) this.results = [] }
                        if (n === this.seq) this.busy = false
                    }, 200)
                },
            }"
        >
            <x-filament::dropdown.list>
                <div x-id="['input']">
                    <label x-bind:for="$id('input')" class="fi-sr-only">Chercher un district</label>
                    <x-filament::input
                        x-bind:id="$id('input')"
                        x-model="q"
                        x-on:input="search()"
                        placeholder="Tapez le nom d'un district…"
                        type="search"
                        autocomplete="off"
                    />
                </div>

                <p x-show="q.trim() === ''" style="padding: .5rem .75rem; opacity: .65; font-size: .85rem; white-space: normal; line-height: 1.35; margin: 0">
                    Commencez à taper pour afficher les districts.
                </p>
                <p x-show="busy" x-cloak style="padding: .5rem .75rem; opacity: .65; font-size: .85rem; white-space: normal; line-height: 1.35; margin: 0">Recherche…</p>
                <p x-show="q.trim() !== '' && ! busy && results.length === 0" x-cloak style="padding: .5rem .75rem; opacity: .65; font-size: .85rem; white-space: normal; line-height: 1.35; margin: 0">Aucun district trouvé.</p>

                <template x-for="d in results" :key="d.id">
                    <a x-bind:href="d.url" class="fi-dropdown-list-item">
                        <span class="fi-dropdown-list-item-label" x-text="d.name"></span>
                        <small x-show="d.region" x-text="d.region" style="margin-left: auto; opacity: .6; font-size: .75rem"></small>
                    </a>
                </template>
            </x-filament::dropdown.list>
        </div>
    @endif

    @if ($multiGroupAfterSwitcher && $afterSwitcherItemGroups !== [])
        @foreach ($afterSwitcherItemGroups as $afterSwitcherGroup)
            <x-filament::dropdown.list>
                @foreach ($afterSwitcherGroup as $item)
                    {{ $item }}
                @endforeach
            </x-filament::dropdown.list>
        @endforeach
    @elseif ($itemsAfterTenantSwitcher->isNotEmpty())
        <x-filament::dropdown.list>
            @foreach ($itemsAfterTenantSwitcher as $item)
                {{ $item }}
            @endforeach
        </x-filament::dropdown.list>
    @endif
</x-filament::dropdown>

{{ FilamentView::renderHook(PanelsRenderHook::TENANT_MENU_AFTER) }}
