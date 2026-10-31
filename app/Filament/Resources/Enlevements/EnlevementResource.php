<?php

namespace App\Filament\Resources\Enlevements;

use App\Filament\Resources\Enlevements\Pages\CreateEnlevement;
use App\Filament\Resources\Enlevements\Pages\EditEnlevement;
use App\Filament\Resources\Enlevements\Pages\ListEnlevements;
use App\Models\Espc;
use App\Models\Expense;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Enlèvement au district par les ESPC : chaque centre de santé qui vient enlever ses produits reçoit une indemnité de déplacement.
 * Ce sont des « autres frais » de type « Enlèvement ESPC » : ils entrent dans le coût global (et restent visibles dans « Autres frais »).
 */
class EnlevementResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $tenantOwnershipRelationshipName = 'district';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxArrowDown;

    protected static ?string $navigationLabel = 'Enlèvement';

    protected static string|\UnitEnum|null $navigationGroup = null;   // menu principal, après la saisie d'une tournée

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'enlèvement';

    protected static ?string $pluralModelLabel = 'Enlèvements par les ESPC';

    protected static ?string $slug = 'enlevements';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', 'enlevement_espc');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('espc_id')->label('Centre de santé (ESPC)')->required()->searchable()->live()
                ->options(fn () => Espc::where('district_id', Filament::getTenant()?->getKey())->orderBy('nom')->pluck('nom', 'id'))
                ->afterStateUpdated(function (Set $set, Get $get, $state) {
                    // le bénéficiaire est, par défaut, le centre de santé qui vient enlever
                    if ($state && blank($get('beneficiaire'))) {
                        $set('beneficiaire', Espc::find($state)?->nom);
                    }
                }),
            DatePicker::make('date_depense')->label("Date de l'enlèvement")->native(false)->displayFormat('d/m/Y')->default(now())->required(),
            TextInput::make('montant')->label('Indemnité de déplacement')->numeric()->required()->minValue(1)->suffix('FCFA'),
            TextInput::make('beneficiaire')->label('Bénéficiaire')->maxLength(120)->helperText('Le centre de santé, ou la personne qui a reçu l\'indemnité.'),
            Textarea::make('commentaire')->maxLength(255)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('date_depense', 'desc')->poll('30s')
            ->columns([
                TextColumn::make('date_depense')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('espc.nom')->label('Centre de santé')->weight('bold')->searchable()->placeholder('—'),
                TextColumn::make('montant')->label('Indemnité')->money('XOF')->sortable()->summarize(Sum::make()->label('Total')->money('XOF')),
                TextColumn::make('beneficiaire')->label('Bénéficiaire')->searchable()->placeholder('—'),
                TextColumn::make('commentaire')->limit(50)->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('espc_id')->label('Centre de santé')->searchable()
                    ->options(fn () => Espc::where('district_id', Filament::getTenant()?->getKey())->orderBy('nom')->pluck('nom', 'id')),
                Filter::make('periode')->label('Période')
                    ->schema([
                        DatePicker::make('du')->label('Du')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('au')->label('Au')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['du'] ?? null, fn ($q, $v) => $q->whereDate('date_depense', '>=', $v))
                        ->when($data['au'] ?? null, fn ($q, $v) => $q->whereDate('date_depense', '<=', $v))),
            ])
            ->recordActions([EditAction::make()->label('Modifier'), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEnlevements::route('/'),
            'create' => CreateEnlevement::route('/create'),
            'edit' => EditEnlevement::route('/{record}/edit'),
        ];
    }
}
