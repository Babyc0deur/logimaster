<?php

namespace App\Filament\Resources\Chronogrammes\Pages;

use App\Filament\Resources\Chronogrammes\ChronogrammeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateChronogramme extends CreateRecord
{
    protected static string $resource = ChronogrammeResource::class;

    /** Préremplit la date depuis le calendrier (?date=YYYY-MM-DD). */
    public function mount(): void
    {
        parent::mount();
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) request('date'))) {
            $this->form->fill(['date_prevue' => request('date')]);
        }
    }
}
