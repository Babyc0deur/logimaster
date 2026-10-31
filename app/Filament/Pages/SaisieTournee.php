<?php

namespace App\Filament\Pages;

use App\Domain\Chronogramme\TourEntry;
use App\Filament\Resources\Sorties\Schemas\SortieForm;
use App\Models\Chronogramme;
use App\Models\Expense;
use App\Models\Ravitaillement;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

/**
 * Saisie d'une tournée complète, en une seule page : compteur de départ, statut et kilométrage de chaque site, compteur d'arrivée,
 * plein de carburant et autres frais. Remplace la saisie site par site lorsque le carnet de bord est recopié après la tournée.
 */
class SaisieTournee extends Page
{
    protected string $view = 'filament.pages.saisie-tournee';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $navigationLabel = "Saisie d'une tournée";

    protected static bool $shouldRegisterNavigation = false;   // accessible depuis « Sorties véhicules » et le suivi des livraisons

    protected static ?string $title = "Saisie d'une tournée";

    protected static ?string $slug = 'saisie-tournee';

    /** Sortie à saisir (passée dans l'adresse par le suivi des livraisons). */
    #[Url(as: 'plan')]
    public ?string $planId = null;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('update_chronogrammes');
    }

    public function mount(): void
    {
        $plan = $this->planId ? $this->plans()->find($this->planId) : null;
        $plan ??= $this->plans()->whereDate('date_prevue', today())->orderBy('heure_depart')->first();
        $plan ? $this->fillFrom($plan) : $this->form->fill([]);
    }

    // ------------------------------------------------------------------ données

    private function plans()
    {
        return Chronogramme::query()->where('district_id', Filament::getTenant()?->getKey())->where('statut', '!=', 'annulee')
            ->with(['circuit:id,nom', 'vehicle:id,immatriculation,type_carburant,km_actuel,prix_carburant', 'sortie']);
    }

    /** @return array<string, string> */
    private function planOptions(): array
    {
        $from = today()->subDays(60)->toDateString();
        $to = today()->addDays(7)->toDateString();

        return $this->plans()->whereBetween('date_prevue', [$from, $to])->orderByDesc('date_prevue')->orderBy('heure_depart')->get()
            ->mapWithKeys(fn (Chronogramme $p) => [$p->id => $p->date_prevue->format('d/m/Y').' · '.($p->circuit?->nom ?? ($p->destination ?: 'Sortie')).' · '.($p->vehicle?->immatriculation ?? 'sans véhicule')])
            ->all();
    }

    private function current(): ?Chronogramme
    {
        $id = $this->data['plan_id'] ?? null;

        return $id ? $this->plans()->find($id) : null;
    }

    /** Prix du litre du véhicule de la sortie choisie. */
    public function usualPrice(): ?float
    {
        return $this->current()?->vehicle?->currentFuelPrice();
    }

    /** Remplit le formulaire avec l'état enregistré de la sortie (le plein et les frais saisis sont des ajouts : champs vides). */
    private function fillFrom(Chronogramme $plan): void
    {
        $plan->loadMissing(['livraisons.espc', 'sortie', 'vehicle']);
        $sites = [];
        foreach ($plan->livraisons as $l) {
            $sites['site-'.$l->id] = [
                'id' => $l->id, 'nom' => $l->espc?->nom,
                'statut' => $l->statut === 'livre' && $l->lieu_livraison === 'transit' ? 'transit' : $l->statut,
                'km' => $l->km_compteur, 'raison' => $l->raison_non_livraison, 'date' => $l->date_livraison?->toDateString(),
            ];
        }

        $this->planId = $plan->id;
        $this->form->fill([
            'plan_id' => $plan->id,
            'km_depart' => $plan->sortie?->km_depart ?? $plan->vehicle?->km_actuel,
            'km_arrivee' => $plan->sortie?->km_arrivee,
            'sites' => $sites,
            'ravitaillement' => ['prix_unitaire' => $plan->vehicle?->currentFuelPrice()],
            'frais' => [],
        ]);
    }

    public function loadPlan(): void
    {
        $plan = $this->current();
        $plan ? $this->fillFrom($plan) : $this->form->fill(['plan_id' => null]);
    }

    /** Pleins et frais déjà enregistrés pour cette sortie (lecture seule). @return array{pleins: \Illuminate\Support\Collection, frais: \Illuminate\Support\Collection} */
    public function existing(): array
    {
        $sortieId = $this->current()?->sortie_id;

        return [
            'pleins' => $sortieId ? Ravitaillement::where('sortie_id', $sortieId)->orderBy('date_ravitaillement')->get() : collect(),
            'frais' => $sortieId ? Expense::where('sortie_id', $sortieId)->orderBy('date_depense')->get() : collect(),
        ];
    }

    // ------------------------------------------------------------------ formulaire

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Sortie')->columns(3)->schema([
                Select::make('plan_id')->label('Sortie planifiée')->options(fn () => $this->planOptions())->searchable()->required()->live()
                    ->afterStateUpdated(fn () => $this->loadPlan())
                    ->helperText('Les 60 derniers jours et la semaine à venir.'),
                TextInput::make('km_depart')->label('Compteur au départ')->numeric()->integer()->minValue(0)->suffix('km')
                    ->helperText('Prérempli avec le compteur du véhicule.'),
                TextInput::make('km_arrivee')->label("Compteur à l'arrivée")->numeric()->integer()->minValue(0)->suffix('km')
                    ->helperText("Le renseigner termine la sortie ; les sites non traités restent « à livrer »."),
            ]),
            Section::make('Sites du circuit')->description('Dans l\'ordre de passage. Le kilométrage ne peut pas reculer d\'un site à l\'autre.')->schema([
                Repeater::make('sites')->label('')->addable(false)->deletable(false)->reorderable(false)->columns(['default' => 1, 'lg' => 2, '2xl' => 6])->schema([
                    Hidden::make('id'),
                    TextInput::make('nom')->label('Site')->disabled()->dehydrated(false)->columnSpan(['default' => 1, '2xl' => 2]),
                    Select::make('statut')->label('Statut')->options(TourEntry::STATUTS)->default('planifie')->native(false)->live()->required(),
                    TextInput::make('km')->label('Compteur')->numeric()->integer()->minValue(0)->suffix('km')
                        ->visible(fn (Get $get) => $get('statut') !== 'planifie'),
                    DatePicker::make('date')->label('Date')->native(false)->displayFormat('d/m/Y')->maxDate(now())
                        ->visible(fn (Get $get) => in_array($get('statut'), ['livre', 'transit'], true)),
                    TextInput::make('raison')->label(fn (Get $get) => $get('statut') === 'non_livre' ? 'Raison (obligatoire)' : 'Note')->maxLength(500)
                        ->visible(fn (Get $get) => $get('statut') !== 'planifie'),
                ]),
            ]),
            Section::make('Plein de carburant (facultatif)')->description('À renseigner pour ajouter un plein ; laissez vide sinon. Il s\'ajoute à ceux déjà enregistrés.')->collapsible()->collapsed()->columns(3)->schema([
                Group::make()->statePath('ravitaillement')->columns(3)->columnSpanFull()->schema([
                    TextInput::make('litres')->numeric()->minValue(0.5)->suffix('L'),
                    TextInput::make('prix_unitaire')->label('Prix du litre')->numeric()->minValue(1)->suffix('FCFA/L')->live(onBlur: true)
                        ->helperText('Prérempli selon le véhicule ; modifiable, une confirmation est alors demandée.'),
                    TextInput::make('km_compteur')->label('Compteur au plein')->numeric()->integer()->minValue(0)->suffix('km'),
                    Toggle::make('prix_confirme')->dehydrated(true)
                        ->label(fn () => 'Je confirme ce prix, différent du prix du véhicule ('.number_format((float) $this->usualPrice(), 0, ',', ' ').' FCFA/L)')
                        ->visible(fn (Get $get) => ($usual = $this->usualPrice()) !== null && filled($get('prix_unitaire')) && abs((float) $get('prix_unitaire') - $usual) > 0.001)
                        ->columnSpanFull(),
                    TextInput::make('station')->maxLength(120),
                    TextInput::make('numero_facture')->label('N° facture / coupon')->maxLength(60),
                ]),
            ]),
            Section::make('Autres frais (facultatif)')->description('Péages, collation, chargement… Chaque ligne s\'ajoute aux frais déjà enregistrés.')->collapsible()->collapsed()->schema([
                Repeater::make('frais')->label('')->addActionLabel('+ Ajouter un frais')->defaultItems(0)->columns(4)->schema([
                    Select::make('type')->options(SortieForm::EXPENSE_TYPES)->default('peage')->native(false)->required(),
                    TextInput::make('montant')->numeric()->minValue(1)->suffix('FCFA'),
                    TextInput::make('beneficiaire')->label('Bénéficiaire')->maxLength(120),
                    TextInput::make('commentaire')->maxLength(255),
                ]),
            ]),
        ]);
    }

    // ------------------------------------------------------------------ enregistrement

    public function save(): void
    {
        $state = $this->form->getState();
        $plan = $this->current() ?? throw ValidationException::withMessages(['data.plan_id' => 'Choisissez une sortie.']);

        // sites indexés par identifiant de livraison ; les clés des lignes du formulaire servent à rattacher les erreurs au bon champ
        $sites = [];
        foreach ($state['sites'] ?? [] as $row) {
            $sites[$row['id']] = $row;
        }
        $siteKeys = [];
        foreach ($this->data['sites'] ?? [] as $key => $row) {
            $siteKeys[$row['id'] ?? null] = $key;
        }
        $fraisKeys = array_keys($this->data['frais'] ?? []);

        try {
            $result = app(TourEntry::class)->apply($plan, [
                'km_depart' => $state['km_depart'] ?? null, 'km_arrivee' => $state['km_arrivee'] ?? null,
                'sites' => $sites, 'ravitaillement' => $state['ravitaillement'] ?? null, 'frais' => $state['frais'] ?? [],
            ], auth()->user());
        } catch (ValidationException $e) {
            $mapped = [];
            foreach ($e->errors() as $field => $messages) {
                $path = preg_replace_callback('/^sites\.([^.]+)\./', fn ($m) => 'sites.'.($siteKeys[$m[1]] ?? $m[1]).'.', $field);
                $path = preg_replace_callback('/^frais\.(\d+)\./', fn ($m) => 'frais.'.($fraisKeys[(int) $m[1]] ?? $m[1]).'.', $path);
                $mapped['data.'.$path] = $messages;
            }
            throw ValidationException::withMessages($mapped);
        }

        $parts = [$result['sites'].' site'.($result['sites'] > 1 ? 's' : '').' renseigné'.($result['sites'] > 1 ? 's' : '')];
        $result['plein'] && $parts[] = 'plein ajouté';
        $result['frais'] && $parts[] = $result['frais'].' frais ajouté'.($result['frais'] > 1 ? 's' : '');
        $result['terminee'] && $parts[] = 'sortie terminée';
        Notification::make()->title('Tournée enregistrée')->body(implode(' · ', $parts).'.')->success()->send();

        $this->fillFrom($this->plans()->findOrFail($plan->id));   // état enregistré ; le plein et les frais saisis sont vidés
    }

    public function backToFollowUp(): string
    {
        return SuiviCircuits::getUrl().'?'.http_build_query(['date' => $this->current()?->date_prevue?->toDateString() ?? CarbonImmutable::today()->toDateString()]);
    }
}
