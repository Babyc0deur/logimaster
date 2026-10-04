<?php

namespace Database\Seeders;

use App\Models\Chronogramme;
use App\Models\Circuit;
use App\Models\District;
use App\Models\Budget;
use App\Models\Driver;
use App\Models\Expense;
use App\Models\Facture;
use App\Models\Immobilisation;
use App\Models\LivraisonEspc;
use App\Models\Personnel;
use App\Models\Vidange;
use App\Models\Espc;
use App\Models\FuelPrice;
use App\Models\Pres;
use App\Models\Ravitaillement;
use App\Models\Region;
use App\Models\SortieVehicule;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Données de démonstration (district ANYAMA) : véhicules, circuit, chronogramme, sorties,
 * et un compte par rôle (mot de passe : "password", à changer hors environnement local).
 */
class LogimasterDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Organisation réelle (33 régions / 113 districts) + prix du carburant du classeur Logimaster.
        $this->call(OrganisationSeeder::class);
        FuelPrice::firstOrCreate(['type_carburant' => 'diesel', 'date_effet' => '2024-01-01'], ['prix' => 715]);
        FuelPrice::firstOrCreate(['type_carburant' => 'essence', 'date_effet' => '2024-01-01'], ['prix' => 875]);

        $anyama = District::where('name', 'ANYAMA')->firstOrFail();
        $abidjan1 = Region::where('name', 'ABIDJAN 1')->firstOrFail();
        $abidjan2 = Region::where('name', 'ABIDJAN 2')->firstOrFail();
        $districts = collect([$anyama])
            ->merge(District::where('region_id', $abidjan1->id)->where('id', '!=', $anyama->id)->get())   // [0] = ANYAMA
            ->merge(District::where('region_id', $abidjan2->id)->get());

        $password = Hash::make('password');
        $accounts = [
            ['pres@logimaster.test', 'Admin National', User::ROLE_PRES_ADMIN, []],
            ['region@logimaster.test', 'Responsable Région Abidjan 1', User::ROLE_REGION_MANAGER, []],
            ['district@logimaster.test', 'Gestionnaire district Anyama', User::ROLE_DISTRICT_MANAGER, [0]],
            ['bailleur@logimaster.test', 'Superviseur Bailleur', User::ROLE_SUPERVISEUR, range(0, 9)],
        ];
        foreach ($accounts as [$email, $name, $role, $idx]) {
            $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => $password, 'is_active' => true]);
            $user->syncRoles([$role]);
            $role === User::ROLE_REGION_MANAGER && $user->update(['region_id' => $abidjan1->id]); // sa région, pas une liste de districts
            $user->districts()->sync($districts->only(is_array($idx) ? $idx : iterator_to_array($idx))->pluck('id'));
        }

        $district = $anyama;
        $vehicles = collect([
            ['1234 AB 01', 'Toyota', 'Hilux', 'diesel', 11.5],
            ['5678 CD 01', 'Nissan', 'Navara', 'diesel', 10.8],
            ['9012 EF 01', 'Yamaha', 'AG100', 'essence', 3.2],
        ])->map(fn ($v) => Vehicle::firstOrCreate(['immatriculation' => $v[0]], [
            'district_id' => $district->id, 'marque' => $v[1], 'modele' => $v[2],
            'type_carburant' => $v[3], 'consommation_theorique' => $v[4],
            'km_actuel' => 42000, 'km_vidange' => 45000,
            'date_ct' => now()->addMonths(3), 'date_assurance' => now()->addMonths(5),
        ]));

        $driver = Driver::firstOrCreate(['matricule' => 'CH-0001'], [
            'district_id' => $district->id, 'nom_complet' => 'Kouassi Yao', 'telephone' => '0707070707',
            'categorie_permis' => 'B', 'permis_expiration' => now()->addYear(),
        ]);

        $espcs = collect([
            ['CSR Anyama', 5.4944, -4.0518, 'Dr Konan', '0505050501'],
            ['CSU Abobo', 5.4167, -4.0167, 'Dr Traoré', '0505050502'],
            ['Dispensaire Yopougon', 5.3364, -4.0876, 'Mme Koffi', '0505050503'],
        ])->map(fn ($e) => Espc::firstOrCreate(
            ['district_id' => $district->id, 'nom' => $e[0]],
            ['type' => 'centre_sante', 'statut' => 'actif', 'gps_lat' => $e[1], 'gps_lon' => $e[2], 'responsable' => $e[3], 'telephone' => $e[4],
                'adresse' => $e[0].', Abidjan', 'email' => str($e[0])->slug('.').'@demo.logimaster.test']
        ));

        $circuit = Circuit::firstOrCreate(['district_id' => $district->id, 'nom' => 'Circuit Nord'], [
            'distance_totale' => 85, 'temps_estime_min' => 240, 'frequence' => 'hebdomadaire',
            'point_depart' => 'DDKM Anyama', 'depart_lat' => 5.4950, 'depart_lon' => -4.0510,
        ]);
        $circuit->point_depart ?: $circuit->update(['point_depart' => 'DDKM Anyama', 'depart_lat' => 5.4950, 'depart_lon' => -4.0510]);
        $circuit->espc()->syncWithoutDetaching($espcs->mapWithKeys(fn ($e, $i) => [$e->id => ['ordre' => $i + 1, 'distance_km' => [1.5, 14, 22][$i]]])->all());

        // Chronogramme : une sortie par semaine sur le circuit, du lundi de la semaine passée à +2 semaines.
        if (Chronogramme::where('district_id', $district->id)->doesntExist()) {
            foreach ([-7, 0, 7, 14] as $i => $offset) {
                Chronogramme::create([
                    'district_id' => $district->id, 'vehicle_id' => $vehicles[$i % 2]->id, 'driver_id' => $driver->id,
                    'circuit_id' => $circuit->id, 'date_prevue' => now()->startOfWeek()->addDays($offset),
                    'heure_depart' => '07:30', 'motif' => 'distribution', 'destination' => $circuit->nom,
                    'statut' => $offset < 0 ? 'realisee' : 'planifiee',
                ]);
            }
        }

        if (SortieVehicule::where('district_id', $district->id)->doesntExist()) {
            $sortie = SortieVehicule::create([
                'owner_type' => 'district', 'owner_id' => $district->id, 'district_id' => $district->id,
                'vehicle_id' => $vehicles[0]->id, 'driver_id' => $driver->id, 'circuit_id' => $circuit->id,
                'km_depart' => 42000, 'km_arrivee' => 42090, 'motif' => 'distribution',
                'circuit_respecte' => true, 'statut' => 'terminee',
            ]);
            Ravitaillement::create([
                'owner_type' => 'district', 'owner_id' => $district->id, 'district_id' => $district->id,
                'vehicle_id' => $vehicles[0]->id, 'sortie_id' => $sortie->id, 'driver_id' => $driver->id,
                'litres' => 12, 'prix_unitaire' => 735, 'station' => 'Total Anyama',
            ]);
        }

        $this->seedPersonnel($district);
        $this->seedMobile($district, $vehicles, $driver, $circuit);
        $this->seedLivraisons($district);
        \App\Support\Modules::enabled('finance') && $this->seedFinance($district, $vehicles);
        $this->seedMaintenance($district, $vehicles);
    }

    private function seedPersonnel(District $district): void
    {
        foreach ([
            ['Koné Ibrahim', 'chef_mission', '0707010101'], ['Bamba Aïcha', 'chef_mission', '0707010102'],
            ['Yao Marie', 'passager', '0707010103'], ['Diallo Seydou', 'passager', '0707010104'], ["N'Guessan Paul", 'passager', '0707010105'],
        ] as [$nom, $fonction, $tel]) {
            Personnel::firstOrCreate(['district_id' => $district->id, 'nom_complet' => $nom], [
                'fonction' => $fonction, 'telephone' => $tel, 'statut' => 'actif', 'email' => str($nom)->slug('.').'@demo.logimaster.test',
            ]);
        }
    }

    /** Compte convoyeur (application mobile) et sortie validée du jour : de quoi essayer le parcours complet. */
    private function seedMobile(District $district, $vehicles, Driver $driver, Circuit $circuit): void
    {
        $chef = Personnel::where('district_id', $district->id)->where('nom_complet', 'Koné Ibrahim')->first();
        if (! $chef) {
            return;
        }
        // Le compte mobile est créé automatiquement avec la fiche ; pour la démonstration on lui donne un mot de passe connu.
        $user = $chef->user ?? app(\App\Domain\Mobile\ConvoyeurAccess::class)->sync($chef);
        $user->forceFill(['email' => 'convoyeur@logimaster.test', 'password' => 'password', 'must_change_password' => false, 'is_active' => true])->save();
        $chef->forceFill(['code_acces' => null])->saveQuietly();

        $plan = Chronogramme::firstOrCreate(
            ['district_id' => $district->id, 'circuit_id' => $circuit->id, 'date_prevue' => today()->toDateString(), 'motif' => 'distribution'],
            ['vehicle_id' => $vehicles[0]->id, 'driver_id' => $driver->id, 'heure_depart' => '07:30', 'statut' => 'planifiee',
                'validation_statut' => 'valide', 'valide_at' => now()]
        );
        $plan->personnels()->syncWithoutDetaching([$chef->id]);
    }

    /** Sites de la sortie passée : 2 livrés (dont un en transit, avec un jour de retard) et 1 non livré avec sa raison. */
    private function seedLivraisons(District $district): void
    {
        $past = Chronogramme::where('district_id', $district->id)->whereDate('date_prevue', '<', today())->first();
        if (! $past || LivraisonEspc::where('chronogramme_id', $past->id)->where('statut', '!=', 'planifie')->exists()) {
            return;
        }
        $rows = LivraisonEspc::where('chronogramme_id', $past->id)->orderBy('ordre')->get();
        $rows->get(0)?->update(['statut' => 'livre', 'date_livraison' => $past->date_prevue->toDateString(), 'lieu_livraison' => 'site']);
        $rows->get(1)?->update(['statut' => 'livre', 'date_livraison' => $past->date_prevue->addDay()->toDateString(), 'lieu_livraison' => 'transit']);
        $rows->get(2)?->update(['statut' => 'non_livre', 'raison_non_livraison' => 'Route inondée, site inaccessible']);
    }

    private function seedFinance(District $district, $vehicles): void
    {
        $month = now()->startOfMonth()->toDateString();
        foreach ([['carburant', 450000], ['maintenance', 300000], ['autres', 100000]] as [$poste, $montant]) {
            Budget::firstOrCreate(['district_id' => $district->id, 'period' => $month, 'poste' => $poste, 'bailleur' => ''], ['montant_alloue' => $montant]);
        }
        Budget::firstOrCreate(['district_id' => $district->id, 'period' => $month, 'poste' => 'carburant', 'bailleur' => 'GAVI'], ['montant_alloue' => 200000]);

        if (Expense::where('district_id', $district->id)->doesntExist()) {
            foreach ([['peage', 'Péage autoroute', 3000, 2], ['reparation', 'Garage Central', 45000, 6], ['autres', 'Frais de mission', 15000, 9]] as [$type, $benef, $montant, $ago]) {
                Expense::create(['district_id' => $district->id, 'vehicle_id' => $vehicles[0]->id, 'type' => $type, 'beneficiaire' => $benef,
                    'montant' => $montant, 'date_depense' => now()->subDays($ago)->toDateString(), 'motif' => 'distribution']);
            }
        }

        $admin = User::where('email', 'pres@logimaster.test')->first();
        $creator = User::where('email', 'district@logimaster.test')->first();
        $mois = now()->format('Ym');
        foreach ([
            ["FAC-{$mois}-001", 'carburant', 'TotalEnergies', 120000, 'brouillon'],
            ["FAC-{$mois}-002", 'maintenance', 'Garage Central', 85000, 'a_valider'],
            ["FAC-{$mois}-003", 'maintenance', 'Pneus Plus', 60000, 'validee'],
            ["FAC-{$mois}-004", 'carburant', 'TotalEnergies', 98000, 'payee'],
            ["FAC-{$mois}-005", 'autres', 'Imprimerie Moderne', 25000, 'rejetee'],
        ] as [$num, $cat, $fournisseur, $montant, $statut]) {
            if (Facture::where('district_id', $district->id)->where('numero', $num)->exists()) {
                continue;
            }
            $f = Facture::create(['district_id' => $district->id, 'numero' => $num, 'categorie' => $cat, 'fournisseur' => $fournisseur, 'montant' => $montant,
                'date_facture' => now()->subDays(5)->toDateString(), 'vehicle_id' => $vehicles[0]->id, 'bailleur' => $cat === 'carburant' ? 'GAVI' : null,
                'cree_par' => $creator?->id]);
            $at = now()->subDays(3);
            $f->update(match ($statut) {
                'a_valider' => ['statut' => $statut, 'soumise_at' => $at],
                'validee' => ['statut' => $statut, 'soumise_at' => $at, 'valide_par' => $admin?->id, 'valide_at' => $at],
                'payee' => ['statut' => $statut, 'soumise_at' => $at, 'valide_par' => $admin?->id, 'valide_at' => $at, 'paye_par' => $admin?->id, 'paye_at' => now()->subDay(), 'mode_paiement' => 'virement', 'reference_paiement' => 'VIR-0001'],
                'rejetee' => ['statut' => $statut, 'soumise_at' => $at, 'valide_par' => $admin?->id, 'valide_at' => $at, 'motif_rejet' => 'Justificatif illisible'],
                default => [],
            });
        }
    }

    private function seedMaintenance(District $district, $vehicles): void
    {
        if (Vidange::where('district_id', $district->id)->doesntExist()) {
            Vidange::create(['district_id' => $district->id, 'vehicle_id' => $vehicles[0]->id, 'date' => now()->subMonths(4)->toDateString(), 'km' => 40000,
                'prochain_km' => 45000, 'type' => 'simple', 'montant' => 55000, 'prestataire' => 'Garage Central']);
        }
        if (Immobilisation::where('district_id', $district->id)->doesntExist()) {
            Immobilisation::create(['owner_type' => 'district', 'owner_id' => $district->id, 'district_id' => $district->id, 'vehicle_id' => $vehicles[1]->id,
                'date_debut' => now()->subDays(12)->toDateString(), 'date_fin' => now()->subDays(8)->toDateString(), 'motif' => 'panne',
                'description' => 'Remplacement alternateur', 'montant' => 90000, 'prestataire' => 'Garage Central']);
        }
    }
}
