<?php

namespace App\Filament\Resources\Drivers\RelationManagers;

use App\Models\Document;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

/** Documents du chauffeur : permis scanné, visite médicale, attestations. */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documents';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('categorie')->options(['permis' => 'Permis de conduire', 'visite_medicale' => 'Visite médicale', 'attestation' => 'Attestation', 'autre' => 'Autre'])->required(),
            DatePicker::make('date_expiration')->label("Date d'expiration")->native(false)->displayFormat('d/m/Y'),
            FileUpload::make('fichier_url')->label('Fichier')->required()->disk('local')->directory('documents')->visibility('private')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(10240)->downloadable()->openable()->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('categorie')
            ->columns([
                TextColumn::make('categorie')->label('Type')->badge(),
                TextColumn::make('date_expiration')->label('Expiration')->date('d/m/Y')->placeholder('—')
                    ->color(fn (Document $r) => $r->date_expiration?->isPast() ? 'danger' : null),
                TextColumn::make('created_at')->label('Ajouté le')->dateTime('d/m/Y'),
            ])
            ->headerActions([
                CreateAction::make()->label('Ajouter un document')
                    ->mutateDataUsing(fn (array $data) => $data + ['district_id' => $this->getOwnerRecord()->district_id, 'uploaded_by' => auth()->id()]),
            ])
            ->recordActions([
                Action::make('telecharger')->label('Télécharger')->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (Document $r) => Storage::disk('local')->exists($r->fichier_url))
                    ->action(fn (Document $r) => Storage::disk('local')->download($r->fichier_url)),
                DeleteAction::make(),
            ]);
    }
}
