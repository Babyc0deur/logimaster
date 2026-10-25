# Besoins pour LogiMaster Pro — tirés du questionnaire de retour d'expérience (DAP, 21/10/2026)

Source : *Questionnaire de retour d'expérience sur l'outil LOGIMASTER_CI* (Google Form, 6 parties, 26 questions).
Ce document est un **cahier des besoins** : il rassemble, question par question, les informations que l'application doit
gérer, et indique ce qui existe déjà.

> **Important** : le fichier reçu est le questionnaire **vierge**. Il ne contient aucune réponse de district. Il dit donc
> *ce que la DAP veut savoir et mesurer*, pas encore *ce que les districts demandent*. Les priorités (parties 4 à 6) se
> confirmeront au dépouillement des réponses (voir la section 7).

Légende : ✔ couvert · ◐ partiel · ✖ manquant · ? à confirmer

---

## 1. Ce que le questionnaire révèle de la cible

| Élément | Valeurs prévues dans le questionnaire |
|---|---|
| Fonctions des utilisateurs | Gestionnaire PGP, pharmacien(ne), responsable PEV, responsable de programme, gestionnaire de données / statisticien(ne), responsable administratif et financier, autre |
| Fréquence d'usage | Tous les jours · plusieurs fois par semaine · une fois par semaine · quelques fois par mois · seulement pour le rapportage mensuel · lors de la consolidation avec l'équipe |
| Niveaux d'accès | District · Région / PRES · DAP · NPSP-CI · DGS · DGCMU · programmes de santé · partenaires techniques et financiers · autres services du Ministère |
| Supports | Ordinateur, téléphone, tablette |
| Mise en production | Version « Pro » digitale, testée par des districts volontaires (question 26) |

**À retenir pour l'application** : la liste des acteurs est plus large que nos rôles actuels
(`pres_admin`, `region_manager`, `district_manager`, `superviseur_bailleur`, `convoyeur`). Il manque des profils de
consultation pour la NPSP-CI, la DGS, la DGCMU, les programmes et les partenaires (voir la section 5).

---

## 2. Besoins de gestion de la flotte (partie 3) et état de l'application

### 3.1 Disponibilité des véhicules
| Besoin | État | Remarque |
|---|---|---|
| Véhicules disponibles / indisponibles | ✔ | Donut de l'état de la flotte, taux d'immobilisation |
| Motif, date de début, durée de l'indisponibilité | ✔ | Immobilisations (motif, dates, durée, coût, prestataire, facture) |
| Localisation du véhicule | ◐ | Position relevée à chaque livraison depuis le mobile ; le « temps réel » est une simulation |

### 3.2 Utilisation
| Besoin | État | Remarque |
|---|---|---|
| Nombre de sorties, motif, destination, circuit | ✔ | Sorties véhicules, circuits, chronogramme |
| Date et durée des déplacements | ◐ | Dates et heures de départ saisies ; durée réelle non calculée par sortie |
| Kilomètres parcourus | ✔ | Distance totale, kilométrage par étape |
| Taux d'utilisation | ✔ | Indicateur « Taux d'utilisation » |

### 3.3 Kilométrage
| Besoin | État | Remarque |
|---|---|---|
| Départ, arrivée, parcouru par sortie | ✔ | Compteurs saisis au bureau et sur mobile |
| Cumulé, par véhicule, sur une période | ✔ | Distance totale par motif et par véhicule |
| Comparaison entre véhicules | ◐ | Répartition par véhicule ; pas de classement comparatif |
| Calculs automatiques (écart, moyenne) | ◐ | Distance prévue / réelle dans le suivi des livraisons |

### 3.4 Carburant
| Besoin | État | Remarque |
|---|---|---|
| Quantité, montant, date, kilométrage au plein | ✔ | Ravitaillements (facture, validation) |
| Consommation moyenne, par véhicule, par période | ✔ | Analyse de consommation, réel contre théorique |
| Détection des consommations inhabituelles | ◐ | Anomalies détectées au plein ; pas d'alerte envoyée |
| Coût du carburant par véhicule | ✔ | Indicateur de coût global |

### 3.5 Maintenance et vidange
| Besoin | État | Remarque |
|---|---|---|
| Type d'entretien, dernier et prochain entretien (date, km) | ✔ | Vidanges, échéances, calendrier de maintenance |
| Nature des réparations, pièces remplacées | ✖ | Pas de détail par pièce |
| Coût, prestataire / garage | ◐ | Coût et prestataire sur les immobilisations ; à étendre aux autres interventions |
| Historique des interventions | ✔ | Onglets du véhicule |
| Alertes : échéance proche, dépassée, vidange à prévoir, kilométrage atteint | ✔ | Alertes rouge / orange / verte, commande quotidienne |

### 3.6 Pannes et immobilisations
| Besoin | État | Remarque |
|---|---|---|
| Date, véhicule concerné, début et durée | ✔ | Immobilisations ; signalement « panne » depuis le mobile |
| Nature et **cause** de la panne | ✖ | Un motif libre seulement |
| Réparations effectuées, coût, date de remise en service | ◐ | Coût et date de fin présents ; réparations non détaillées |
| Historique des pannes | ◐ | Via les immobilisations |
| Alerte d'immobilisation prolongée | ✔ | Seuil réglable en jours |

### 3.7 Coûts
| Besoin | État | Remarque |
|---|---|---|
| Carburant, maintenance, réparations, vidange, autres frais | ✔ | Ravitaillements, vidanges, immobilisations, dépenses |
| Pneumatiques, pièces de rechange, assurance | ✖ | À ajouter comme types de dépense |
| Coût total par véhicule, coût au kilomètre, coût mensuel | ◐ | Coût global et coût au kilomètre ; détail par véhicule à confirmer |

### 3.8 Conducteurs
| Besoin | État | Remarque |
|---|---|---|
| Identité, véhicule conduit, permis et sa validité | ✔ | Fiche chauffeur, alerte permis |
| Nombre de sorties, historique des déplacements | ✔ | Statistiques du chauffeur |
| Incidents et accidents | ◐ | Signalements limités à « panne » et « vidange » ; pas de type accident |
| Autres informations administratives | ? | À préciser avec les districts |

### 3.9 Affectation des ressources
| Besoin | État | Remarque |
|---|---|---|
| Véhicule affecté au district, conducteur affecté au véhicule | ◐ | Véhicule principal du chauffeur ; pas de période |
| **Période** et **historique** des affectations | ✖ | À créer |
| Affectation à une structure ou à une activité | ◐ | Motif et circuit de la sortie ; pas d'affectation durable |

### 3.10 Planification
| Besoin | État | Remarque |
|---|---|---|
| Planification des sorties, circuits, véhicules, conducteurs | ✔ | Chronogramme, validation par le superviseur |
| Visualisation du planning | ✔ | Vues semaine et mois |
| Prévu contre réalisé | ✔ | Respect du chronogramme, suivi des livraisons |
| Détection de conflits | ✔ | Refus des doubles réservations véhicule et chauffeur |

### 3.11 Historique d'un véhicule
| Besoin | État | Remarque |
|---|---|---|
| Déplacements, kilométrage, carburant, maintenance, vidanges, immobilisations | ✔ | Onglets du véhicule |
| Coûts, conducteurs, affectations | ◐ | Coûts partiels ; conducteurs et affectations absents |
| Choix de la période (mois, 3, 6, 12 mois, depuis la mise en service) | ◐ | Filtres de dates ; pas de raccourcis |

### 3.12 Alertes
| Alerte demandée | État |
|---|---|
| Maintenance à venir, vidange à prévoir, échéance dépassée | ✔ |
| Immobilisation prolongée | ✔ |
| Assurance et permis arrivant à échéance | ✔ |
| Consommation inhabituelle de carburant | ◐ (détectée, pas notifiée) |
| Kilométrage inhabituel | ✖ |
| Dépenses inhabituelles | ✖ |
| **Données non renseignées** | ✖ (la cartographie montre les mois vides, sans alerte) |
| **Retard de transmission des données** | ✖ |

### 3.13 Performance
| Indicateur | État |
|---|---|
| Taux de disponibilité, taux d'utilisation | ✔ |
| Nombre de sorties, kilométrage, consommation moyenne | ✔ |
| Coût d'utilisation, coût au kilomètre | ✔ / ◐ |
| Nombre de pannes, durée moyenne d'immobilisation | ◐ |
| **Respect du programme de maintenance** | ✖ |

---

## 3. Besoins transversaux (parties 2 et 4)

| Besoin exprimé | État | Remarque |
|---|---|---|
| Saisie plus simple et plus rapide | ◐ | Mobile pour le terrain ; import Excel ; saisie au bureau par circuit |
| Utilisation sur ordinateur, téléphone, tablette | ✔ | Back-office web et application mobile installable |
| Accès aux données en temps réel | ◐ | Rafraîchissement automatique des listes (30 s) ; pas de WebSocket |
| Consultation de l'historique, recherche rapide | ◐ | Recherche par liste ; pas de recherche globale |
| Calcul automatique des indicateurs | ✔ | 8 indicateurs DDKM |
| Production automatique de rapports | ✔ | PDF / Excel, envois planifiés |
| Tableaux de bord dynamiques | ✔ | Filtres PRES / région / district / dates |
| Cartographie des sites et circuits | ✔ | Cartographie, circuits, suivi en temps réel |
| Comparaison entre périodes | ✔ | « vs mois précédent » |
| Comparaison entre véhicules | ◐ | Répartition par véhicule |
| **Comparaison entre districts** | ◐ | Possible au niveau national ; pas de classement |
| Consolidation automatique | ✔ | Données de tous les districts dans une base |
| Écran d'accueil du matin (question 17) | ◐ | Tableau de bord ; à définir avec les réponses |
| Qualité des données (question 10) | ◐ | Contrôles à l'import ; pas de score par district |

---

## 4. Informations à collecter pour fournir ces fonctions

Champs qui **n'existent pas encore** et que les besoins ci-dessus exigent :

**Pannes et interventions**
- nature de la panne, cause de la panne ;
- réparations effectuées, pièces remplacées (libellé, quantité), garage ou prestataire sur toute intervention ;
- date de remise en service (déjà possible par la date de fin de l'immobilisation).

**Coûts**
- types de dépense à ajouter : pneumatiques, pièces de rechange, assurance ;
- rattachement systématique d'un coût au véhicule.

**Conducteurs**
- incidents et accidents (type, date, gravité, véhicule) ;
- autres informations administratives (à préciser avec les districts).

**Affectations**
- véhicule ↔ conducteur ↔ structure ou activité, avec **date de début et de fin** (historique).

**Alertes de pilotage**
- seuils pour kilométrage inhabituel, dépenses inhabituelles, nombre de jours sans saisie, retard de transmission.

**Qualité**
- date de saisie contre date de l'événement, pour mesurer la promptitude.

---

## 5. Qui voit quoi (partie 5)

Le questionnaire demande aux districts de dire quelles informations vont à chaque niveau. Proposition de départ, à valider
avec les réponses aux questions 18 à 22.

| Niveau | Information utile (hypothèse) | Existant |
|---|---|---|
| District | Saisie complète, planning, alertes, tableau de bord | ✔ |
| Région / PRES | Qualité et complétude des districts, comparaison entre districts | ◐ |
| DAP | Compilation des 33 régions, comparaisons, retour aux acteurs | ◐ |
| NPSP-CI, DGS, DGCMU | Indicateurs consolidés, lecture seule | ✖ (profil à créer) |
| Programmes de santé | Livraisons et circuits liés à leurs produits | ✖ |
| Partenaires techniques et financiers | Indicateurs agrégés, coûts, couverture, sans données nominatives | ◐ (rôle superviseur bailleur) |

**Règle à garder** : les profils extérieurs au district ne voient que des données agrégées, jamais les fiches personnelles
des conducteurs.

---

## 6. Écarts prioritaires (ce que l'application ne fait pas encore)

1. Alertes « **données non renseignées** » et « **retard de transmission** » (rôle de contrôle de la région).
2. **Score de qualité des données** par district (promptitude, complétude).
3. **Historique des affectations** véhicule ↔ conducteur, avec périodes.
4. **Pannes détaillées** : nature, cause, réparations, pièces, garage.
5. Types de dépense **pneumatiques, pièces, assurance**.
6. **Incidents et accidents** des conducteurs.
7. Alertes « **kilométrage inhabituel** » et « **dépenses inhabituelles** ».
8. Indicateur « **respect du programme de maintenance** ».
9. **Comparaison et classement** entre véhicules et entre districts.
10. Profils de **consultation** pour NPSP-CI, DGS, DGCMU, programmes et partenaires.

---

## 7. Exploiter les réponses quand elles arriveront

| Question | À en tirer |
|---|---|
| 5 | Note de facilité d'usage (1 à 5) : moyenne par fonction et par ancienneté |
| 6, 8 | Étapes les plus pénibles : première cible de simplification |
| 7, 9, 12 | Informations manquantes : écarts à combler |
| 10 | Fréquence et types d'erreurs : règles de validation à ajouter |
| 11 | Informations en retard : alertes et automatisations |
| 13 | Trois principales limites : fonctionnalités à corriger d'abord |
| 14, 23, 24 | Priorités : classement des chantiers (compter les 5 choix, puis la priorité n° 1) |
| 15, 16, 17 | Calculs, alertes et écran d'accueil à construire |
| 18 à 22 | Droits d'accès par niveau et profils de consultation |
| 26 | Districts volontaires pour tester la nouvelle version |

Les districts qui répondent « tous les jours » ou « plusieurs fois par semaine » sont les plus utiles pour les tests.
