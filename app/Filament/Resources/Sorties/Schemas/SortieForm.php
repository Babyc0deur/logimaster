<?php

namespace App\Filament\Resources\Sorties\Schemas;

use App\Models\Circuit;
use App\Models\FuelPrice;
use App\Models\Personnel;
use App\Models\SortieVehicule;
use App\Models\Vehicle;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SortieForm
{
    public const EXPENSE_TYPES = [
        'collation' => 'Collation', 'hebergement' => 'Hébergement', 'chargement' => 'Chargement',
        'dechargement' => 'Déchargement', 'peage' => 'Péage', 'autre' => 'Autre',
    ];

    /** Alerte si la consommation dépasse la théorique de plus de ce pourcentage. */
    public const SEUIL_CONSO = 20;

    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Informations générales')->columns(2)->schema([
                DatePicker::make('date_sortie')->label('Date')->native(false)->displayFormat('d/m/Y')->default(now())->required(),
                Select::make('vehicle_id')->label('Véhicule')
                    ->relationship('vehicle', 'immatriculation')->searchable()->preload()->required()->live()
                    ->afterStateUpdated(function (Set $set, $state, ?SortieVehicule $record) {
                        $vehicle = Vehicle::find($state);
                        if ($vehicle && ! $record) {
                            $set('km_depart', $vehicle->km_actuel);
                        }
                    }),
                Text::make(fn (Get $get) => ($v = Vehicle::find($get('vehicle_id')))
                    ? "Km actuel : ".number_format($v->km_actuel, 0, ',', ' ')." km · Carburant : ".(FuelPrice::TYPES[$v->type_carburant] ?? $v->type_carburant)
                        .($v->consommation_theorique ? " · Conso théorique : {$v->consommation_theorique} L/100km" : '')
                    : 'Sélectionnez un véhicule pour afficher son kilométrage.')->columnSpanFull(),
                Select::make('driver_id')->label('Chauffeur')->relationship('driver', 'nom_complet')->searchable()->preload(),
                Select::make('chef_mission_id')->label('Chef de mission')
                    ->relationship('chefMission', 'nom_complet', modifyQueryUsing: fn (Builder $query) => $query->where('fonction', 'chef_mission'))
                    ->searchable()->preload()
                    ->createOptionForm(self::personnelForm('chef_mission'))
                    ->createOptionUsing(fn (array $data) => Personnel::create($data + ['district_id' => Filament::getTenant()->getKey()])->getKey()),
                Select::make('passagers')->label('Passagers (3 max)')
                    ->relationship('passagers', 'nom_complet')->multiple()->maxItems(3)->searchable()->preload()
                    ->createOptionForm(self::personnelForm('passager'))
                    ->createOptionUsing(fn (array $data) => Personnel::create($data + ['district_id' => Filament::getTenant()->getKey()])->getKey())
                    ->columnSpanFull(),
            ]),

            Section::make('Itinéraire')->columns(2)->schema([
                Select::make('circuit_id')->label('Circuit planifié')
                    ->relationship('circuit', 'nom')->searchable()->preload()->live()
                    ->afterStateUpdated(function (Set $set, $state) {
                        $circuit = Circuit::with('espc')->find($state);
                        if ($circuit) {
                            $set('destination', $circuit->nom);
                        }
                    }),
                Text::make(function (Get $get) {
                    $circuit = Circuit::with('espc')->find($get('circuit_id'));
                    if (! $circuit) {
                        return 'Sans circuit : saisissez l\'itinéraire manuellement ci-dessous.';
                    }

                    return "ESPC desservis ({$circuit->espc->count()}) : ".$circuit->espc->pluck('nom')->join(' → ')
                        .($circuit->distance_totale ? " · Distance prévue : {$circuit->distance_totale} km" : '');
                }),
                TextInput::make('point_depart')->label('Point de départ')->maxLength(160),
                TextInput::make('km_depart')->label('Kilométrage départ')->numeric()->required()->minValue(0)->live(onBlur: true)->suffix('km'),
                Repeater::make('etapes')->label('Étapes')->addActionLabel('+ Ajouter une étape')->defaultItems(0)->columnSpanFull()
                    ->schema([
                        TextInput::make('lieu')->label('Lieu')->required(),
                        TextInput::make('km')->label('Kilométrage')->numeric()->suffix('km')
                            ->gte(fn (Get $get) => $get('../../km_depart')),
                    ])->columns(2),
                TextInput::make('point_arrivee')->label("Point d'arrivée")->maxLength(160),
                TextInput::make('km_arrivee')->label('Kilométrage arrivée')->numeric()->live(onBlur: true)->suffix('km')
                    ->gt(fn (Get $get) => $get('km_depart') ?: 0)
                    ->validationMessages(['gt' => "Le kilométrage d'arrivée doit être supérieur au kilométrage de départ."]),
                Text::make(function (Get $get) {
                    $distance = ($get('km_arrivee') !== null && $get('km_arrivee') !== '') ? (int) $get('km_arrivee') - (int) $get('km_depart') : null;
                    if ($distance === null) {
                        return 'Distance calculée : — (en attente du kilométrage d\'arrivée)';
                    }
                    $text = "Distance calculée : {$distance} km";
                    $circuit = Circuit::find($get('circuit_id'));
                    if ($circuit?->distance_totale && $distance > $circuit->distance_totale * 1.2) {
                        $text .= " ⚠ supérieure de plus de 20 % à la distance du circuit ({$circuit->distance_totale} km)";
                    }

                    return $text;
                })->columnSpanFull(),
            ]),

            Section::make('Motif et détails')->columns(2)->schema([
                Select::make('motif')->label('Motif principal')->options(SortieVehicule::MOTIFS)->default('distribution')->required(),
                Select::make('statut')->options(SortieVehicule::STATUTS)->default('en_cours')->required(),
                Radio::make('circuit_respecte')->label('Circuit respecté')->boolean(trueLabel: 'Oui', falseLabel: 'Non')->inline(),
                TextInput::make('destination')->maxLength(160),
                Textarea::make('commentaires')->columnSpanFull(),
            ]),

            Section::make('Carburant')->schema([
                Repeater::make('ravitaillements')->label('Ravitaillements effectués')->relationship('ravitaillements')
                    ->addActionLabel('+ Ajouter un ravitaillement')->defaultItems(0)->columns(3)
                    ->schema([
                        TextInput::make('litres')->numeric()->required()->minValue(0.1)->suffix('L')->live(onBlur: true),
                        TextInput::make('prix_unitaire')->label('Prix unitaire')->numeric()->required()->suffix('FCFA/L')->live(onBlur: true)
                            ->default(fn (Get $get) => FuelPrice::current(Vehicle::find($get('../../vehicle_id'))?->type_carburant)),
                        Text::make(fn (Get $get) => 'Montant : '.number_format((float) $get('litres') * (float) $get('prix_unitaire'), 0, ',', ' ').' FCFA'),
                        TextInput::make('station')->maxLength(120),
                        TextInput::make('numero_facture')->label('N° facture / coupon')->maxLength(60),
                        TextInput::make('km_compteur')->label('Km compteur')->numeric(),
                        FileUpload::make('facture_path')->label('Facture')->disk('local')->directory('factures')->visibility('private')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(10240)
                            ->downloadable()->openable()->columnSpanFull(),
                    ])
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data, Get $get) => $data + [
                        'district_id' => Filament::getTenant()->getKey(),
                        'vehicle_id' => $get('vehicle_id'),
                        'driver_id' => $get('driver_id'),
                    ]),
                Text::make(function (Get $get) {
                    $litres = collect($get('ravitaillements') ?? [])->sum(fn ($r) => (float) ($r['litres'] ?? 0));
                    $cout = collect($get('ravitaillements') ?? [])->sum(fn ($r) => (float) ($r['litres'] ?? 0) * (float) ($r['prix_unitaire'] ?? 0));
                    $text = 'Carburant : '.number_format($litres, 1, ',', ' ').' L · '.number_format($cout, 0, ',', ' ').' FCFA';

                    $distance = ($get('km_arrivee') !== null && $get('km_arrivee') !== '') ? (int) $get('km_arrivee') - (int) $get('km_depart') : 0;
                    $theo = (float) (Vehicle::find($get('vehicle_id'))?->consommation_theorique ?? 0);
                    if ($distance > 0 && $litres > 0 && $theo > 0) {
                        $reelle = $litres / $distance * 100;
                        $ecart = ($reelle - $theo) / $theo * 100;
                        $text .= sprintf(' · Consommation %.1f L/100km (%+.0f %% vs théorique)', $reelle, $ecart);
                        if ($ecart > self::SEUIL_CONSO) {
                            $text .= ' ⚠ ALERTE surconsommation';
                        }
                    }

                    return $text;
                }),
            ]),

            Section::make('Autres frais')->schema([
                Repeater::make('expenses')->label('Frais')->relationship('expenses')
                    ->addActionLabel('+ Ajouter un frais')->defaultItems(0)->columns(2)
                    ->schema([
                        Select::make('type')->options(self::EXPENSE_TYPES)->required(),
                        TextInput::make('beneficiaire')->label('Bénéficiaire')->maxLength(120),
                        TextInput::make('montant')->numeric()->required()->minValue(0)->suffix('FCFA')->live(onBlur: true),
                        TextInput::make('commentaire')->maxLength(255),
                    ])
                    ->mutateRelationshipDataBeforeCreateUsing(fn (array $data) => $data + ['district_id' => Filament::getTenant()->getKey()]),
                Text::make(function (Get $get) {
                    $autres = collect($get('expenses') ?? [])->sum(fn ($e) => (float) ($e['montant'] ?? 0));
                    $carb = collect($get('ravitaillements') ?? [])->sum(fn ($r) => (float) ($r['litres'] ?? 0) * (float) ($r['prix_unitaire'] ?? 0));

                    return 'Total autres frais : '.number_format($autres, 0, ',', ' ').' FCFA · Coût total de la sortie : '.number_format($autres + $carb, 0, ',', ' ').' FCFA';
                }),
            ]),
        ]);
    }

    /** Formulaire de création rapide d'un membre du personnel depuis la sortie. */
    private static function personnelForm(string $fonction): array
    {
        return [
            TextInput::make('nom_complet')->label('Nom complet')->required()->maxLength(120),
            TextInput::make('telephone')->maxLength(30),
            \Filament\Forms\Components\Hidden::make('fonction')->default($fonction),
        ];
    }
}
