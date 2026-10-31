# Scénario de test de bout en bout — Logimaster

Durée : environ 45 minutes. Un ordinateur (administration) et un téléphone (application mobile du bureau).
Chaque étape donne l'action puis le résultat attendu (✔). Cochez ou notez l'écart.

## 0. Préparation

| Élément | Valeur |
|---|---|
| Administration | `http://127.0.0.1:8085/admin` |
| Accueil | `http://127.0.0.1:8085/` |
| Application mobile | `/m` (adresse publique HTTPS + `/m` depuis le téléphone) |
| Comptes de démo (mot de passe `password`) | `pres@logimaster.test` (national), `region@logimaster.test`, `district@logimaster.test`, `bailleur@logimaster.test` |
| Compte pour l'application mobile | `district@logimaster.test` (gestionnaire d'ANYAMA) ou `pres@logimaster.test` (national : choix du district) |

1. Lancer l'application (« lance l'app ») ; noter l'adresse affichée.
2. Sur le téléphone, mettre le Wi-Fi/données actifs (l'étape 7 coupe le réseau).

## 1. Page d'accueil

1. Ouvrir `/`. ✔ Le titre apparaît mot par mot ; la scène tourne en boucle : toast « Chronogramme validé », camion qui avance, 4 sites cochés (le 3ᵉ en orange « transit »), compteurs Sites livrés / Distance qui montent, message « Sortie terminée ».
2. Défiler. ✔ L'en-tête devient sombre et flou ; la ligne du parcours se remplit et les 4 étapes s'allument ; les cartes apparaissent ; les anneaux d'indicateurs se remplissent ; les compteurs s'animent.
3. Section mobile : ✔ le téléphone change d'écran toutes les 3 s, l'onglet du bas suit.
4. Ouvrir/fermer la FAQ. ✔ Une seule réponse ouverte à la fois n'est pas imposée ; l'animation de la flèche fonctionne.
5. Réduire la fenêtre à la largeur d'un téléphone. ✔ Pas de défilement horizontal, scène et boutons lisibles.
6. Préférence système « réduire les animations » : ✔ image finale fixe de la scène.

## 2. Installation et connexion mobile

1. Scanner le QR code de la page (ou ouvrir l'adresse). ✔ Écran de connexion Logimaster.
2. Installer : Android « Installer l'application » ; iPhone Safari → Partager → « Sur l'écran d'accueil ». ✔ Icône sur l'écran d'accueil, ouverture plein écran.
3. `/m/installer` ✔ affiche imprimable avec le QR.
4. Se connecter avec un mauvais mot de passe. ✔ Message d'erreur, pas d'accès.
5. Se connecter avec `district@logimaster.test` / `password`. ✔ Accueil avec la sortie du jour du district ; aucune demande de position ni de notification.
6. Se connecter avec `pres@logimaster.test`. ✔ Un sélecteur propose les 113 districts ; changer de district recharge les sorties, le planning et les véhicules.
7. Se connecter avec `bailleur@logimaster.test` (lecture seule). ✔ Refus : ce compte n'a pas la permission d'exécuter les circuits.

## 3. Comptes du bureau

1. Admin → menu utilisateur → **Utilisateurs** → créer un compte avec le rôle « Gestionnaire de district » et rattacher un district. ✔ Le compte se connecte à l'administration **et** à l'application mobile avec la même adresse e-mail.
2. Créer une personne dans **Personnel** avec la fonction chef de mission. ✔ Aucun compte n'est créé : elle apparaît seulement dans l'équipe d'une sortie.
3. Désactiver le compte du bureau. ✔ Connexion refusée sur l'administration et sur le mobile ; le jeton mobile existant ne fonctionne plus.
4. Avec un jeton mobile, appeler `/api/vehicles`. ✔ Refus (403) : le jeton ne sert qu'à l'application mobile.

## 4. Planification et validation (bureau)

1. Se connecter en `district@logimaster.test`, choisir le district MEAGUI (ou ANYAMA), menu **Chronogramme**. ✔ Période par défaut mai–oct. 2025 (filtre) ; calendrier large.
2. Créer une sortie : circuit, véhicule, date, **équipe** (chef + passagers). ✔ Enregistrée en « à valider ».
3. Glisser-déposer la sortie sur un autre jour. ✔ La date change ; un conflit (même véhicule, même jour) est signalé.
4. Se connecter en `bailleur@logimaster.test` (superviseur). ✔ Bouton **Valider** disponible ; le district ne peut pas valider lui-même.
5. Valider la sortie. ✔ Verrouillée (non modifiable) ; statut « validé ».
6. ✔ Le superviseur et les autres validateurs reçoivent la notification « Chronogramme validé » dans l'application (cloche) ; la sortie est visible à l'accueil de l'application mobile du district.
7. Un compte d'un autre district : ✔ ne voit pas cette sortie.

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

## 6 bis. Cartographie et suivi en temps réel

1. Menu **Cartographie** (sous Chronogramme), compte `pres@logimaster.test`. ✔ Carte des 113 districts sanitaires (limites officielles) avec les limites orange des régions ; vert = au moins une sortie saisie, rouge = rien ; points blancs = chefs-lieux.
2. Changer le **mois** puis la **région** (menu de la carte). ✔ Couleurs et compteurs mis à jour ; la région choisie est mise en avant et la carte zoome dessus ; le survol d'un district donne région, sorties et mois renseignés.
3. ✔ Le **taux de remplissage** s'affiche (bandeau de la carte, carte de chiffres, colonne « Taux » du tableau : mois renseignés / mois écoulés).
4. Menu **Suivi en temps réel**, district MEAGUI choisi dans le sélecteur. ✔ Les véhicules du district partent du chef-lieu à 07:30 (vitesses ×30 / ×60 / ×180, Pause, Recommencer) ; limite de la région en orange, district teinté.
5. Cliquer un véhicule dans la liste. ✔ La carte cadre son parcours et le met en avant. Changer de district dans le menu. ✔ La simulation suit le nouveau district (message explicite s'il n'a ni véhicule ni circuit).

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

Tester aussi : menu utilisateur (en haut à droite) → Paramètres (ESPC), Personnel, Districts au-dessus de Déconnexion ; groupe Administration ; absence des menus Factures/Budgets.

## 9. Pages d'erreur et sécurité

1. `/page-inexistante` ✔ page 404 personnalisée en français.
2. Jeton mobile sur l'API complète (`/api/vehicles`) ✔ 403.
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
