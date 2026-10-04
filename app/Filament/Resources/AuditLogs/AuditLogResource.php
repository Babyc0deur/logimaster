<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Journal d'audit en lecture seule (écritures API, connexions…). Réservé à view_audit_logs. */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = "Journal d'audit";

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $modelLabel = "entrée d'audit";

    protected static ?string $pluralModelLabel = "journal d'audit";

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('view_audit_logs');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $json = fn (AuditLog $r, int $flags = 0) => $r->metadata ? json_encode($r->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | $flags) : null;

        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('user:id,name'))
            ->columns([
                TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('user.name')->label('Utilisateur')->placeholder('Système')->searchable(),
                TextColumn::make('module')->label('Module')->badge()->placeholder('—'),
                TextColumn::make('action')->label('Action')->searchable(),
                TextColumn::make('details')->label('Détails')->state(fn (AuditLog $r) => $json($r))
                    ->limit(80)->tooltip(fn (AuditLog $r) => $json($r, JSON_PRETTY_PRINT)),
            ])
            ->filters([
                SelectFilter::make('module')->options(fn () => AuditLog::query()->whereNotNull('module')->distinct()->orderBy('module')->pluck('module', 'module')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAuditLogs::route('/')];
    }
}
