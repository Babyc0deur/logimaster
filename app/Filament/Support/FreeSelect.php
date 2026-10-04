<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\DB;

/** Liste déroulante « paramétrable » : valeurs par défaut + valeurs déjà saisies + ajout libre. */
class FreeSelect
{
    /**
     * @param  array<int|string, string>  $defaults  clé => libellé
     */
    public static function make(string $field, string $table, array $defaults, string $label): Select
    {
        return Select::make($field)
            ->label($label)
            ->searchable()
            ->options(function () use ($field, $table, $defaults) {
                $existing = DB::table($table)->whereNotNull($field)->where($field, '!=', '')->distinct()->pluck($field)
                    ->mapWithKeys(fn ($v) => [$v => ucfirst($v)])->all();

                return $defaults + $existing;
            })
            ->getOptionLabelUsing(fn ($value) => $defaults[$value] ?? ucfirst((string) $value))
            ->createOptionForm([TextInput::make('value')->label($label)->required()->maxLength(60)])
            ->createOptionUsing(fn (array $data) => trim($data['value']));
    }
}
