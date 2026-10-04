<?php

namespace App\Filament\Pages;

use App\Domain\Indicators\IndicatorCatalog;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Objectifs (seuils de conformité) des indicateurs DDKM : couleurs des cartes, rapports et recommandations. */
class IndicatorTargets extends Page
{
    protected string $view = 'filament.pages.alert-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $navigationLabel = 'Objectifs des indicateurs';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $title = 'Objectifs des indicateurs DDKM';

    protected static ?string $slug = 'objectifs-indicateurs';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('manage_settings');
    }

    /** Indicateurs ayant un objectif (les autres sont informatifs). */
    private static function targeted(): array
    {
        return collect(IndicatorCatalog::defaults())->filter(fn ($m) => $m['direction'] !== null)->all();
    }

    public function mount(): void
    {
        $this->form->fill(collect(self::targeted())->mapWithKeys(fn ($m, $key) => ["objectif_{$key}" => IndicatorCatalog::get($key)['target']])->all());
    }

    public function form(Schema $schema): Schema
    {
        $fields = collect(self::targeted())->map(fn ($m, $key) => TextInput::make("objectif_{$key}")
            ->label($m['label'])->numeric()->required()->minValue(0)->maxValue($m['unit'] === '%' ? 100 : null)
            ->suffix($m['unit'])->prefix($m['direction'] === 'down' ? '≤' : '≥')
            ->helperText('Valeur par défaut : '.IndicatorCatalog::format($key, $m['target'])))->values()->all();

        return $schema->statePath('data')->components([
            Section::make('Objectifs')->description('Au-dessus (≥) ou en dessous (≤) de cette valeur, l\'indicateur est conforme (vert).')->columns(2)->schema($fields),
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
        Notification::make()->title('Objectifs enregistrés')->success()->send();
    }
}
