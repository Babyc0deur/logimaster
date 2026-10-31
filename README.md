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
| `superviseur_bailleur` | bailleur@logimaster.test | 9 districts d'Abidjan ; validation des plannings |

Le sélecteur de district (au-dessus du menu) et les filtres du tableau de bord ne proposent que les districts
du périmètre de l'utilisateur.

## Modules

- **Véhicules** : fiche complète (caractéristiques, affectation, bailleur, documents avec échéances), formulaire en 4 étapes,
  onglets historique / vidanges / carburant / statistiques, exports Excel & PDF.
- **Chronogramme** : planning des sorties (circuit + date + sites), vue semaine (véhicules × jours) et vue mois, couleurs par
  motif, **glisser-déposer** pour reprogrammer (refus du passé, des doubles réservations et des plans verrouillés), clic sur
  un jour pour créer, génération selon la fréquence des circuits, duplication, démarrage de la sortie.
  **Validation par un superviseur** : le district soumet le mois, le superviseur (`validate_chronogrammes`) valide
  (verrouillage de la date, du véhicule, du chauffeur, du circuit et de l'horaire), refuse avec motif ou lève la validation ;
  notifications in-app. API : `POST /api/chronogrammes/{submit,validate,refuse,reopen}`.
- **Sorties** : formulaire complet (équipage, étapes, carburant, autres frais), coûts et alerte de surconsommation,
  statuts Planifiée → En cours → Terminée → Validée, validation en masse, duplication.
- **Carburant** (menu « Carburant » : tableau de bord, liste des pleins, analyse) : pleins avec facture, validation, détection d'anomalies, analyse réel vs théorique,
  prix du carburant avec historique, seuils d'alerte.
- **Vidanges** et **Immobilisations** (menu principal, après les autres frais) : vidanges (prochaine échéance et date estimées), immobilisations (durée, impact, statistiques),
  alertes 🔴/🟠/🟢 (tableau de bord), notifications in-app (`php artisan fleet:alerts`, planifié à 07:00).
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
- **Saisie d'une tournée** (menu, sous le suivi des livraisons ; lien « Saisir toute la tournée » sur chaque circuit du suivi) :
  une seule page pour recopier le carnet de bord d'une sortie. On y renseigne le compteur de départ, le statut et le kilométrage de
  chaque site (livré, en transit, non livré avec sa raison), le compteur d'arrivée (qui termine la sortie), un plein de carburant
  (prix du véhicule prérempli, confirmation s'il est modifié) et des autres frais. Règles de contrôle : le
  kilométrage ne recule jamais, la raison est obligatoire pour un site non livré, les erreurs s'affichent sur le bon champ et rien n'est
  enregistré en cas de refus. La saisie peut être refaite pour corriger ; le plein et les frais sont des ajouts (les enregistrés sont listés dessous).
- **Autres frais** (menu ; ex-« Dépenses ») : péages, collation, chargement, déchargement, hébergement, etc. Le type **« Enlèvement ESPC »**
  enregistre l'indemnité de déplacement versée à un centre de santé qui vient enlever ses produits au district : l'ESPC est obligatoire,
  le bénéficiaire se préremplit, et le frais est compté dans le **coût global** (autres frais, avec une ligne « dont enlèvements par les
  ESPC » dans le détail de l'indicateur). La fiche de l'ESPC affiche le total reçu pour ses enlèvements.
- **Enlèvement** (menu principal, après la saisie d'une tournée) : la même chose en un écran dédié. Liste des enlèvements par les ESPC (date, centre de
  santé, indemnité, bénéficiaire, total de la période), filtres par centre de santé et par période, et « Enregistrer un enlèvement ». Chaque
  enlèvement est un frais de type « Enlèvement ESPC » : il apparaît aussi dans « Autres frais » et dans le coût global.
- **Mutualisation** (menu principal, après les véhicules) : un district met un véhicule à la disposition d'un autre pour une période (avec ou sans date
  de fin). Le district **propriétaire** crée, modifie ou termine la mise à disposition. Le district **utilisateur** la voit, et la page
  **« Activité du véhicule »** (lien « Activité » de la liste et de la liste des véhicules) montre aux deux districts ce que le véhicule fait :
  sorties, kilomètres, carburant, autres frais, vidanges et immobilisations, avec un résumé par district. Le propriétaire voit toute
  l'activité ; l'utilisateur la voit pendant ses périodes seulement, et jamais les autres véhicules ni données du propriétaire. Chaque
  sortie, plein ou frais reste rattaché au district qui l'a saisi (ses coûts, ses indicateurs). Étape 1 : mise à disposition et visibilité ;
  la planification de sorties par le district utilisateur et la détection des conflits entre districts viennent ensuite.
- **Personnel** : fiche chauffeur complète (permis, véhicule principal, statistiques), chefs de mission et passagers.
- **Rapports** : PDF et Excel (DDKM, flotte, carburant, maintenance, par bailleur), envoi par e-mail, envois
  planifiés (`php artisan reports:send`, 06:00 ; `--monthly` le 1er du mois à 03:30).

- **Cartographie** (menu, sous le chronogramme ; anciennement « Suivi de la saisie ») : carte de la Côte d'Ivoire avec les **limites
  officielles des 113 districts sanitaires** (`public/geo/ci-districts.json`, issu du fichier du ministère) et les limites des
  **33 régions** (traits orange). Chaque district est vert (au moins une sortie saisie, hors annulées) ou rouge, pour un mois ou
  l'année ; le point blanc est son chef-lieu. Filtres année, mois et région ; **taux de remplissage** = mois renseignés / mois
  écoulés (carte, carte de chiffres et colonne « Taux » du tableau des 12 mois par district).
- **Suivi en temps réel** : **désactivé** (ni menu, ni page). La simulation du déplacement des véhicules sur OpenStreetMap
  est conservée dans le code ; `LOGIMASTER_LIVE_TRACKING=true` dans `.env` la réactive.
- **Paramètres** (dernier groupe du menu) : centres de santé (ESPC), personnel (chauffeurs, chefs de mission, passagers), districts.
- **Administration** (permission `manage_settings`) : seuils d'alerte, prix du carburant, **objectifs des
  8 indicateurs** (couleurs des cartes, rapports et recommandations), **districts** (liste du périmètre, création et
  édition par le national avec `manage_districts`), **journal d'audit** en lecture seule (`view_audit_logs`).

## Page d'accueil

- `/` : page de présentation (papier / encre / orange) : fonctionnement, **cartographie**, les 8 indicateurs, questions, avec l'accès à
  l'administration (`/admin`). Il n'y a plus d'application mobile (`/m`, `/api/mobile`), ni notifications push, ni mode tunnel, ni
  comptes de convoyeurs : tout se saisit dans l'administration (menu, ou page « Saisie d'une tournée »). Les chefs de mission et
  passagers restent dans *Personnel* comme équipage d'une sortie, sans compte.
- **Photo de la facture de carburant** : dans *Carburant* (liste des pleins), champ « Facture » (PDF, JPEG ou PNG, 10 Mo max),
  enregistrée sur le disque privé (`storage/app/private/factures`). Au bureau : « Voir la facture » (ou « Télécharger la facture »).

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
Variables à définir sur Render : `APP_NAME=Logimaster`, `CACHE_STORE=file`, `BACKUP_S3_*`
(sauvegardes), `SENTRY_LARAVEL_DSN` (erreurs). Un **disque persistant** est nécessaire pour conserver la base entre deux déploiements.

## Finance (supprimé)

Budgets, factures, tableau de bord financier, rapport financier, alertes de budget et leurs permissions sont **supprimés** (code, API
et tables, par migration). Les **Autres frais** (onglet « AUTRES FRAIS » des classeurs, ex-« Dépenses ») restent, car elles alimentent l'indicateur de coût
global ; le rapport « par bailleur » reste aussi (coûts par bailleur et par véhicule, sans budgets). L'étiquette Git `avant-retrait-mobile`
précède ces retraits (`git tag`).

## Période par défaut

Les filtres (tableau de bord, indicateurs, carburant) s'ouvrent sur **mai à octobre 2025**, la période des données
réelles chargées : réglable dans `.env` (`LOGIMASTER_PERIOD_FROM`, `LOGIMASTER_PERIOD_UNTIL` ; valeurs vides = mois en cours).
Filament mémorise en session les filtres déjà choisis : se reconnecter pour revoir les valeurs par défaut.

## Permissions

Les ressources et pages Filament appliquent les mêmes permissions que l'API : une politique par modèle (`app/Policies`,
adossée à `view_/create_/update_/delete_{module}`), les pages (tableau de bord, carburant, saisie d'une tournée…)
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

Verrouillage de compte, bibliothèque de documents, PostgreSQL ; service d'itinéraires fiable à la place du serveur OSRM de démonstration
(si le suivi en temps réel est réactivé).

Hors périmètre (décision du projet) : synchronisation hors ligne (`/api/sync/*`), applications mobile et desktop,
temps réel (Reverb, Horizon, Scramble), optimisation de circuits, module Finance.
