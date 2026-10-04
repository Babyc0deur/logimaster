<?php

namespace App\Filament\Resources\Budgets\Pages;

use App\Filament\Resources\Budgets\BudgetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBudgets extends ManageRecords
{
    protected static string $resource = BudgetResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nouveau budget')->visible(fn () => auth()->user()->can('create_budgets'))];
    }
}
