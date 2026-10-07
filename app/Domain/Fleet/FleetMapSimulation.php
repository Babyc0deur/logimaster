<?php

namespace App\Domain\Fleet;

use App\Models\Circuit;
use App\Models\District;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

/**
 * Données de la carte « Cartographie » : les véhicules du district, chacun sur un circuit (le dernier planifié pour lui,
 * sinon un circuit du district), avec les étapes à parcourir (départ du district → sites → retour).
 *
 * Positions : coordonnées GPS des sites quand elles sont renseignées ; sinon positions de démonstration calculées autour
 * du chef-lieu du district (même résultat à chaque affichage), signalées comme telles sur la carte.
 */
class FleetMapSimulation
{
    /** Teintes des véhicules sur la carte. */
    public const COLORS = ['#c2410c', '#1d4ed8', '#047857', '#7c3aed', '#b45309', '#be123c', '#0e7490', '#4d7c0f'];

    /** Chef-lieu par défaut (centre de la Côte d'Ivoire) si le district n'a ni coordonnées connues ni site géolocalisé. */
    private const FALLBACK = [7.54, -5.55];

    public function build(District $district, int $max = 8): array
    {
        $centre = $this->centre($district);
        $circuits = Circuit::with('espc')->where('district_id', $district->id)->orderBy('nom')->get()
            ->filter(fn (Circuit $c) => $c->espc->isNotEmpty())->values();
        $vehicles = Vehicle::where('district_id', $district->id)->orderBy('immatriculation')->limit($max)->get();

        // dernier circuit planifié pour chaque véhicule
        $last = DB::table('chronogrammes')->whereNull('deleted_at')->where('district_id', $district->id)->whereNotNull('circuit_id')
            ->orderByDesc('date_prevue')->get(['vehicle_id', 'circuit_id'])->unique('vehicle_id')->pluck('circuit_id', 'vehicle_id');

        $used = [];
        $simulated = false;
        $out = [];
        foreach ($vehicles as $i => $v) {
            $circuit = $circuits->firstWhere('id', $last[$v->id] ?? null);
            if (! $circuit || in_array($circuit->id, $used, true)) {
                $circuit = $circuits->first(fn ($c) => ! in_array($c->id, $used, true)) ?? $circuits->get($i % max(1, $circuits->count()));
            }
            if (! $circuit) {
                continue;
            }
            $used[] = $circuit->id;
            $index = $circuits->search(fn ($c) => $c->id === $circuit->id);
            $stops = [];
            foreach ($circuit->espc->take(8)->values() as $k => $e) {
                $real = $e->hasGps();
                $simulated = $simulated || ! $real;
                [$lat, $lon] = $real ? [(float) $e->gps_lat, (float) $e->gps_lon] : $this->demoPosition($centre, $index, $circuits->count(), $k, $e->id);
                $stops[] = ['nom' => $e->nom, 'type' => $e->type, 'lat' => round($lat, 6), 'lon' => round($lon, 6), 'demo' => ! $real];
            }
            $out[] = [
                'immatriculation' => $v->immatriculation,
                'modele' => trim(($v->marque ?? '').' '.($v->modele ?? '')),
                'statut' => $v->statut,
                'circuit' => $circuit->nom,
                'color' => self::COLORS[$i % count(self::COLORS)],
                'stops' => $stops,
            ];
        }

        return [
            'district' => $district->name,
            'centre' => ['lat' => $centre[0], 'lon' => $centre[1]],
            'vehicles' => $out,
            'simulated' => $simulated,
        ];
    }

    /** Chef-lieu : coordonnées connues du district, sinon moyenne des sites géolocalisés, sinon centre du pays. */
    private function centre(District $district): array
    {
        $known = config('logimaster.district_centres')[mb_strtoupper($district->name)] ?? null;
        if ($known) {
            return $known;
        }
        $gps = DB::table('espc')->where('district_id', $district->id)->whereNotNull('gps_lat')->whereNotNull('gps_lon')
            ->selectRaw('avg(gps_lat) as lat, avg(gps_lon) as lon')->first();

        return $gps && $gps->lat !== null ? [(float) $gps->lat, (float) $gps->lon] : self::FALLBACK;
    }

    /**
     * Position de démonstration du k-ième site d'un circuit : chaque circuit part dans sa propre direction,
     * les sites s'éloignent de 6 à 9 km par étape avec un léger écart d'angle (stable pour un même site).
     */
    private function demoPosition(array $centre, int $circuit, int $circuits, int $k, string $seed): array
    {
        $h = hexdec(substr(md5($seed), 0, 8));
        $angle = deg2rad(($circuit * 360 / max(1, $circuits)) + (($h % 50) - 25));
        $km = 6 + $k * 6 + ($h % 300) / 100;
        $lat = $centre[0] + ($km / 111.0) * cos($angle);
        $lon = $centre[1] + ($km / (111.0 * cos(deg2rad($centre[0])))) * sin($angle);

        return [$lat, $lon];
    }
}
