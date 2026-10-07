<?php

namespace Tests\Feature;

use App\Domain\Organisation\PresMapping;
use App\Models\District;
use App\Models\Pres;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 10 PRES : chaque région sanitaire est rattachée à son pôle ; l'ancien PRES unique disparaît ; relancer ne change rien. */
class PresMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_regions_are_attached_to_their_pres(): void
    {
        $legacy = Pres::create(['name' => PresMapping::LEGACY]);
        foreach (['NAWA', 'INDENIE DUABLIN', 'HAUT SASSANDRA', 'NZI', 'GÔH', 'SAN PEDRO', 'ABIDJAN 1'] as $name) {
            Region::create(['pres_id' => $legacy->id, 'name' => $name]);
        }

        $sp = District::create(['region_id' => Region::where('name', 'SAN PEDRO')->value('id'), 'name' => 'SAN PEDRO', 'sync_id' => 'SP', 'sync_password_hash' => 'x']);
        $r = PresMapping::apply();
        $this->assertSame('SAN-PEDRO', $sp->fresh()->name);   // district renommé
        $this->assertSame(7, $r['moved']);
        $this->assertSame([], $r['unknown']);
        $pres = fn (string $region) => Region::where('name', $region)->first()->pres->name;
        $this->assertSame('PRES de San-Pédro', $pres('NAWA'));
        $this->assertSame('PRES de San-Pédro', $pres('SAN-PEDRO'));
        $this->assertSame("PRES d'Abengourou", $pres('INDENIE-DJUABLIN'));   // noms corrigés
        $this->assertSame('PRES de Daloa', $pres('HAUT-SASSANDRA'));
        $this->assertSame(0, Region::whereIn('name', ['INDENIE DUABLIN', 'HAUT SASSANDRA'])->count());
        $this->assertSame('PRES de Yamoussoukro', $pres('NZI'));
        $this->assertSame('PRES de Yamoussoukro', $pres('GÔH'));
        $this->assertSame("PRES d'Abidjan", $pres('ABIDJAN 1'));
        $this->assertNull(Pres::where('name', PresMapping::LEGACY)->first());
        $this->assertSame(10, Pres::count());
        $this->assertSame(0, PresMapping::apply()['moved']);   // sans effet la seconde fois
        $this->assertSame(33, collect(PresMapping::MAP)->flatten()->count());
    }
}
