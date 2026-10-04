<?php

namespace App\Filament\Resources\Factures;

use App\Filament\Resources\Factures\Pages\CreateFacture;
use App\Filament\Resources\Factures\Pages\EditFacture;
use App\Filament\Resources\Factures\Pages\ListFactures;
use App\Filament\Resources\Factures\Pages\ViewFacture;
use App\Models\Facture;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Factures fournisseurs : saisie, catégorisation, validation, paiement, archivage (historique complet). */
class FactureResource extends Resource
{
    protected static ?string $model = Facture::class;

    protected static ?string $tenantOwnershipRelationshipName = 'district';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $navigationLabel = 'Factures';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'facture';

    protected static ?string $pluralModelLabel = 'factures';

    public static function canAccess(): bool
    {
        return \App\Support\Modules::enabled('finance') && (bool) auth()->user()?->can('view_factures');
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Facture::where('district_id', Filament::getTenant()?->getKey())->where('statut', 'a_valider')->count();

        return $n > 0 && auth()->user()?->can('validate_factures') ? (string) $n : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('fournisseur')->required()->maxLength(160),
            TextInput::make('numero')->label('N° de facture')->required()->maxLength(60)
                ->rules([fn (Get $get, ?Facture $record) => function ($attribute, $value, $fail) use ($get, $record) {
                    $dup = Facture::where('district_id', Filament::getTenant()->getKey())->where('fournisseur', $get('fournisseur'))->where('numero', $value)
                        ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))->exists();
                    $dup && $fail('Cette facture est déjà enregistrée pour ce fournisseur.');
                }]),
            DatePicker::make('date_facture')->label('Date de la facture')->native(false)->displayFormat('d/m/Y')->default(now())->required()->maxDate(now()),
            Select::make('categorie')->label('Catégorie')->options(Facture::CATEGORIES)->required(),
            TextInput::make('montant')->numeric()->required()->minValue(1)->suffix('FCFA'),
            Select::make('vehicle_id')->label('Véhicule concerné')->relationship('vehicle', 'immatriculation')->searchable()->preload()->live()
                ->afterStateUpdated(fn (Set $set, $state) => $set('bailleur', Vehicle::find($state)?->bailleur)),
            TextInput::make('bailleur')->maxLength(120)->helperText('Repris du véhicule ; permet le suivi des dépenses par bailleur.'),
            Textarea::make('description')->columnSpanFull(),
            FileUpload::make('fichier_path')->label('Facture (PDF ou image)')->disk('local')->directory('factures')->visibility('private')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(10240)->downloadable()->openable()->previewable()->columnSpanFull(),
        ])->columns(2);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Facture')->columns(4)->schema([
                TextEntry::make('numero')->label('N°')->weight('bold'),
                TextEntry::make('fournisseur'),
                TextEntry::make('date_facture')->label('Date')->date('d/m/Y'),
                TextEntry::make('statut')->badge()->formatStateUsing(fn ($state) => Facture::STATUTS[$state] ?? $state)->color(fn ($state) => self::color($state)),
                TextEntry::make('categorie')->formatStateUsing(fn ($state) => Facture::CATEGORIES[$state] ?? $state),
                TextEntry::make('montant')->money('XOF'),
                TextEntry::make('vehicle.immatriculation')->label('Véhicule')->placeholder('—'),
                TextEntry::make('bailleur')->placeholder('—'),
                TextEntry::make('description')->columnSpanFull()->placeholder('—'),
                TextEntry::make('motif_rejet')->label('Motif du rejet')->color('danger')->columnSpanFull()->visible(fn (?Facture $record) => (bool) $record?->motif_rejet),
            ]),
            Section::make('Historique')->schema([
                RepeatableEntry::make('historique')->label('')->columns(4)->schema([
                    TextEntry::make('action')->label('Étape')->badge()
                        ->formatStateUsing(fn ($state) => ['creation' => 'Création', 'soumission' => 'Soumission', 'validation' => 'Validation', 'rejet' => 'Rejet', 'paiement' => 'Paiement', 'archivage' => 'Archivage'][$state] ?? $state),
                    TextEntry::make('par')->label('Par')->placeholder('—'),
                    TextEntry::make('le')->label('Le')->dateTime('d/m/Y H:i'),
                    TextEntry::make('note')->label('Note')->placeholder('—'),
                ]),
            ]),
        ]);
    }

    public static function color(?string $statut): string
    {
        return match ($statut) {
            'a_valider' => 'warning', 'validee' => 'info', 'payee' => 'success', 'rejetee' => 'danger', 'archivee' => 'gray', default => 'gray',
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date_facture', 'desc')
            ->recordUrl(fn (Facture $record) => static::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('date_facture')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('numero')->label('N°')->searchable()->weight('bold'),
                TextColumn::make('fournisseur')->searchable(),
                TextColumn::make('categorie')->label('Catégorie')->badge()->formatStateUsing(fn ($state) => Facture::CATEGORIES[$state] ?? $state),
                TextColumn::make('montant')->money('XOF')->sortable(),
                TextColumn::make('vehicle.immatriculation')->label('Véhicule')->placeholder('—'),
                TextColumn::make('bailleur')->placeholder('—')->toggleable(),
                TextColumn::make('statut')->badge()->formatStateUsing(fn ($state) => Facture::STATUTS[$state] ?? $state)->color(fn ($state) => self::color($state)),
                TextColumn::make('createur.name')->label('Saisie par')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('statut')->options(Facture::STATUTS)->multiple(),
                SelectFilter::make('categorie')->options(Facture::CATEGORIES),
                SelectFilter::make('vehicle_id')->label('Véhicule')->relationship('vehicle', 'immatriculation'),
                Filter::make('periode')
                    ->schema([DatePicker::make('du')->label('Du')->default(\App\Support\DashboardFilters::filterFrom()), DatePicker::make('au')->label('Au')->default(\App\Support\DashboardFilters::filterUntil())])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['du'] ?? null, fn ($q, $v) => $q->whereDate('date_facture', '>=', $v))
                        ->when($data['au'] ?? null, fn ($q, $v) => $q->whereDate('date_facture', '<=', $v))),
            ])
            ->recordActions([
                ViewAction::make()->label('Voir'),
                EditAction::make()->visible(fn (Facture $f) => $f->isEditable() && auth()->user()->can('update_factures')),
                ...self::workflowActions(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()->visible(fn () => auth()->user()->can('delete_factures'))])]);
    }

    /** @return array<int, Action> */
    public static function workflowActions(): array
    {
        $done = fn (string $title) => Notification::make()->title($title)->success()->send();

        return [
            Action::make('soumettre')->label('Soumettre')->icon('heroicon-o-paper-airplane')->color('warning')
                ->visible(fn (Facture $f) => $f->isEditable() && auth()->user()->can('create_factures'))
                ->requiresConfirmation()->modalDescription('La facture sera transmise pour validation et ne sera plus modifiable.')
                ->action(function (Facture $f) use ($done) {
                    $f->soumettre(auth()->user());
                    $done('Facture soumise à validation');
                }),
            Action::make('valider')->label('Valider')->icon('heroicon-o-check-badge')->color('success')
                ->visible(fn (Facture $f) => $f->statut === 'a_valider' && auth()->user()->can('validate_factures'))
                ->requiresConfirmation()
                ->action(function (Facture $f) use ($done) {
                    $f->valider(auth()->user());
                    $done('Facture validée');
                }),
            Action::make('rejeter')->label('Rejeter')->icon('heroicon-o-x-circle')->color('danger')
                ->visible(fn (Facture $f) => $f->statut === 'a_valider' && auth()->user()->can('validate_factures'))
                ->schema([Textarea::make('motif')->label('Motif du rejet')->required()->maxLength(500)])
                ->action(function (Facture $f, array $data) use ($done) {
                    $f->rejeter(auth()->user(), $data['motif']);
                    $done('Facture rejetée');
                }),
            Action::make('payer')->label('Enregistrer le paiement')->icon('heroicon-o-banknotes')->color('success')
                ->visible(fn (Facture $f) => $f->statut === 'validee' && auth()->user()->can('pay_factures'))
                ->schema([
                    Select::make('mode')->label('Mode de paiement')->options(Facture::MODES_PAIEMENT)->required(),
                    TextInput::make('reference')->label('Référence (n° de virement, chèque…)')->maxLength(80),
                ])
                ->action(function (Facture $f, array $data) use ($done) {
                    $f->payer(auth()->user(), $data['mode'], $data['reference'] ?? null);
                    $done('Paiement enregistré');
                }),
            Action::make('archiver')->label('Archiver')->icon('heroicon-o-archive-box')->color('gray')
                ->visible(fn (Facture $f) => $f->statut === 'payee' && auth()->user()->can('pay_factures'))
                ->requiresConfirmation()
                ->action(function (Facture $f) use ($done) {
                    $f->archiver(auth()->user());
                    $done('Facture archivée');
                }),
            Action::make('fichier')->label('Télécharger')->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (Facture $f) => $f->fichier_path && Storage::disk('local')->exists($f->fichier_path))
                ->action(fn (Facture $f) => Storage::disk('local')->download($f->fichier_path)),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFactures::route('/'),
            'create' => CreateFacture::route('/create'),
            'view' => ViewFacture::route('/{record}'),
            'edit' => EditFacture::route('/{record}/edit'),
        ];
    }
}
