<?php

namespace App\Domain\Import\Workbook;

/**
 * Structure du classeur « Logimaster » (modèle Excel des districts) : onglets importables, en-têtes
 * tels que dans le fichier d'origine, et mots-clés (en minuscules, sans accents ni ponctuation)
 * qui permettent de retrouver chaque colonne même si l'intitulé varie légèrement.
 */
final class WorkbookSpec
{
    /** Motifs de déplacement : libellé du classeur => clé interne. */
    public const MOTIFS = [
        'Livraison des produits de santé aux ESPC' => 'distribution',
        'Redistribution des produits de santé' => 'redistribution',
        'Enlèvement de produits de santé à la NPSP' => 'enlevement_npsp',
        'Supervision' => 'supervision',
        'Coaching des acteurs' => 'coaching',
        'Autres déplacement (à préciser)' => 'autre',
    ];

    public const IMMOBILISATION_MOTIFS = [
        'Visite technique' => 'visite_technique',
        'Contrôle technique' => 'controle_technique',
        'Réparation' => 'reparation',
        'Dépannage' => 'depannage',
        'Vidange' => 'vidange',
        'Incidents (Vol, incendie, accident…)' => 'incident',
    ];

    public const EXPENSE_TYPES = [
        'Collation' => 'collation',
        'Frais de chargement' => 'chargement',
        'Frais de déchargement' => 'dechargement',
        'Hébergement' => 'hebergement',
        'Autres dépenses (à préciser)' => 'autre',
    ];

    /**
     * Onglets dans l'ordre d'import (les références — sites, véhicules, circuits — d'abord).
     * fields : champ interne => mot(s)-clé de l'en-tête ; key : champ qui identifie la ligne d'en-tête.
     *
     * @return array<string, array{key: string, headers: array<int, string>, fields: array<string, string|array<int, string>>}>
     */
    public static function sheets(): array
    {
        return [
            'Liste des Sites' => [
                'key' => 'nom',
                'headers' => ['N°', 'Nom des ESPC et autres sites de destination'],
                'fields' => ['nom' => ['nomdesespc', 'nomdeslieux']],
            ],
            'VEHICULES' => [
                'key' => 'immat',
                'headers' => [
                    'Immatriculation du véhicule', 'District sanitaire', "Date de réception du véhicule\n(JJ/MM/AAAA)", 'Nom du Bailleur',
                    'Marque du véhicule', 'Modèle', "Vignette\n(Année)", "Date de mise en circulation\n(JJ/MM/AAAA)",
                    "Validité assurance\n(JJ/MM/AAAA)", 'Appartenance du véhicule', 'Type de carburant',
                    "Date de dernier contrôle technique\n(JJ/MM/AAAA)", "Date du prochain contrôle technique\n(JJ/MM/AAAA)",
                    'Vidange au Km', "Poids du véhicule à vide\n(Kg)", 'Commentaire',
                ],
                'fields' => [
                    'immat' => 'immatriculationduvehicule', 'district' => 'districtsanitaire', 'date_reception' => 'datedereception',
                    'bailleur' => 'nomdubailleur', 'marque' => 'marqueduvehicule', 'modele' => 'modele', 'vignette' => 'vignette',
                    'mise_en_circulation' => 'datedemiseencirculation', 'assurance' => 'validiteassurance',
                    'appartenance' => 'appartenanceduvehicule', 'carburant' => 'typedecarburant', 'dernier_ct' => 'datededernier',
                    'prochain_ct' => 'dateduprochain', 'vidange_km' => 'vidangeaukm', 'poids' => 'poidsduvehicule', 'commentaire' => 'commentaire',
                ],
            ],
            'CHRONOGRAMME' => [
                'key' => 'circuit',
                'headers' => [
                    'Mois', 'Motif de déplacement', 'Nom du circuit', 'Nom des SITES sur chaque circuit (Du 1er visité au dernier visité)',
                    "Date de déplacement planifiée\n(JJ/MM/AAAA)", 'COMMENTAIRE',
                ],
                'fields' => [
                    'mois' => 'mois', 'motif' => 'motifdedeplacement', 'circuit' => 'nomducircuit', 'site' => 'nomdessites',
                    'date' => 'datededeplacementplanifiee', 'commentaire' => 'commentaire',
                ],
            ],
            'CIRCUITS' => [
                'key' => 'immat',
                'headers' => [
                    'Immatriculation du véhicule', "Date\nJJ/MM/AAAA", 'Nom du chauffeur', 'Chef de Mission', 'Passager 1', 'Passager 2', 'Passager 3',
                    'Nom du circuit Executé', 'Point de départ ', "Kilométrage au depart\n(km)", "Point d'arrivée ", "Kilométrage d'arrivée\n(km)",
                    'Motif du déplacement', "Distance parcourue\n(Km)", 'Commentaires',
                ],
                'fields' => [
                    'immat' => 'immatriculationduvehicule', 'date' => 'datejjmmaaaa', 'chauffeur' => 'nomduchauffeur', 'chef' => 'chefdemission',
                    'p1' => 'passager1', 'p2' => 'passager2', 'p3' => 'passager3', 'circuit' => 'nomducircuitexecute',
                    'depart' => 'pointdedepart', 'km_depart' => 'kilometrageaudepart', 'arrivee' => 'pointdarrivee',
                    'km_arrivee' => 'kilometragedarrivee', 'motif' => 'motifdudeplacement', 'commentaire' => 'commentaires',
                ],
            ],
            'CARBURANT' => [
                'key' => 'immat',
                'headers' => [
                    'Immatriculation du vehicule', 'Nom du chauffeur', 'Motif du déplacement', 'Type de carburant', "Date de transaction\n(JJ/MM/AAAA)",
                    "Relevé Kilométrage à la prise du caburant\n(Km)", 'Total litres ravitaillés', "Prix unitaire\n(F.CFA)", "Prix total\n(F.CFA)",
                    'N°Facture / Numéro de coupon', 'Commentaire',
                ],
                'fields' => [
                    'immat' => 'immatriculationduvehicule', 'chauffeur' => 'nomduchauffeur', 'motif' => 'motifdudeplacement',
                    'carburant' => 'typedecarburant', 'date' => 'datedetransaction', 'km' => 'relevekilometrage', 'litres' => 'totallitres',
                    'pu' => 'prixunitaire', 'facture' => 'facture', 'commentaire' => 'commentaire',
                ],
            ],
            'AUTRES FRAIS' => [
                'key' => 'immat',
                'headers' => [
                    "Date\n(JJ/MM/AAAA)", 'Bénéficiaires', 'Immatriculation du véhicule', 'Motif de déplacement', 'Type de dépense',
                    "Montant Total\n(F.CFA)", 'Commentaire',
                ],
                'fields' => [
                    'date' => 'datejjmmaaaa', 'beneficiaire' => 'beneficiaires', 'immat' => 'immatriculationduvehicule',
                    'motif' => ['motifdedeplacement', 'motifdudeplacement'], 'type' => 'typededepense', 'montant' => 'montanttotal', 'commentaire' => 'commentaire',
                ],
            ],
            'VIDANGES' => [
                'key' => 'immat',
                'headers' => [
                    'Immatriculation du véhicule', 'Date de la dernière vidange', 'Kilométrage à la dernière vidange', 'N°Facture / N° de coupon',
                    'Montant  de la vidange', 'Prochaine vidange prévue (kilométrage)', 'Observations',
                ],
                'fields' => [
                    'immat' => 'immatriculationduvehicule', 'date' => 'datedeladernierevidange', 'km' => 'kilometragealadernierevidange',
                    'facture' => 'facture', 'montant' => 'montantdelavidange', 'prochain_km' => 'prochainevidangeprevue', 'observations' => 'observations',
                ],
            ],
            'IMMOBILISATION' => [
                'key' => 'immat',
                'headers' => [
                    'Immatriculation du véhicule', 'Mois', "Date d'indisponibilité\n(JJ/MM/AAAA)", "Motifs d'immobilisation ", "Montant Total\n(F.CFA)",
                    'Prestataire de service ', "Date de disponibilité\n(JJ/MM/AAAA)", 'Commentaire',
                ],
                'fields' => [
                    'immat' => 'immatriculationduvehicule', 'mois' => 'mois', 'debut' => 'datedindisponibilite', 'motif' => 'motifsdimmobilisation',
                    'montant' => 'montanttotal', 'prestataire' => 'prestatairedeservice', 'fin' => 'datededisponibilite', 'commentaire' => 'commentaire',
                ],
            ],
        ];
    }
}
