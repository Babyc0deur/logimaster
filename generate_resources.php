<?php

$base = __DIR__ . '/app/Filament/Resources';

$resources = [
    'Ravitaillements' => [
        'name' => 'Ravitaillement',
        'model' => 'App\Models\Ravitaillement',
        'icon' => 'Heroicon::OutlinedBeaker',
        'label' => 'Ravitaillements',
        'singular' => 'ravitaillement',
        'form' => '
                \Filament\Forms\Components\Select::make("vehicle_id")->label("Véhicule")->relationship("vehicle", "immatriculation")->required()->searchable(),
                \Filament\Forms\Components\Select::make("driver_id")->label("Chauffeur")->relationship("driver", "nom_complet")->searchable(),
                \Filament\Forms\Components\TextInput::make("litres")->numeric()->required(),
                \Filament\Forms\Components\TextInput::make("prix_unitaire")->numeric()->required(),
                \Filament\Forms\Components\TextInput::make("station")->maxLength(120),
                \Filament\Forms\Components\TextInput::make("numero_facture")->maxLength(60),
                \Filament\Forms\Components\TextInput::make("km_compteur")->numeric(),
        ',
        'table' => '
                \Filament\Tables\Columns\TextColumn::make("created_at")->dateTime("d/m/Y")->label("Date")->sortable(),
                \Filament\Tables\Columns\TextColumn::make("vehicle.immatriculation")->label("Véhicule")->searchable(),
                \Filament\Tables\Columns\TextColumn::make("driver.nom_complet")->label("Chauffeur")->searchable(),
                \Filament\Tables\Columns\TextColumn::make("litres")->numeric(),
                \Filament\Tables\Columns\TextColumn::make("montant_total")->label("Total")->money("XOF"),
        '
    ],
    'Immobilisations' => [
        'name' => 'Immobilisation',
        'model' => 'App\Models\Immobilisation',
        'icon' => 'Heroicon::OutlinedWrench',
        'label' => 'Immobilisations',
        'singular' => 'immobilisation',
        'form' => '
                \Filament\Forms\Components\Select::make("vehicle_id")->label("Véhicule")->relationship("vehicle", "immatriculation")->required()->searchable(),
                \Filament\Forms\Components\DatePicker::make("date_debut")->required(),
                \Filament\Forms\Components\DatePicker::make("date_fin"),
                \Filament\Forms\Components\Select::make("motif")->options(["panne" => "Panne", "entretien" => "Entretien", "accident" => "Accident", "controle_technique" => "Contrôle Technique"])->required(),
                \Filament\Forms\Components\Textarea::make("description"),
                \Filament\Forms\Components\TextInput::make("montant")->numeric(),
                \Filament\Forms\Components\TextInput::make("prestataire"),
                \Filament\Forms\Components\Select::make("statut")->options(["en_cours" => "En cours", "resolu" => "Résolu"])->default("en_cours")->required(),
        ',
        'table' => '
                \Filament\Tables\Columns\TextColumn::make("vehicle.immatriculation")->label("Véhicule")->searchable(),
                \Filament\Tables\Columns\TextColumn::make("date_debut")->date("d/m/Y")->sortable(),
                \Filament\Tables\Columns\TextColumn::make("date_fin")->date("d/m/Y"),
                \Filament\Tables\Columns\TextColumn::make("motif")->badge(),
                \Filament\Tables\Columns\TextColumn::make("statut")->badge()->color(fn (string $state): string => match ($state) { "en_cours" => "warning", "resolu" => "success", default => "gray" }),
        '
    ],
    'Vidanges' => [
        'name' => 'Vidange',
        'model' => 'App\Models\Vidange',
        'icon' => 'Heroicon::OutlinedWrenchScrewdriver',
        'label' => 'Vidanges',
        'singular' => 'vidange',
        'form' => '
                \Filament\Forms\Components\Select::make("vehicle_id")->label("Véhicule")->relationship("vehicle", "immatriculation")->required()->searchable(),
                \Filament\Forms\Components\DatePicker::make("date")->required(),
                \Filament\Forms\Components\TextInput::make("km")->numeric()->required(),
                \Filament\Forms\Components\TextInput::make("type")->label("Type d\'intervention"),
                \Filament\Forms\Components\TextInput::make("montant")->numeric(),
                \Filament\Forms\Components\TextInput::make("prestataire"),
                \Filament\Forms\Components\TextInput::make("numero_facture"),
                \Filament\Forms\Components\TextInput::make("prochain_km")->label("Prochaine vidange (Km)")->numeric(),
        ',
        'table' => '
                \Filament\Tables\Columns\TextColumn::make("vehicle.immatriculation")->label("Véhicule")->searchable(),
                \Filament\Tables\Columns\TextColumn::make("date")->date("d/m/Y")->sortable(),
                \Filament\Tables\Columns\TextColumn::make("km")->numeric(),
                \Filament\Tables\Columns\TextColumn::make("prochain_km")->label("Prochaine à (Km)")->numeric(),
                \Filament\Tables\Columns\TextColumn::make("montant")->money("XOF"),
        '
    ],
    'Circuits' => [
        'name' => 'Circuit',
        'model' => 'App\Models\Circuit',
        'icon' => 'Heroicon::OutlinedMap',
        'label' => 'Circuits',
        'singular' => 'circuit',
        'form' => '
                \Filament\Forms\Components\TextInput::make("nom")->required(),
                \Filament\Forms\Components\TextInput::make("distance_totale")->label("Distance (Km)")->numeric(),
                \Filament\Forms\Components\TextInput::make("temps_estime_min")->label("Temps estimé (min)")->numeric(),
                \Filament\Forms\Components\Select::make("frequence")->options(["quotidien" => "Quotidien", "hebdomadaire" => "Hebdomadaire", "mensuel" => "Mensuel"]),
                \Filament\Forms\Components\Select::make("statut")->options(["actif" => "Actif", "inactif" => "Inactif"])->default("actif"),
        ',
        'table' => '
                \Filament\Tables\Columns\TextColumn::make("nom")->searchable(),
                \Filament\Tables\Columns\TextColumn::make("distance_totale")->label("Km")->numeric(),
                \Filament\Tables\Columns\TextColumn::make("temps_estime_min")->label("Temps (min)")->numeric(),
                \Filament\Tables\Columns\TextColumn::make("statut")->badge()->color(fn (string $state): string => match ($state) { "actif" => "success", "inactif" => "gray", default => "gray" }),
        '
    ],
    'Espcs' => [
        'name' => 'Espc',
        'model' => 'App\Models\Espc',
        'icon' => 'Heroicon::OutlinedBuildingOffice2',
        'label' => 'Centres de santé (ESPC)',
        'singular' => 'espc',
        'form' => '
                \Filament\Forms\Components\TextInput::make("nom")->required(),
                \Filament\Forms\Components\Select::make("type")->options(["centre_sante" => "Centre de Santé", "hopital" => "Hôpital", "dispensaire" => "Dispensaire", "chu" => "CHU"]),
                \Filament\Forms\Components\TextInput::make("responsable"),
                \Filament\Forms\Components\TextInput::make("telephone"),
                \Filament\Forms\Components\Select::make("statut")->options(["actif" => "Actif", "inactif" => "Inactif"])->default("actif"),
        ',
        'table' => '
                \Filament\Tables\Columns\TextColumn::make("nom")->searchable(),
                \Filament\Tables\Columns\TextColumn::make("type")->badge(),
                \Filament\Tables\Columns\TextColumn::make("responsable"),
                \Filament\Tables\Columns\TextColumn::make("telephone"),
                \Filament\Tables\Columns\TextColumn::make("statut")->badge()->color(fn (string $state): string => match ($state) { "actif" => "success", "inactif" => "gray", default => "gray" }),
        '
    ],
    'Expenses' => [
        'name' => 'Expense',
        'model' => 'App\Models\Expense',
        'icon' => 'Heroicon::OutlinedBanknotes',
        'label' => 'Dépenses',
        'singular' => 'dépense',
        'form' => '
                \Filament\Forms\Components\Select::make("sortie_id")->label("Sortie liée (Optionnel)")->relationship("sortie", "id")->searchable(),
                \Filament\Forms\Components\Select::make("type")->options(["carburant" => "Carburant", "maintenance" => "Maintenance", "peage" => "Péage", "autre" => "Autre"])->required(),
                \Filament\Forms\Components\TextInput::make("montant")->numeric()->required(),
                \Filament\Forms\Components\TextInput::make("beneficiaire"),
                \Filament\Forms\Components\Textarea::make("commentaire"),
        ',
        'table' => '
                \Filament\Tables\Columns\TextColumn::make("created_at")->dateTime("d/m/Y")->label("Date")->sortable(),
                \Filament\Tables\Columns\TextColumn::make("type")->badge(),
                \Filament\Tables\Columns\TextColumn::make("montant")->money("XOF")->sortable(),
                \Filament\Tables\Columns\TextColumn::make("beneficiaire")->searchable(),
        '
    ]
];

foreach ($resources as $dir => $def) {
    $rDir = "$base/$dir";
    @mkdir("$rDir/Pages", 0777, true);
    @mkdir("$rDir/Schemas", 0777, true);
    @mkdir("$rDir/Tables", 0777, true);

    $name = $def['name'];
    $model = $def['model'];
    $icon = $def['icon'];
    $label = $def['label'];
    $singular = $def['singular'];
    
    // Resource Class
    $resourceTpl = "<?php
namespace App\Filament\Resources\\$dir;
use App\Filament\Resources\\$dir\Pages\Create{$name};
use App\Filament\Resources\\$dir\Pages\Edit{$name};
use App\Filament\Resources\\$dir\Pages\List{$dir};
use App\Filament\Resources\\$dir\Schemas\\{$name}Form;
use App\Filament\Resources\\$dir\Tables\\{$dir}Table;
use $model;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class {$name}Resource extends Resource
{
    protected static ?string \$model = {$name}::class;
    protected static ?string \$tenantOwnershipRelationshipName = 'district';
    protected static string|BackedEnum|null \$navigationIcon = $icon;
    protected static ?string \$navigationLabel = '$label';
    protected static ?string \$modelLabel = '$singular';
    protected static ?string \$pluralModelLabel = '$label';

    public static function form(Schema \$schema): Schema
    {
        return {$name}Form::configure(\$schema);
    }

    public static function table(Table \$table): Table
    {
        return {$dir}Table::configure(\$table);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => List{$dir}::route('/'),
            'create' => Create{$name}::route('/create'),
            'edit'   => Edit{$name}::route('/{record}/edit'),
        ];
    }
}";
    file_put_contents("$rDir/{$name}Resource.php", $resourceTpl);

    // Form Class
    $formTpl = "<?php
namespace App\Filament\Resources\\$dir\Schemas;
use Filament\Schemas\Schema;
class {$name}Form {
    public static function configure(Schema \$schema): Schema {
        return \$schema->components([ {$def['form']} ]);
    }
}";
    file_put_contents("$rDir/Schemas/{$name}Form.php", $formTpl);

    // Table Class
    $tableTpl = "<?php
namespace App\Filament\Resources\\$dir\Tables;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Table;
class {$dir}Table {
    public static function configure(Table \$table): Table {
        return \$table->columns([ {$def['table']} ])
            ->recordActions([ EditAction::make() ])
            ->toolbarActions([ BulkActionGroup::make([ DeleteBulkAction::make() ]) ]);
    }
}";
    file_put_contents("$rDir/Tables/{$dir}Table.php", $tableTpl);

    // Pages
    $listTpl = "<?php namespace App\Filament\Resources\\$dir\Pages; use App\Filament\Resources\\$dir\\{$name}Resource; use Filament\Actions\CreateAction; use Filament\Resources\Pages\ListRecords; class List{$dir} extends ListRecords { protected static string \$resource = {$name}Resource::class; protected function getHeaderActions(): array { return [ CreateAction::make() ]; } }";
    file_put_contents("$rDir/Pages/List{$dir}.php", $listTpl);

    $createTpl = "<?php namespace App\Filament\Resources\\$dir\Pages; use App\Filament\Resources\\$dir\\{$name}Resource; use Filament\Resources\Pages\CreateRecord; class Create{$name} extends CreateRecord { protected static string \$resource = {$name}Resource::class; }";
    file_put_contents("$rDir/Pages/Create{$name}.php", $createTpl);

    $editTpl = "<?php namespace App\Filament\Resources\\$dir\Pages; use App\Filament\Resources\\$dir\\{$name}Resource; use Filament\Actions\DeleteAction; use Filament\Resources\Pages\EditRecord; class Edit{$name} extends EditRecord { protected static string \$resource = {$name}Resource::class; protected function getHeaderActions(): array { return [ DeleteAction::make() ]; } }";
    file_put_contents("$rDir/Pages/Edit{$name}.php", $editTpl);
}

echo "Toutes les ressources ont ete generees !";
