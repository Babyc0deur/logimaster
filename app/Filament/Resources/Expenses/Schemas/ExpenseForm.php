<?php
namespace App\Filament\Resources\Expenses\Schemas;
use Filament\Schemas\Schema;
class ExpenseForm {
    public static function configure(Schema $schema): Schema {
        return $schema->components([ 
                \Filament\Forms\Components\Select::make("sortie_id")->label("Sortie liée (Optionnel)")->relationship("sortie", "id")->searchable(),
                \Filament\Forms\Components\Select::make("type")->options(["collation" => "Collation", "chargement" => "Frais de chargement", "dechargement" => "Frais de déchargement", "hebergement" => "Hébergement", "peage" => "Péage", "carburant" => "Carburant", "maintenance" => "Maintenance", "autre" => "Autres dépenses"])->required(),
                \Filament\Forms\Components\DatePicker::make("date_depense")->label("Date")->native(false)->displayFormat("d/m/Y")->default(now())->required(),
                \Filament\Forms\Components\Select::make("vehicle_id")->label("Véhicule")->relationship("vehicle", "immatriculation")->searchable()->preload(),
                \Filament\Forms\Components\TextInput::make("montant")->numeric()->required(),
                \Filament\Forms\Components\TextInput::make("beneficiaire"),
                \Filament\Forms\Components\Textarea::make("commentaire"),
         ]);
    }
}