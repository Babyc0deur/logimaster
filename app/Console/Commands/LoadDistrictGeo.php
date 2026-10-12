<?php

namespace App\Console\Commands;

use App\Domain\Organisation\PresMapping;
use App\Models\District;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Renseigne le chef-lieu (latitude, longitude) des districts depuis database/data/districts_geo.json
 * (OpenStreetMap, ODbL). Ne touche pas un district qui a déjà des coordonnées, sauf avec --force.
 */
class LoadDistrictGeo extends Command
{
    protected $signature = 'districts:geo {--force : remplace aussi les coordonnées déjà renseignées}';

    protected $description = 'Charge les coordonnées des chefs-lieux des districts sanitaires';

    public function handle(): int
    {
        $path = database_path('data/districts_geo.json');
        if (! is_file($path) || ! Schema::hasColumn('districts', 'latitude')) {
            return self::SUCCESS;
        }
        $geo = collect(json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR))
            ->mapWithKeys(fn ($v, $name) => [PresMapping::key($name) => $v]);
        $done = 0;
        foreach (District::query()->when(! $this->option('force'), fn ($q) => $q->whereNull('latitude'))->get() as $d) {
            if ($c = $geo[PresMapping::key($d->name)] ?? null) {
                District::whereKey($d->id)->update(['latitude' => $c['lat'], 'longitude' => $c['lon']]);
                $done++;
            }
        }
        $this->info("Coordonnées : {$done} district(s) renseigné(s).");

        return self::SUCCESS;
    }
}
