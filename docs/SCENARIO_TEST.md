# Scénario de test de bout en bout — Logimaster

Durée : environ 45 minutes. Un ordinateur (administration).
Chaque étape donne l'action puis le résultat attendu (✔). Cochez ou notez l'écart.

## 0. Préparation

| Élément | Valeur |
|---|---|
| Administration | `http://127.0.0.1:8085/admin` |
| Accueil | `http://127.0.0.1:8085/` |
| Comptes de démo (mot de passe `password`) | `pres@logimaster.test` (national), `region@logimaster.test`, `district@logimaster.test`, `bailleur@logimaster.test` |

1. Lancer l'application (« lance l'app ») ; noter l'adresse affichée.

## 1. Page d'accueil

1. Ouvrir `/`. ✔ Le titre apparaît mot par mot ; la scène tourne en boucle : toast « Chronogramme validé », camion qui avance, 4 sites cochés (le 3ᵉ en orange « transit »), compteurs Sites livrés / Distance qui montent, message « Sortie terminée ».
2. Défiler. ✔ L'en-tête devient sombre et flou ; la ligne du parcours se remplit et les 4 étapes s'allument ; les cartes apparaissent ; les anneaux d'indicateurs se remplissent ; les compteurs s'animent.
4. Ouvrir/fermer la FAQ. ✔ Une seule réponse ouverte à la fois n'est pas imposée ; l'animation de la flèche fonctionne.
5. Réduire la fenêtre à la largeur d'un téléphone. ✔ Pas de défilement horizontal, scène et boutons lisibles.
6. Préférence système « réduire les animations » : ✔ image finale fixe de la scène.

## 2. Connexion à l'administration

1. Ouvrir `/admin`. ✔ Écran de connexion Logimaster.
2. Se connecter avec un mauvais mot de passe. ✔ Message d'erreur, pas d'accès ; l'échec est visible dans le journal d'audit.
3. Se connecter avec `district@logimaster.test` / `password`. ✔ Tableau de bord du district ; menu limité à ce district.
4. Vérifier le menu : ✔ groupes « Carburant » (tableau de bord, **Carburant**, analyse), « Vidange » (calendrier, vidanges, immobilisations), « Données », « Administration ».
5. Vérifier l'absence : ✔ aucun menu Finance, Budgets, Factures ni « Suivi en temps réel ».

## 3. Comptes du bureau

1. Admin → menu utilisateur → **Utilisateurs** → créer un compte avec le rôle « Gestionnaire de district » et rattacher un district. ✔ Le compte se connecte à l'administration.
2. Créer une personne dans **Personnel** avec la fonction chef de mission. ✔ Aucun compte n'est créé : elle apparaît seulement dans l'équipe d'une sortie.
3. Désactiver le compte du bureau. ✔ Connexion refusée.

## 4. Planification et validation (bureau)

1. Se connecter en `district@logimaster.test`, choisir le district MEAGUI (ou ANYAMA), menu **Chronogramme**. ✔ Période par défaut mai–oct. 2025 (filtre) ; calendrier large.
2. Créer une sortie : circuit, véhicule, date, **équipe** (chef + passagers). ✔ Enregistrée en « à valider ».
3. Glisser-déposer la sortie sur un autre jour. ✔ La date change ; un conflit (même véhicule, même jour) est signalé.
4. Se connecter en `bailleur@logimaster.test` (superviseur). ✔ Bouton **Valider** disponible ; le district ne peut pas valider lui-même.
5. Valider la sortie. ✔ Verrouillée (non modifiable) ; statut « validé ».
6. ✔ Les autres validateurs reçoivent la notification « Chronogramme validé » dans l'application (cloche).
7. Un compte d'un autre district : ✔ ne voit pas cette sortie.

## 5. Saisie de la tournée (bureau)

1. Admin → **Saisie d'une tournée** (ou le lien « Saisir toute la tournée » du suivi des livraisons). ✔ La sortie du jour est choisie, le compteur de départ prérempli avec celui du véhicule.
2. Site 1 → **Livrée sur site** avec son compteur. ✔ Les champs date et note apparaissent.
3. Site 2 → **Livrée en transit**. ✔ Enregistrée comme livrée au point de transit.
4. Site 3 → **Non livrée** sans raison, puis enregistrer. ✔ Refus, message sur le champ « Raison » ; rien n'est enregistré.
5. Saisir un compteur inférieur à celui du site précédent. ✔ Refus, message sur le champ du compteur.
6. Section **Plein de carburant** : litres, prix modifié. ✔ Une confirmation du prix est demandée ; facture ajoutable depuis *Carburant*.
7. Renseigner le **compteur d'arrivée**. ✔ La sortie est terminée ; les sites non traités restent « à livrer ».
8. Enregistrer deux fois de suite. ✔ Aucun plein ni frais en double ; les pleins et frais déjà enregistrés sont listés sous le formulaire.

## 6. Vérification côté web (suivi des livraisons)

1. Admin → **Suivi des livraisons** (page unique). ✔ Les statuts saisis à l'étape 5 apparaissent, avec qui / quand / quoi, et les kilomètres parcourus.
2. **Carburant** (liste des pleins) ✔ le plein apparaît, lié à la sortie, avec le lien vers l'image de la facture (fenêtre d'aperçu).
3. **Livraisons ESPC** ✔ sites livrés présents ; « non livré » avec son motif.
4. Tableau de bord ✔ les indicateurs bougent (livraison, respect du chronogramme, carburant) ; filtre par défaut mai–oct. 2025 ; filtre district/région/PRES fonctionnel.

## 6 bis. Cartographie

1. Menu **Cartographie** (sous Chronogramme), compte `pres@logimaster.test`. ✔ Carte des 113 districts sanitaires (limites officielles) avec les limites orange des régions ; vert = au moins une sortie saisie, rouge = rien ; points blancs = chefs-lieux.
2. Changer le **mois** puis la **région** (menu de la carte). ✔ Couleurs et compteurs mis à jour ; la région choisie est mise en avant et la carte zoome dessus ; le survol d'un district donne région, sorties et mois renseignés.
3. ✔ Le **taux de remplissage** s'affiche (bandeau de la carte, carte de chiffres, colonne « Taux » du tableau : mois renseignés / mois écoulés).

## 6 ter. Enlèvement par les ESPC et mutualisation

1. Admin → **Autres frais** → créer, type « Enlèvement ESPC ». ✔ Le champ « Centre de santé (ESPC) » apparaît et est obligatoire ; le bénéficiaire se préremplit.
2. Enregistrer une indemnité de 2 500 FCFA. ✔ La ligne apparaît avec le libellé « Enlèvement ESPC » et le centre de santé ; filtre par type possible.
3. Ouvrir la fiche de l'ESPC. ✔ « Enlèvements au district » indique le total reçu.
4. Tableau de bord → détail de « Coût global » (après recalcul). ✔ La ligne « dont enlèvements par les ESPC » est visible.
5. Admin (gestionnaire d'un district) → **Mutualisation** → mettre un véhicule à disposition d'un autre district. ✔ Refus si la période chevauche une autre mise à disposition du même véhicule ou si la fin précède le début.
6. Se connecter comme gestionnaire du district utilisateur. ✔ Le véhicule apparaît dans sa liste « Mutualisation » (sens « Emprunté », sans bouton Modifier) ; un troisième district ne voit rien.
7. Lien **Activité** du véhicule. ✔ Les deux districts voient ses sorties, kilomètres, carburant, frais, vidanges et immobilisations, avec un résumé par district ; l'utilisateur ne voit que les dates de la mise à disposition.

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
3. Session expirée / déconnexion ✔ retour à la connexion.

## 10. Contrôle automatique

```bash
php -d memory_limit=2G vendor/bin/phpunit
```

✔ Toute la suite passe (172+ tests).

## Fiche de résultats

| Section | OK | Écart observé |
|---|---|---|
| 1 Accueil | | |
| 2 Connexion | | |
| 3 Accès | | |
| 4 Planification | | |
| 5 Saisie de la tournée | | |
| 6 Suivi web | | |
| 8 Rôles | | |
| 9 Erreurs | | |
