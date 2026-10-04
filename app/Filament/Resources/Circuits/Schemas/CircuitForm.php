<?php

namespace App\Filament\Resources\Circuits\Schemas;

use App\Models\Espc;
use App\Support\Geo;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CircuitForm
{
    public const FREQUENCES = ['quotidien' => 'Quotidien', 'hebdomadaire' => 'Hebdomadaire', 'bimensuel' => 'Bimensuel', 'mensuel' => 'Mensuel', 'trimestriel' => 'Trimestriel'];

    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Circuit')->columns(2)->schema([
                TextInput::make('nom')->label('Nom du circuit')->required()->maxLength(120),
                Select::make('statut')->options(['actif' => 'Actif', 'inactif' => 'Inactif'])->default('actif')->required(),
                Select::make('frequence')->label('Fréquence')->options(self::FREQUENCES),
                TextInput::make('temps_estime_min')->label('Temps estimé')->numeric()->minValue(0)->suffix('minutes'),
                TextInput::make('distance_totale')->label('Distance totale')->numeric()->minValue(0)->suffix('km')
                    ->helperText('Laissez vide pour la calculer à partir des distances des étapes.'),
            ]),
            Section::make('Point de départ')->columns(3)->schema([
                TextInput::make('point_depart')->label('Lieu de départ')->maxLength(160)->placeholder('DS Bocanda'),
                TextInput::make('depart_lat')->label('Latitude')->numeric()->minValue(-90)->maxValue(90),
                TextInput::make('depart_lon')->label('Longitude')->numeric()->minValue(-180)->maxValue(180),
            ]),
            Section::make('Étapes (sites desservis, dans l\'ordre de passage)')->schema([
                Repeater::make('etapes')->label('')->reorderable()->addActionLabel('+ Ajouter une étape')->defaultItems(0)->columns(3)
                    ->itemLabel(fn (array $state, ?Repeater $component = null) => ($state['espc_id'] ?? null) ? (Espc::find($state['espc_id'])?->nom ?? 'Étape') : 'Nouvelle étape')
                    ->schema([
                        Select::make('espc_id')->label('Site (ESPC)')->required()->searchable()->distinct()
                            ->options(fn () => Espc::where('district_id', Filament::getTenant()?->getKey())->orderBy('nom')->pluck('nom', 'id'))
                            ->createOptionForm([
                                TextInput::make('nom')->required()->maxLength(160),
                                TextInput::make('type')->maxLength(40)->placeholder('CSR, CSU, maternité…'),
                                TextInput::make('gps_lat')->label('Latitude')->numeric(),
                                TextInput::make('gps_lon')->label('Longitude')->numeric(),
                            ])
                            ->createOptionUsing(fn (array $data) => Espc::create($data + ['district_id' => Filament::getTenant()->getKey()])->getKey())
                            ->columnSpan(2),
                        TextInput::make('distance_km')->label('Distance depuis l\'étape précédente')->numeric()->minValue(0)->suffix('km'),
                    ]),
                Text::make(function (Get $get) {
                    $legs = collect($get('etapes') ?? [])->pluck('distance_km')->filter(fn ($d) => $d !== null && $d !== '');
                    $n = count($get('etapes') ?? []);

                    return "{$n} étape(s) · distance cumulée des étapes : ".($legs->isEmpty() ? '—' : number_format((float) $legs->sum(), 1, ',', ' ').' km');
                }),
            ]),
        ]);
    }
}
