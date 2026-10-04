<?php

namespace App\Filament\Resources\Vidanges\Schemas;

use App\Domain\Fleet\MaintenancePlanner;
use App\Models\Setting;
use App\Models\Vehicle;
use App\Models\Vidange;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class VidangeForm
{
    public const TYPES = ['simple' => 'Vidange simple', 'complete' => 'Vidange complète', 'revision' => 'Révision'];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('vehicle_id')->label('Véhicule')->relationship('vehicle', 'immatriculation')
                ->searchable()->preload()->required()->live()
                ->afterStateUpdated(function (Set $set, $state) {
                    $v = Vehicle::find($state);
                    $set('km', $v?->km_actuel);
                    self::suggestNext($set, $v, $v?->km_actuel);
                }),
            DatePicker::make('date')->label('Date')->native(false)->displayFormat('d/m/Y')->default(now())->required(),
            TextInput::make('km')->label('Kilométrage')->numeric()->required()->minValue(0)->suffix('km')->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set, Get $get, $state) => self::suggestNext($set, Vehicle::find($get('vehicle_id')), (int) $state)),
            Text::make(function (Get $get) {
                $vehicle = Vehicle::find($get('vehicle_id'));
                if (! $vehicle) {
                    return 'Sélectionnez un véhicule pour voir la dernière vidange.';
                }
                $last = Vidange::where('vehicle_id', $vehicle->id)->orderByDesc('km')->first();
                if (! $last) {
                    return 'Aucune vidange enregistrée pour ce véhicule.';
                }
                $since = ($get('km') ?: $vehicle->km_actuel) - $last->km;

                return 'Dernier : '.number_format($last->km, 0, ',', ' ').' km ('.$last->date->format('d/m/Y').') · Depuis la dernière : '.number_format(max(0, $since), 0, ',', ' ').' km';
            })->columnSpanFull(),
            Select::make('type')->label('Type')->options(self::TYPES)->default('simple')->required(),
            TextInput::make('montant')->numeric()->minValue(0)->suffix('FCFA'),
            TextInput::make('prestataire')->maxLength(120),
            TextInput::make('numero_facture')->label('N° facture')->maxLength(60),
            FileUpload::make('facture_path')->label('Facture')->disk('local')->directory('factures')->visibility('private')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(10240)->downloadable()->openable()->columnSpanFull(),
            TextInput::make('prochain_km')->label('Prochaine vidange (km)')->numeric()->suffix('km')->live(onBlur: true)
                ->gt(fn (Get $get) => $get('km') ?: 0)
                ->helperText('Proposé automatiquement : kilométrage + intervalle par défaut (Seuils d\'alerte).')
                ->afterStateUpdated(fn (Set $set, Get $get, $state) => self::suggestDate($set, Vehicle::find($get('vehicle_id')), (int) $state)),
            DatePicker::make('prochaine_date')->label('Date estimée')->native(false)->displayFormat('d/m/Y')
                ->helperText('Estimée d\'après le rythme moyen du véhicule sur 90 jours ; modifiable.'),
            Textarea::make('observations')->columnSpanFull(),
        ])->columns(2);
    }

    private static function suggestNext(Set $set, ?Vehicle $vehicle, ?int $km): void
    {
        if (! $vehicle || $km === null) {
            return;
        }
        $next = $km + (int) Setting::get('intervalle_vidange_km');
        $set('prochain_km', $next);
        self::suggestDate($set, $vehicle, $next, $km);
    }

    private static function suggestDate(Set $set, ?Vehicle $vehicle, int $targetKm, ?int $fromKm = null): void
    {
        $date = $vehicle ? app(MaintenancePlanner::class)->estimateDateForKm($vehicle, $targetKm, $fromKm) : null;
        $set('prochaine_date', $date?->toDateString());
    }
}
