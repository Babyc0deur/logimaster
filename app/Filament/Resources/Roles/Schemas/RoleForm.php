<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Support\PermissionCatalog;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Rôle : nom, puis ses permissions rangées par élément de l'application (une section par élément, cases en français). */
class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        $sections = [];
        foreach (PermissionCatalog::groups() as $key => $group) {
            $sections[] = Section::make($group['label'])->description($group['description'])->compact()->schema([
                CheckboxList::make("permissions_{$key}")->hiddenLabel()->options($group['options'])
                    // libellés longs (« Voir le tableau de bord ») : deux colonnes au plus pour qu'ils tiennent sur une ligne
                    ->columns(max(array_map('mb_strlen', $group['options'])) > 12 ? ['default' => 1, 'md' => 2] : ['default' => 2, 'md' => 4])
                    ->gridDirection('row')->bulkToggleable(count($group['options']) > 2),
            ]);
        }

        return $schema->components([
            TextInput::make('name')->label('Nom du rôle')->required()->unique(ignoreRecord: true)->maxLength(255)
                ->helperText(fn (?string $state) => $state ? PermissionCatalog::roleLabel($state) : null),
            Grid::make(['default' => 1, 'lg' => 2])->columnSpanFull()->schema($sections),
        ]);
    }

    /** Permissions du rôle réparties dans les champs de chaque élément (remplissage du formulaire). */
    public static function fill(array $data, array $permissionNames): array
    {
        foreach (PermissionCatalog::groups() as $key => $group) {
            $data["permissions_{$key}"] = array_values(array_intersect(array_keys($group['options']), $permissionNames));
        }

        return $data;
    }

    /** Toutes les permissions cochées, tous éléments confondus ; retire les champs d'éléments des données du rôle. */
    public static function extract(array &$data): array
    {
        $names = [];
        foreach (array_keys($data) as $field) {
            if (str_starts_with($field, 'permissions_')) {
                $names = [...$names, ...(array) $data[$field]];
                unset($data[$field]);
            }
        }

        return array_values(array_unique($names));
    }
}
