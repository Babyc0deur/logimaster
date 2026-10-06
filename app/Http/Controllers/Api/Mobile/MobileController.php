<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\ApiController;
use App\Domain\Mobile\SiteGeolocation;
use App\Models\Chronogramme;
use App\Models\FuelPrice;
use App\Models\LivraisonEspc;
use App\Models\PushSubscription;
use App\Models\Ravitaillement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * API de l'application mobile du convoyeur (chef de mission ou passager). Il ne voit que les sorties validées dont il fait
 * partie de l'équipe, exécute le circuit site par site, déclare le carburant pris et clôture la sortie. Tout est visible
 * immédiatement dans « Suivi des livraisons » sur le web.
 */
class MobileController extends ApiController
{
    // ------------------------------------------------------------------ sorties

    /** Sorties validées de l'équipe : de 14 jours en arrière à 60 jours en avant (le téléphone les garde pour le mode hors réseau). */
    public function sorties(Request $request)
    {
        $today = CarbonImmutable::today();
        $plans = $this->mine($request)->with(['circuit:id,nom', 'vehicle:id,immatriculation,marque,modele', 'livraisons.espc:id,nom,type', 'sortie'])
            ->whereBetween('date_prevue', [$today->subDays(14)->toDateString(), $today->addDays(60)->toDateString()])
            ->orderBy('date_prevue')->orderBy('heure_depart')->get();

        return ['data' => $plans->map(fn (Chronogramme $p) => $this->summary($p))->values(), 'today' => $today->toDateString()];
    }

    public function sortie(Request $request, string $id)
    {
        return $this->detail($this->plan($request, $id));
    }

    /**
     * Démarre le circuit : crée la sortie (kilométrage de départ = celui du véhicule, modifiable) et y rattache l'équipe.
     * `photo_compteur` : photo du compteur au départ (preuve du kilométrage). Rejouable : la photo est rattachée si elle manquait.
     */
    public function start(Request $request, string $id)
    {
        $plan = $this->plan($request, $id);
        $data = $request->validate(['km_depart' => ['nullable', 'integer', 'min:0'], 'photo_compteur' => ['nullable', 'string', 'max:6000000']]);
        $photo = isset($data['photo_compteur']) ? $this->decodePhoto($data['photo_compteur']) : null;

        if (! $plan->sortie_id) {
            abort_if(in_array($plan->statut, ['realisee', 'annulee'], true), 422, 'Cette sortie ne peut plus être démarrée.');
            abort_if(! $plan->vehicle_id, 422, 'Aucun véhicule n\'est affecté à cette sortie : contactez le bureau du district.');
            $sortie = $plan->demarrer();
            $sortie->update($this->filled(['km_depart' => $data['km_depart'] ?? null, 'chef_mission_id' => $this->chefMissionId($plan)]));
            $sortie->passagers()->syncWithoutDetaching($plan->personnels()->where('fonction', 'passager')->pluck('personnels.id')->all());
        }
        $sortie = $plan->fresh('sortie')->sortie;
        if ($photo && $sortie && ! $sortie->photo_km_depart) {
            $sortie->forceFill(['photo_km_depart' => $this->storePhoto($photo, 'compteurs')])->saveQuietly();
        }

        return $this->detail($plan->fresh());
    }

    /** Termine la sortie (kilométrage d'arrivée). Les sites non traités restent « à traiter » : rien n'est marqué livré à sa place. */
    public function finish(Request $request, string $id)
    {
        $plan = $this->plan($request, $id);
        $sortie = $plan->sortie;
        abort_if(! $sortie, 409, 'Démarrez d\'abord le circuit.');
        $data = $request->validate(['km_arrivee' => ['required', 'integer', 'min:0'], 'photo_compteur' => ['nullable', 'string', 'max:6000000']]);
        abort_if($data['km_arrivee'] < (int) $sortie->km_depart, 422, 'Le kilométrage d\'arrivée est inférieur au kilométrage de départ ('.(int) $sortie->km_depart.' km).');
        $photo = isset($data['photo_compteur']) ? $this->decodePhoto($data['photo_compteur']) : null;

        if ($sortie->statut === 'en_cours') {
            $sortie->skipSiteDelivery = true;
            $sortie->update(['km_arrivee' => $data['km_arrivee']]);
        }
        if ($photo && ! $sortie->photo_km_arrivee) {   // photo du compteur au retour (rattachée aussi si l'envoi est rejoué)
            $sortie->forceFill(['photo_km_arrivee' => $this->storePhoto($photo, 'compteurs')])->saveQuietly();
        }

        return $this->detail($plan->fresh());
    }

    // ------------------------------------------------------------------ livraisons

    /** Statut d'un site : livre | transit | non_livre | planifie. « transit » = livré en point de transit. Rejouable (idempotent). */
    public function livraison(Request $request, string $id)
    {
        $livraison = LivraisonEspc::with('chronogramme.personnels:id')->findOrFail($id);
        $plan = $this->plan($request, $livraison->chronogramme_id);
        abort_if(! $plan->sortie_id, 409, 'Démarrez d\'abord le circuit.');

        $data = $request->validate([
            'statut' => ['required', Rule::in(['livre', 'transit', 'non_livre', 'planifie'])],
            'raison' => ['nullable', 'string', 'max:500'],
            'commentaire' => ['nullable', 'string', 'max:500'],
            'done_at' => ['nullable', 'date', 'before_or_equal:now +1 day'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
            'precision' => ['nullable', 'numeric', 'min:0', 'max:100000'],    // précision du GPS du téléphone, en mètres
            'receptionnaire' => ['nullable', 'string', 'max:120'],            // preuve de livraison : qui a reçu
            'colis' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'preuve_photo' => ['nullable', 'string', 'max:6000000'],         // photo du bon de livraison signé (data URL)
        ]);
        $photo = isset($data['preuve_photo']) ? $this->decodePhoto($data['preuve_photo']) : null;
        $when = isset($data['done_at']) ? CarbonImmutable::parse($data['done_at']) : CarbonImmutable::now();

        $update = match ($data['statut']) {
            'livre', 'transit' => [
                'statut' => 'livre', 'date_livraison' => $when->toDateString(), 'lieu_livraison' => $data['statut'] === 'transit' ? 'transit' : 'site',
                'raison_non_livraison' => $data['commentaire'] ?? null,
            ],
            'non_livre' => [
                'statut' => 'non_livre', 'date_livraison' => null, 'lieu_livraison' => null,
                'raison_non_livraison' => $data['raison'] ?? abort(422, 'La raison de la non-livraison est obligatoire.'),
            ],
            default => ['statut' => 'planifie', 'date_livraison' => null, 'lieu_livraison' => null, 'raison_non_livraison' => null],
        };
        $delivered = in_array($data['statut'], ['livre', 'transit'], true);
        $livraison->update($update + [
            'lat' => $data['lat'] ?? null, 'lon' => $data['lon'] ?? null,
            'saisi_par' => $request->user()->getKey(), 'saisi_at' => $when,
            'receptionnaire' => $delivered ? (trim((string) ($data['receptionnaire'] ?? '')) ?: null) : null,
            'colis' => $delivered ? ($data['colis'] ?? null) : null,
        ] + ($delivered ? [] : ['preuve_photo' => null]));
        if ($delivered && $photo) {
            $livraison->forceFill(['preuve_photo' => $this->storePhoto($photo, 'preuves')])->saveQuietly();
        }
        app(SiteGeolocation::class)->record($livraison->fresh('espc'), $data['lat'] ?? null, $data['lon'] ?? null, isset($data['precision']) ? (float) $data['precision'] : null);

        return $this->detail($plan->fresh());
    }

    // ------------------------------------------------------------------ carburant

    /** Carburant pris pendant la sortie. `client_ref` (généré par le téléphone) évite les doublons si l'envoi est rejoué. */
    public function ravitaillement(Request $request, string $id): JsonResponse
    {
        $plan = $this->plan($request, $id);
        abort_if(! $plan->sortie_id, 409, 'Démarrez d\'abord le circuit.');
        $data = $request->validate([
            'client_ref' => ['required', 'string', 'max:64'],
            'litres' => ['required', 'numeric', 'min:0.5', 'max:500'],
            'prix_unitaire' => ['nullable', 'numeric', 'min:1', 'max:5000'],
            'km_compteur' => ['nullable', 'integer', 'min:0'],
            'station' => ['nullable', 'string', 'max:120'],
            'numero_facture' => ['nullable', 'string', 'max:60'],
            'done_at' => ['nullable', 'date', 'before_or_equal:now +1 day'],
            'facture_photo' => ['nullable', 'string', 'max:6000000'],   // photo de la facture : image encodée en base64 (data URL)
        ]);
        $photo = isset($data['facture_photo']) ? $this->decodePhoto($data['facture_photo']) : null;

        if ($existing = Ravitaillement::where('client_ref', $data['client_ref'])->first()) {
            abort_unless($existing->sortie_id === $plan->sortie_id, 409, 'Référence déjà utilisée.');
            // envoi rejoué : la photo manquante est rattachée au plein déjà enregistré
            if ($photo && ! $existing->facture_path) {
                $existing->update(['facture_path' => $this->storePhoto($photo)]);
            }

            return response()->json($this->fuel($existing), 200);
        }

        $vehicle = $plan->vehicle;
        $price = $data['prix_unitaire'] ?? FuelPrice::current($vehicle->type_carburant ?? 'diesel');
        abort_if(! $price, 422, 'Indiquez le prix du litre.');

        $fuel = Ravitaillement::create([
            'district_id' => $plan->district_id, 'vehicle_id' => $plan->vehicle_id, 'sortie_id' => $plan->sortie_id, 'driver_id' => $plan->driver_id,
            'litres' => $data['litres'], 'prix_unitaire' => $price, 'km_compteur' => $data['km_compteur'] ?? null,
            'station' => $data['station'] ?? null, 'numero_facture' => $data['numero_facture'] ?? null, 'motif' => $plan->motif,
            'date_ravitaillement' => isset($data['done_at']) ? CarbonImmutable::parse($data['done_at'])->toDateString() : today()->toDateString(),
            'client_ref' => $data['client_ref'], 'saisi_par' => $request->user()->getKey(),
            'facture_path' => $photo ? $this->storePhoto($photo) : null,
        ]);

        return response()->json($this->fuel($fuel), 201);
    }

    // ------------------------------------------------------------------ notifications et appareils

    public function notifications(Request $request)
    {
        $user = $request->user();

        return [
            'unread' => $user->unreadNotifications()->count(),
            'data' => $user->notifications()->latest()->limit(30)->get()->map(fn ($n) => [
                'id' => $n->id, 'title' => $n->data['title'] ?? 'Notification', 'body' => $n->data['body'] ?? '',
                'at' => $n->created_at?->toIso8601String(), 'read' => $n->read_at !== null,
            ]),
        ];
    }

    public function readNotifications(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(null, 204);
    }

    /** Clé publique VAPID pour abonner le téléphone aux notifications. */
    public function config()
    {
        return ['vapid_public_key' => config('webpush.public_key'), 'push' => filled(config('webpush.public_key')) && filled(config('webpush.private_key'))];
    }

    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);
        PushSubscription::updateOrCreate(['endpoint_hash' => PushSubscription::hashEndpoint($data['endpoint'])], [
            'user_id' => $request->user()->getKey(), 'endpoint' => $data['endpoint'], 'p256dh' => $data['keys']['p256dh'], 'auth' => $data['keys']['auth'],
            'user_agent' => substr((string) $request->userAgent(), 0, 250),
        ]);

        return response()->json(null, 204);
    }

    public function unsubscribe(Request $request)
    {
        $request->validate(['endpoint' => ['required', 'url']]);
        $request->user()->pushSubscriptions()->where('endpoint_hash', PushSubscription::hashEndpoint($request->input('endpoint')))->delete();

        return response()->json(null, 204);
    }

    // ------------------------------------------------------------------ outils

    /** Sorties validées, non annulées, dont l'utilisateur fait partie de l'équipe ou qu'il conduit (chauffeur). */
    private function mine(Request $request): Builder
    {
        $personnelId = $request->user()->personnel_id;
        $driverId = $personnelId ? \App\Models\Personnel::whereKey($personnelId)->value('driver_id') : null;

        return Chronogramme::query()
            ->where('validation_statut', 'valide')->where('statut', '!=', 'annulee')
            ->where(fn ($q) => $q->whereHas('personnels', fn ($t) => $t->whereKey($personnelId))
                ->when($driverId, fn ($q) => $q->orWhere('driver_id', $driverId)));
    }

    private function plan(Request $request, string $id): Chronogramme
    {
        return $this->mine($request)->with(['circuit:id,nom,point_depart', 'vehicle', 'driver:id,nom_complet', 'district:id,name', 'personnels', 'livraisons.espc', 'sortie'])->findOrFail($id);
    }

    private function chefMissionId(Chronogramme $plan): ?string
    {
        return $plan->personnels->firstWhere('fonction', 'chef_mission')?->getKey();
    }

    /** @param  array<string, mixed>  $values */
    private function filled(array $values): array
    {
        return array_filter($values, fn ($v) => $v !== null);
    }

    /** @return array<string, mixed> */
    private function summary(Chronogramme $p): array
    {
        $stops = $p->livraisons;
        $treated = $stops->where('statut', '!=', 'planifie')->count();

        return [
            'id' => $p->id, 'date_prevue' => $p->date_prevue->toDateString(), 'heure_depart' => $p->heure_depart ? substr($p->heure_depart, 0, 5) : null,
            'circuit' => $p->circuit?->nom ?? ($p->destination ?: 'Sortie'), 'vehicule' => $p->vehicle?->immatriculation, 'motif' => $p->motif,
            'demarree' => (bool) $p->sortie_id, 'terminee' => $p->sortie?->statut !== null && $p->sortie->statut !== 'en_cours',
            'sites' => $stops->count(), 'traites' => $treated, 'livres' => $stops->where('statut', 'livre')->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function detail(Chronogramme $p): array
    {
        $p->loadMissing(['circuit.espc', 'vehicle', 'driver', 'district', 'personnels', 'livraisons.espc', 'sortie']);
        $distances = $p->circuit?->espc->mapWithKeys(fn ($e) => [$e->id => $e->pivot->distance_km !== null ? (float) $e->pivot->distance_km : null]) ?? collect();
        $stops = $p->livraisons->sortBy('ordre')->values()->map(fn (LivraisonEspc $l) => [
            'id' => $l->id, 'ordre' => $l->ordre, 'espc' => $l->espc?->nom, 'type' => $l->espc?->type,
            'etat' => match (true) {
                $l->statut === 'livre' && $l->lieu_livraison === 'transit' => 'transit',
                default => $l->statut,
            },
            'date_livraison' => $l->date_livraison?->toDateString(), 'raison' => $l->raison_non_livraison, 'distance_km' => $distances->get($l->espc_id),
            'saisi_at' => $l->saisi_at?->toIso8601String(),
            'receptionnaire' => $l->receptionnaire, 'colis' => $l->colis, 'preuve' => (bool) $l->preuve_photo,
        ]);
        $sortie = $p->sortie;

        return $this->summary($p) + [
            'depart' => $p->circuit?->point_depart ?: $p->district?->name,
            'district' => $p->district?->name,
            'vehicule_detail' => trim(($p->vehicle?->marque ?? '').' '.($p->vehicle?->modele ?? '')) ?: null,
            'chauffeur' => $p->driver?->nom_complet,
            'equipe' => $p->personnels->map(fn ($x) => ['nom' => $x->nom_complet, 'fonction' => $x->fonction])->values(),
            'sortie' => $sortie ? ['id' => $sortie->id, 'statut' => $sortie->statut, 'km_depart' => $sortie->km_depart, 'km_arrivee' => $sortie->km_arrivee,
                'photo_depart' => (bool) $sortie->photo_km_depart, 'photo_arrivee' => (bool) $sortie->photo_km_arrivee] : null,
            'stops' => $stops,
            'restants' => $stops->where('etat', 'planifie')->count(),
            'ravitaillements' => $sortie ? Ravitaillement::where('sortie_id', $sortie->id)->latest('date_ravitaillement')->get()->map(fn ($r) => $this->fuel($r))->values() : [],
        ];
    }

    /** @return array<string, mixed> */
    private function fuel(Ravitaillement $r): array
    {
        return [
            'id' => $r->id, 'client_ref' => $r->client_ref, 'litres' => (float) $r->litres, 'prix_unitaire' => (float) $r->prix_unitaire,
            'montant' => round((float) $r->litres * (float) $r->prix_unitaire), 'km_compteur' => $r->km_compteur, 'station' => $r->station,
            'date' => $r->date_ravitaillement?->toDateString(), 'anomalie' => $r->anomalie, 'facture' => (bool) $r->facture_path,
        ];
    }

    /**
     * Décode la photo envoyée par le téléphone. Le type est contrôlé sur le contenu (pas sur ce que déclare l'appareil) :
     * seules les images JPEG, PNG et WebP de 4 Mo maximum sont acceptées.
     *
     * @return array{0: string, 1: string} [contenu, extension]
     */
    protected function decodePhoto(string $dataUrl): array
    {
        abort_unless(preg_match('#^data:image/(?:jpeg|png|webp);base64,([A-Za-z0-9+/=\s]+)$#', $dataUrl, $m), 422, 'Photo illisible : envoyez une image JPEG, PNG ou WebP.');
        $binary = base64_decode(preg_replace('/\s+/', '', $m[1]), true);
        abort_if($binary === false || $binary === '', 422, 'Photo illisible.');
        abort_if(strlen($binary) > 4 * 1024 * 1024, 422, 'Photo trop lourde (4 Mo maximum).');

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
        abort_unless($ext, 422, 'Le fichier envoyé n\'est pas une image JPEG, PNG ou WebP.');

        return [$binary, $ext];
    }

    /** Enregistre la photo sur le disque privé : factures/ (carburant), compteurs/ (kilométrage), preuves/ (bons de livraison). */
    protected function storePhoto(array $photo, string $folder = 'factures'): string
    {
        $path = $folder.'/'.Str::uuid().'.'.$photo[1];
        Storage::disk('local')->put($path, $photo[0]);

        return $path;
    }
}
