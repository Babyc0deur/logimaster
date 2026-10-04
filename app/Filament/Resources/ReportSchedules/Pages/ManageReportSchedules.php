<?php

namespace App\Filament\Resources\ReportSchedules\Pages;

use App\Filament\Resources\ReportSchedules\ReportScheduleResource;
use Filament\Resources\Pages\ManageRecords;

/** La création se fait depuis « Rapports → Planifier un envoi ». */
class ManageReportSchedules extends ManageRecords
{
    protected static string $resource = ReportScheduleResource::class;
}
