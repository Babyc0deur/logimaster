<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Seuils d'alerte (carburant, maintenance, immobilisation) — Paramètres métier. */
class AlertSettings extends Page
{
    protected string $view = 'filament.pages.alert-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?string $navigationLabel = "Seuils d'alerte";

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $title = "Seuils d'alerte";

    protected static ?string $slug = 'seuils-alerte';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('manage_settings');
    }

    public function mount(): void
    {
        $this->form->fill(collect(Setting::DEFAULTS)->map(fn ($default, $key) => Setting::get($key))->all());
    }

    public function form(Schema $schema): Schema
    {
        $n = fn (string $name, string $label, string $suffix) => TextInput::make($name)->label($label)->numeric()->required()->minValue(0)->suffix($suffix);

        return $schema->statePath('data')->components([
            Section::make('Carburant')->columns(2)->schema([
                $n('seuil_surconsommation', 'Surconsommation par véhicule', '% d\'écart'),
                $n('seuil_surconsommation_sortie', 'Surconsommation sur une sortie', '% d\'écart'),
            ]),
            Section::make('Maintenance')->columns(2)->schema([
                $n('intervalle_vidange_km', 'Intervalle de vidange par défaut', 'km'),
                $n('seuil_vidange_urgent_km', '🔴 Vidange urgente en dessous de', 'km restants'),
                $n('seuil_vidange_attention_km', '🟠 Vidange à prévoir en dessous de', 'km restants'),
                $n('seuil_echeance_urgent_jours', '🔴 Échéance (CT, assurance, documents) urgente', 'jours'),
                $n('seuil_echeance_attention_jours', '🟠 Échéance à surveiller', 'jours'),
                $n('immobilisation_alerte_jours', 'Alerte véhicule immobilisé depuis plus de', 'jours'),
            ]),
        ]);
    }

    protected function getFormActions(): array
    {
        return [Action::make('save')->label('Enregistrer')->submit('save')];
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::put($key, $value);
        }
        Notification::make()->title('Seuils enregistrés')->success()->send();
    }
}
