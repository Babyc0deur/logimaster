<?php

namespace Tests\Unit;

use App\Domain\Import\Harmonizer;
use PHPUnit\Framework\TestCase;

class HarmonizerTest extends TestCase
{
    public function test_circuit_names_follow_one_convention(): void
    {
        foreach (['1', 'C1', 'c 1', 'CIR1', 'circuit  1', 'Circuit1', 'CIRCUT 1', 'CIRUIT 1', 'CIUCUIT 1'] as $raw) {
            $this->assertSame('CIRCUIT 1', Harmonizer::circuitName($raw), $raw);
        }
        $this->assertSame('CIRCUIT 1 - ANANDA', Harmonizer::circuitName('CIR1: ANANDA'));
        $this->assertSame('AXE LAKOTA', Harmonizer::circuitName('Axe  Lakota'));
        $this->assertSame('CIRCUIT VILLE', Harmonizer::circuitName('circuit ville'));
    }

    public function test_motifs_and_destinations_are_not_routes(): void
    {
        foreach ([['PNLP', 1], ['GARAGE', 1], ['DISTRICT', 1], ['AUTRE DÉPLACEMENT', 10], ['DCPEV', 2]] as [$name, $sites]) {
            $this->assertFalse(Harmonizer::isRoute($name, $sites), $name);
        }
        $this->assertTrue(Harmonizer::isRoute('CIRCUIT 1', 1));
        $this->assertTrue(Harmonizer::isRoute('PNLP', 5)); // un vrai parcours portant ce nom aurait plus de deux sites
        $this->assertTrue(Harmonizer::isRoute('AXE GAGNOA', 3));
    }

    public function test_vehicles_brands_models_and_funders(): void
    {
        $this->assertSame(['TOYOTA', 'HIACE'], Harmonizer::vehicle('Toyota Hiace', null));
        $this->assertSame(['TOYOTA', 'HIACE'], Harmonizer::vehicle('TOYATA  HIACE', null));
        $this->assertSame(['TOYOTA', 'HIACE'], Harmonizer::vehicle('TOYOTA', 'hyace'));
        $this->assertSame(['FORD', 'TRANSIT FOURGON'], Harmonizer::vehicle('FORD TRANSIT', 'FOURGON'));
        $this->assertSame(['TOYOTA', 'HIACE'], Harmonizer::vehicle('TOYOTA HIACE', 'HIACE'));
        $this->assertSame([null, null], Harmonizer::vehicle('', ''));
        foreach (['FOND MONDIAL', 'UCP FM', 'FM', 'UCP-FM', 'Fonds Mondial', 'UCP FOND MONDIAL'] as $raw) {
            $this->assertSame('FONDS MONDIAL', Harmonizer::funder($raw), $raw);
        }
        $this->assertSame('PNLP', Harmonizer::funder('P NLP'));
        $this->assertSame('PNLP', Harmonizer::funder('Programme National de lutte contre le paludisme'));
        $this->assertNull(Harmonizer::funder('  '));
    }

    public function test_facility_names_and_types(): void
    {
        $this->assertSame('CSR-DM PUBLIC DE DATTA', Harmonizer::facilityName(' csr - dm  public de Datta '));
        $this->assertSame('CSR', Harmonizer::facilityType('CSR-DM PUBLIC DE DATTA'));
        $this->assertSame('CSU', Harmonizer::facilityType('CSUI FOUNGESSO'));
        $this->assertSame('HG', Harmonizer::facilityType('HG BONOUA'));
        $this->assertSame('CLINIQUE', Harmonizer::facilityType('CLINIQUE MEDICALE BIRAYA'));
        $this->assertNull(Harmonizer::facilityType('KASSASSO'));
        $this->assertSame(Harmonizer::key('CSR-DM Wongué'), Harmonizer::key('csr dm wongue'));
    }
}
