# Logimaster — Gestion de flotte DDKM

Backend **Laravel 13** + back-office **Filament 4** (français), API REST (**Sanctum**), indicateurs DDKM pré-calculés,
import/export du classeur Excel Logimaster des districts.

## Installation

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed   # attention : réinitialise la base
php -d memory_limit=2G vendor/bin/phpunit   # `php artisan test` dépasse la limite mémoire par défaut (128 Mo)
php artisan serve --port=8085      # puis http://localhost:8085/admin
```

Le seeder charge l'organisation réelle (**10 PRES → 33 régions → 113 districts**, codes du classeur Logimaster, fichier
`database/data/organisation_ci.json`) et un jeu de démonstration dans le district **ANYAMA**.

Comptes de démonstration (mot de passe `password`, à changer hors local) :

| Rôle | Email | Périmètre |
|---|---|---|
| `pres_admin` | pres@logimaster.test | National, tous les droits |
| `region_manager` | region@logimaster.test | Districts de la région Abidjan 1 (rattachement par `users.region_id`) |
| `district_manager` | district@logimaster.test | Anyama (CRUD, soumission des plannings) |
| `superviseur_bailleur` | bailleur@logimaster.test | 9 districts d'Abidjan ; validation des plannings et des factures |

Le sélecteur de district (au-dessus du menu) et les filtres du tableau de bord ne proposent que les districts
du périmètre de l'utilisateur.

## Modules

- **Véhicules** : fiche complète (caractéristiques, affectation, bailleur, documents avec échéances), formulaire en 4 étapes,
  onglets historique / maintenance / carburant / statistiques, exports Excel & PDF.
- **Chronogramme** : planning des sorties (circuit + date + sites), vue semaine (véhicules × jours) et vue mois, couleurs par
  motif, **glisser-déposer** pour reprogrammer (refus du passé, des doubles réservations et des plans verrouillés), clic sur
  un jour pour créer, génération selon la fréquence des circuits, duplication, démarrage de la sortie.
  **Validation par un superviseur** : le district soumet le mois, le superviseur (`validate_chronogrammes`) valide
  (verrouillage de la date, du véhicule, du chauffeur, du circuit et de l'horaire), refuse avec motif ou lève la validation ;
  notifications in-app. API : `POST /api/chronogrammes/{submit,validate,refuse,reopen}`.
- **Sorties** : formulaire complet (équipage, étapes, carburant, autres frais), coûts et alerte de surconsommation,
  statuts Planifiée → En cours → Terminée → Validée, validation en masse, duplication.
- **Carburant** : ravitaillements (facture, validation, détection d'anomalies), tableau de bord, analyse réel vs théorique,
  prix du carburant avec historique, seuils d'alerte.
- **Maintenance** : vidanges (prochaine échéance et date estimées), immobilisations (durée, impact, statistiques),
  calendrier mensuel, alertes 🔴/🟠/🟢, notifications in-app (`php artisan fleet:alerts`, planifié à 07:00).
- **Indicateurs DDKM** : **8 indicateurs** sur deux lignes (coût global, utilisation des véhicules, taux d'immobilisation, respect du
  chronogramme, utilisation rationnelle du carburant, carburant par motif, respect des circuits, distance totale ; la livraison
  ESPC n'est plus calculée). Snapshots (`php artisan indicators:compute`, planifié 01:00 + clôture mensuelle ; `indicators:backfill`
  pour les mois passés), période libre calculée à la volée, « Non évalué » pour l'utilisation rationnelle du carburant sans
  consommation théorique. Écran de détail par indicateur (tendance, historique, répartition par véhicule/site), cartes des 8
  indicateurs et donut de l'état de la flotte sur le tableau de bord.
- **Circuits et ESPC** : étapes ordonnées avec distances, point de départ, carte (Leaflet / OpenStreetMap), fiche ESPC
  (adresse, e-mail, GPS). **Saisonnalité** : chaque circuit est valable *toute l'année*, en *saison sèche* ou en *saison pluvieuse* (colonne, filtre,
  import/export, API) ; c'est une information : au planning, l'utilisateur choisit librement le circuit et la date
  (un simple rappel s'affiche si la saison du circuit ne correspond pas à la date). Mois secs réglables : `LOGIMASTER_DRY_SEASON_MONTHS` (défaut `11,12,1,2,3`). **Suivi des livraisons** (un seul menu) : vue circuit — chaque sortie du jour se lit comme une ligne d'étapes (départ puis sites, avec statut, distances, retards, raisons, indicateurs et actions rapides livrée / non livrée) ; vue tableau (bouton « Vue tableau ») : statut par site (planifiée / livrée / non livrée),
  date, lieu (site ou transit), raison ; clôturer une sortie marque les sites livrés. API : `/api/livraisons`.
- **Personnel** : fiche chauffeur complète (permis, véhicule principal, statistiques), chefs de mission et passagers.
- **Finance** : budgets par mois, par poste (carburant / maintenance / autres) et par bailleur, tableau de bord avec
  prévision et alertes de dépassement ; **factures** avec workflow brouillon → à valider → validée / rejetée → payée →
  archivée (séparation des tâches : le créateur ne valide pas sa propre facture).
- **Rapports** : PDF et Excel (DDKM, flotte, carburant, maintenance, financier, bailleur), envoi par e-mail, envois
  planifiés (`php artisan reports:send`, 06:00 ; `--monthly` le 1er du mois à 03:30).

- **Cartographie** (menu, sous le chronogramme ; anciennement « Suivi de la saisie ») : carte de la Côte d'Ivoire avec les **limites
  officielles des 113 districts sanitaires** (`public/geo/ci-districts.json`, issu du fichier du ministère) et les limites des
  **33 régions** (traits orange). Chaque district est vert (au moins une sortie saisie, hors annulées) ou rouge, pour un mois ou
  l'année ; le point blanc est son chef-lieu. Filtres année, mois et région ; **taux de remplissage** = mois renseignés / mois
  écoulés (carte, carte de chiffres et colonne « Taux » du tableau des 12 mois par district).
- **Suivi en temps réel** (ancienne « Cartographie ») : simulation, sur OpenStreetMap, du déplacement des véhicules du district
  choisi dans le menu, chacun sur son circuit (départ du chef-lieu à 07:30, 40 km/h, 10 min par site, itinéraires OSRM public
  de démonstration). Limite de la région et districts voisins en traits fins, district teinté ; un clic sur un véhicule cadre son
  parcours. Sites sans GPS : positions de démonstration autour du chef-lieu.
- **Paramètres** (dernier groupe du menu) : centres de santé (ESPC), chefs de mission et passagers, districts.
- **Administration** (permission `manage_settings`) : seuils d'alerte, prix du carburant, **objectifs des
  8 indicateurs** (couleurs des cartes, rapports et recommandations), **districts** (liste du périmètre, création et
  édition par le national avec `manage_districts`), **journal d'audit** en lecture seule (`view_audit_logs`).

## Page d'accueil et application mobile

- `/` : page de présentation (papier / encre / orange) : fonctionnement, **cartographie**, les 8 indicateurs, application
  terrain, suivi en temps réel, questions, avec le QR code d'installation et l'accès à l'administration (`/admin`).
- `/m` : **application convoyeur** installable (PWA). Le chef de mission ou le passager y reçoit les sorties validées de son
  équipe, exécute le circuit site par site (livré / transit / non livré), déclare le carburant et termine la sortie ; le
  bureau le voit aussitôt dans « Suivi des livraisons ». Fonctionne hors réseau (actions envoyées au retour du réseau).
- `/m/installer` : affiche à imprimer avec le QR code. Le QR pointe vers `LOGIMASTER_MOBILE_URL` (HTTPS obligatoire pour
  l'installation et les notifications ; défaut `APP_URL/m`).
- **Partager l'application hors du réseau local** : `powershell -ExecutionPolicy Bypass -File tools\start-tunnel.ps1` ouvre un
  tunnel HTTPS public (localhost.run, via le `ssh` de Windows, rien à installer), active le **mode tunnel**
  (`LOGIMASTER_TUNNEL_MODE=true` : depuis l'adresse publique, seuls l'accueil, `/m` et `/api/mobile` répondent ; `/admin` et le reste
  de l'API restent locaux, en 404 de l'extérieur) et met à jour `LOGIMASTER_MOBILE_URL` pour le QR code. L'adresse change à chaque
  lancement ; le tunnel dure tant que le PC et `php artisan serve` tournent. En production : un vrai nom de domaine HTTPS.
- **Accès automatique** : tout chef de mission ou passager actif est **convoyeur d'office** : son accès est créé avec sa
  fiche (identifiant généré, ex. `kone.ibrahim`, et code d'accès provisoire de 8 caractères), sans création manuelle. Le
  bureau lit l'identifiant et le code dans *Personnel* (action « Accès mobile ») pour les remettre ; le convoyeur choisit son
  mot de passe à la première connexion, et le code disparaît. « Réinitialiser le code » en cas de perte. L'accès suit la
  fiche : suspendu si la personne devient inactive ou change de fonction, réactivé ensuite, supprimé avec la fiche.
  `php artisan mobile:sync-access` crée les accès du personnel déjà enregistré (import, saisie antérieure).
  `LOGIMASTER_MOBILE_AUTO_ACCESS=false` désactive la création automatique. Le rôle `convoyeur` n'a aucun accès à
  l'administration ; il voit uniquement les sorties **validées** dont il est dans l'**équipe** (champ « Équipe » du chronogramme).
- **Photo de la facture de carburant** : dans l'onglet Carburant, « Prendre une photo de la facture ». L'image est réduite
  (1400 px, JPEG ≈ 100-200 Ko) avant l'envoi, gardée sur le téléphone (IndexedDB) tant qu'il n'y a pas de réseau, puis
  enregistrée sur le disque privé (`storage/app/private/factures`). Contrôle sur le contenu (JPEG, PNG ou WebP, 4 Mo max).
  Au bureau : *Ravitaillements* → « Voir la facture » (ou « Télécharger la facture »). Le serveur doit accepter des envois
  de quelques centaines de Ko : `.claude/launch.json` lance `php -S` (via `server.php`) avec `post_max_size=12M` ; avec
  `php artisan serve` ou Herd, vérifier `post_max_size` et le dossier temporaire de PHP.
- **Notifications** : à la validation du chronogramme, l'équipe est prévenue dans l'application et par notification push.
  `php artisan webpush:vapid --write` génère les clés VAPID dans `.env` (sous Windows, `WEBPUSH_OPENSSL_CONF` doit pointer
  vers un `openssl.cnf` valide). API : `/api/mobile/*` (jeton à capacité « mobile » uniquement).
- Compte de démonstration : identifiant `kone.ibrahim` (ou `convoyeur@logimaster.test`) / `password` (ANYAMA, sortie du jour validée).

## Organisation et géographie

- **Organisation** : **10 PRES** réelles, auxquelles sont rattachées les 33 régions (`app/Domain/Organisation/PresMapping.php`, commande
  `php artisan organisation:pres`, idempotente) ; districts renommés selon la liste officielle (ex. SAN-PEDRO).
- **Districts** : `php artisan districts:geo [--force]` charge les coordonnées des chefs-lieux
  (`database/data/districts_geo.json`, OpenStreetMap, replacés dans leur polygone officiel si besoin).
- Le fichier des limites est servi avec une adresse versionnée (`Brand::url()`), le navigateur reprend donc toujours le dernier tracé.

## Déploiement (Render, Docker)

`docker/start.sh` enchaîne au démarrage : migrations, restauration de la sauvegarde, installation, chargement des données,
`organisation:pres`, `districts:geo --force`, `indicators:backfill`, puis caches (config, routes, vues, événements, Filament).
SQLite est réglé pour la performance (WAL, `busy_timeout`, index composites) ; opcache est configuré dans le `Dockerfile`.
Variables à définir sur Render : `APP_NAME=Logimaster`, `CACHE_STORE=file`, `VAPID_*` (notifications push), `BACKUP_S3_*`
(sauvegardes), `SENTRY_LARAVEL_DSN` (erreurs). Un **disque persistant** est nécessaire pour conserver la base entre deux déploiements.

## Module Finance (retiré)

Budgets, factures, tableau de bord financier, rapport financier et alertes de budget sont **retirés** de l'application
(menus, API `/api/budgets`, `/api/factures`, `/api/finance/*` en 404, type de rapport « financier »). Le code et les données
sont conservés : `LOGIMASTER_FINANCE=true` dans `.env` les réactive. Les **Dépenses** (onglet « AUTRES FRAIS » des classeurs)
restent, car elles alimentent l'indicateur de coût global.

## Période par défaut

Les filtres (tableau de bord, indicateurs, finance, carburant) s'ouvrent sur **mai à octobre 2025**, la période des données
réelles chargées : réglable dans `.env` (`LOGIMASTER_PERIOD_FROM`, `LOGIMASTER_PERIOD_UNTIL` ; valeurs vides = mois en cours).
Filament mémorise en session les filtres déjà choisis : se reconnecter pour revoir les valeurs par défaut.

## Permissions

Les ressources et pages Filament appliquent les mêmes permissions que l'API : une politique par modèle (`app/Policies`,
adossée à `view_/create_/update_/delete_{module}`), les pages (tableau de bord, carburant, calendrier de maintenance…)
exigent leur permission de consultation, et les rôles ne sont accessibles qu'au national. Un rôle en lecture seule
(région, superviseur bailleur) ne voit ni les boutons ni les URL de création ou de modification.

## Import / export

- **Classeur Logimaster** (menu *Données → Import / export du classeur*, ou `POST /api/import/workbook`) : importe tel quel le
  modèle Excel des districts (`.xlsx` / `.xlsm`) — onglets *Liste des Sites, VEHICULES, CHRONOGRAMME, CIRCUITS (sorties),
  CARBURANT, AUTRES FRAIS, VIDANGES, IMMOBILISATION*. Formules et onglets de calcul ignorés. Réimportable sans doublon,
  tout ou rien (ou « ignorer les lignes en erreur »), numéros de ligne identiques à ceux d'Excel. Modèle vide et export des
  données du district au même format (sauvegarde / restauration).
- **Chargement en masse des districts** : `php artisan import:districts "<dossier>"` lit un dossier de classeurs (un fichier par
  district et par mois, sous-dossiers par mois acceptés), associe chaque fichier à son district d'après son nom, garde le
  classeur le plus récent de chacun et importe les sites ESPC et les véhicules (`--sheets=` pour d'autres onglets, `--only=`
  pour un district, `--dry-run` pour vérifier l'association sans rien écrire). Mode tolérant : « NA », « 2800KG » ou une date
  illisible dans une colonne facultative sont ignorés au lieu de refuser la ligne. Réimportable sans doublon.
- **Activité réelle** : `php artisan import:districts "<dossier>" --activity --only=MEAGUI` importe aussi le chronogramme, les
  sorties, le carburant, les autres frais et les immobilisations, sur la période `--from` / `--until` (défaut : période par
  défaut de l'application, `config/logimaster.php`, mai à octobre 2025), puis recalcule les indicateurs de chaque mois. Mode
  tolérant : dates saisies en période (« 11/06/2025 AU 23/06/2025 » → début retenu), plaques incrémentées par Excel
  (D55142, D55143… rattachées à D55141), noms de personnes en majuscules. `--report=fichier.txt` écrit toutes les lignes
  ignorées et corrections pour vérification. Source des classeurs : dossier « Version 300925 Validé » (PRES > région).
- **Circuits et harmonisation** : `php artisan import:districts "<dossier>" --circuits` importe les circuits (nom et sites dans
  l'ordre, onglet CHRONOGRAMME) sans doublon ni sortie planifiée, en écartant les motifs saisis comme circuits (PNLP, garage…).
  `php artisan data:harmonize` (`--dry-run` pour simuler) met les données au format Logimaster : marques et modèles (fautes
  corrigées), bailleurs (« UCP FM », « FOND MONDIAL » → FONDS MONDIAL), noms et types d'ESPC (CSR, CSU, HG…), circuits
  (« C1 », « CIR1 », « 1 » → CIRCUIT 1) ; fusionne les ESPC et circuits en double. Les imports appliquent les mêmes règles.
- **Par entité** (menu *Import / Export* des listes Véhicules, Chauffeurs, Circuits, ESPC, Chronogramme ; API
  `/api/import/{entity}`) : modèle `.xlsx` avec onglets Modèle / Exemple / Aide, import, export au format d'import.
  Circuits : point de départ, GPS du départ et distances par étape (`distances_etapes`, ex. `12; 7,5`) ; ESPC : adresse, e-mail.
  Le classeur Logimaster complet ne lit pas encore ces colonnes supplémentaires.

## API (`/api`)

`POST /api/auth/login` → token Bearer. Routes cloisonnées par district et protégées par permissions. Liste complète :
`php artisan route:list --path=api`.

## Reste à faire

2FA admin et verrouillage de compte, bibliothèque de documents, PostgreSQL ; GPS des centres de santé (pour des positions
réelles dans le suivi en temps réel) ; service d'itinéraires fiable à la place du serveur OSRM de démonstration ; démarrage mobile limité
au jour planifié ; dépôt GitHub à passer en privé avant les données réelles.

Hors périmètre (décision du projet) : synchronisation hors ligne (`/api/sync/*`), applications mobile et desktop,
temps réel (Reverb, Horizon, Scramble), optimisation de circuits.
