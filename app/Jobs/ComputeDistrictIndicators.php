<?php

namespace App\Jobs;

use App\Domain\Indicators\IndicatorService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ComputeDistrictIndicators implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $districtId, public string $month)
    {
        $this->onQueue('reports');
    }

    public function handle(IndicatorService $service): void
    {
        $service->computeForDistrict($this->districtId, CarbonImmutable::parse($this->month));
    }
}
