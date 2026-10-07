# Scénario de test de bout en bout — LogiMaster

Durée : environ 45 minutes. Un ordinateur (administration) et un téléphone (application convoyeur).
Chaque étape donne l'action puis le résultat attendu (✔). Cochez ou notez l'écart.

## 0. Préparation

| Élément | Valeur |
|---|---|
| Administration | `http://127.0.0.1:8085/admin` |
| Accueil | `http://127.0.0.1:8085/` |
| Application mobile | `/m` (adresse du tunnel + `/m` depuis le téléphone) |
| Comptes de démo (mot de passe `password`) | `pres@logimaster.test` (national), `region@logimaster.test`, `district@logimaster.test`, `bailleur@logimaster.test`, `convoyeur@logimaster.test` |
| Convoyeur ANYAMA | `kone.ibrahim` / `password` |
| Convoyeurs MEAGUI | ex. `soro.tenena` / code provisoire (voir la fiche Chefs de mission & passagers) |

1. Lancer l'application (« lance l'app ») et le tunnel (`tools/start-tunnel.ps1 -Watch`) ; noter l'adresse affichée.
2. Sur le téléphone, mettre le Wi-Fi/données actifs (l'étape 7 coupe le réseau).

## 1. Page d'accueil

1. Ouvrir `/`. ✔ Le titre apparaît mot par mot ; la scène tourne en boucle : toast « Chronogramme validé », camion qui avance, 4 sites cochés (le 3ᵉ en orange « transit »), compteurs Sites livrés / Distance qui montent, message « Sortie terminée ».
2. Défiler. ✔ L'en-tête devient sombre et flou ; la ligne du parcours se remplit et les 4 étapes s'allument ; les cartes apparaissent ; les anneaux d'indicateurs se remplissent ; les compteurs s'animent.
3. Section mobile : ✔ le téléphone change d'écran toutes les 3 s, l'onglet du bas suit.
4. Ouvrir/fermer la FAQ. ✔ Une seule réponse ouverte à la fois n'est pas imposée ; l'animation de la flèche fonctionne.
5. Réduire la fenêtre à la largeur d'un téléphone. ✔ Pas de défilement horizontal, scène et boutons lisibles.
6. Via l'adresse du tunnel : ✔ aucun lien « Administration » ; `/admin` renvoie 404.
7. Préférence système « réduire les animations » : ✔ image finale fixe de la scène.

## 2. Installation et connexion mobile

1. Scanner le QR code de la page (ou ouvrir l'adresse). ✔ Écran de connexion LogiMaster.
2. Installer : Android « Installer l'application » ; iPhone Safari → Partager → « Sur l'écran d'accueil ». ✔ Icône sur l'écran d'accueil, ouverture plein écran.
3. `/m/installer` ✔ affiche imprimable avec le QR.
4. Se connecter avec un mauvais mot de passe. ✔ Message d'erreur, pas d'accès.
5. Se connecter avec un compte à code provisoire (ex. `soro.tenena`). ✔ Obligation de choisir un nouveau mot de passe ; ensuite le code provisoire ne fonctionne plus.
6. Se connecter avec `kone.ibrahim` / `password`. ✔ Accueil avec la sortie du jour.
7. Accepter les notifications. ✔ Profil : « Notifications activées ».

## 3. Création des accès (automatique)

1. Admin → **Chefs de mission & passagers** → créer une personne avec rôle chef de mission. ✔ Identifiant et code générés sans création de compte manuelle.
2. Action « Accès mobile » sur la ligne. ✔ Identifiant + code provisoire, bouton QR, bouton « Régénérer le code ».
3. Désactiver la personne. ✔ Connexion mobile refusée.
4. Supprimer puis recréer la même personne. ✔ Aucun doublon d'e-mail (pas d'erreur).

## 4. Planification et validation (bureau)

1. Se connecter en `district@logimaster.test`, choisir le district MEAGUI (ou ANYAMA), menu **Chronogramme**. ✔ Période par défaut mai–oct. 2025 (filtre) ; calendrier large.
2. Créer une sortie : circuit, véhicule, date, **équipe** (chef + passagers). ✔ Enregistrée en « à valider ».
3. Glisser-déposer la sortie sur un autre jour. ✔ La date change ; un conflit (même véhicule, même jour) est signalé.
4. Se connecter en `bailleur@logimaster.test` (superviseur). ✔ Bouton **Valider** disponible ; le district ne peut pas valider lui-même.
5. Valider la sortie. ✔ Verrouillée (non modifiable) ; statut « validé ».
6. ✔ Sur le téléphone de l'équipe : notification push « Chronogramme validé » (toucher ouvre l'application) et sortie visible à l'accueil + onglet Alertes.
7. Membre hors équipe : ✔ ne voit pas cette sortie.

## 5. Exécution du circuit (téléphone)

1. Accueil → **Démarrer le circuit**. ✔ Statut « en cours » ; liste des sites dans l'ordre.
2. Site 1 → **Livré**. ✔ Coché vert, barre de progression, site suivant mis en avant.
3. Site 2 → **En transit**. ✔ Orange, heure enregistrée.
4. Site 3 → **Non livré** avec un motif obligatoire. ✔ Refus si motif vide.
5. Onglet **Carburant** : litres, prix, station, **photo de la facture** (prise caméra ou galerie). ✔ Aperçu de la photo, confirmation d'envoi.
6. **Terminer la sortie** quand tous les sites sont traités. ✔ Bouton refusé tant qu'un site reste en attente ; puis « Sortie terminée ».

## 6. Vérification côté web (temps réel)

1. Admin → **Suivi des livraisons** (page unique), laisser ouverte pendant l'étape 5. ✔ Les statuts se mettent à jour seuls (sondage automatique), avec qui / quand / quoi.
2. **Carburant / Ravitaillements** ✔ le plein apparaît, lié à la sortie, avec le lien vers l'image de la facture (fenêtre d'aperçu).
3. **Livraisons ESPC** ✔ sites livrés présents ; « non livré » avec son motif.
4. Tableau de bord ✔ les indicateurs bougent (livraison, respect du chronogramme, carburant) ; filtre par défaut mai–oct. 2025 ; filtre district/région/PRES fonctionnel.

## 7. Hors réseau

1. Sur le téléphone, couper données + Wi-Fi (mode avion).
2. Marquer un site livré, saisir un plein avec photo. ✔ Pas d'erreur ; compteur « actions en attente » > 0 dans Profil.
3. Réactiver le réseau. ✔ Synchronisation automatique, compteur à 0 ; sur le web, l'heure affichée est l'heure réelle de l'action (pas celle de la synchro).
4. Rejouer la synchronisation (rouvrir l'app). ✔ Aucun doublon.

## 8. Rôles et périmètres

| Compte | Attendu |
|---|---|
| `pres@logimaster.test` | voit tous les districts |
| `region@logimaster.test` | uniquement sa région |
| `district@logimaster.test` | uniquement son district, ne valide pas |
| `bailleur@logimaster.test` | valide, lecture seule ailleurs |
| convoyeur | aucun accès à `/admin` (refusé), mobile uniquement |

Tester aussi : menu utilisateur (en haut à droite) → Paramètres (ESPC), Chefs de mission & passagers, Districts au-dessus de Déconnexion ; groupe Administration ; absence des menus Factures/Budgets.

## 9. Pages d'erreur et sécurité

1. `/page-inexistante` ✔ page 404 personnalisée en français.
2. Via le tunnel : `/admin`, `/admin/login`, `/api/vehicles` ✔ 404.
3. Token expiré / déconnexion mobile ✔ retour à la connexion, file hors réseau conservée.

## 10. Contrôle automatique

```bash
php -d memory_limit=2G vendor/bin/phpunit
```

✔ Toute la suite passe (172+ tests).

## Fiche de résultats

| Section | OK | Écart observé |
|---|---|---|
| 1 Accueil | | |
| 2 Installation | | |
| 3 Accès | | |
| 4 Planification | | |
| 5 Exécution | | |
| 6 Suivi web | | |
| 7 Hors réseau | | |
| 8 Rôles | | |
| 9 Erreurs | | |
