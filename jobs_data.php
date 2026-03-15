<?php
/**
 * jobs_data.php — Données des jobs VTOM pour le tableau de bord de supervision
 *
 * Dans un environnement réel, remplacez ce tableau statique par une requête
 * à l'API REST VTOM ou à votre base de données de supervision.
 *
 * Structure de chaque job :
 *   - id          : identifiant unique du job
 *   - nom         : nom du job dans VTOM
 *   - trigrame    : trigramme de l'application concernée
 *   - environnement : ex. PROD, PREPROD, REC, DEV
 *   - unite_soumission : unité de soumission VTOM (agent d'exécution)
 *   - statut      : 'OK', 'ERREUR', 'HORAIRE_DEPASSE', 'EN_COURS', 'EN_ATTENTE'
 *   - derniere_execution : date/heure de la dernière exécution
 *   - fichier_log : chemin ou URL vers le fichier de log
 *   - consigne    : URL vers la consigne/procédure à exécuter en cas d'incident
 *   - commentaire : note optionnelle
 */

function getJobsData(): array
{
    return [
        [
            'id'                  => 1,
            'nom'                 => 'JOB_BATCH_COMPTA_NUIT',
            'trigrame'            => 'CPT',
            'environnement'       => 'PROD',
            'unite_soumission'    => 'US_UNIX_PROD_01',
            'statut'              => 'ERREUR',
            'derniere_execution'  => '2026-03-15 02:15:00',
            'fichier_log'         => '/logs/prod/cpt/JOB_BATCH_COMPTA_NUIT_20260315.log',
            'consigne'            => 'https://wiki.interne/consignes/CPT/JOB_BATCH_COMPTA_NUIT',
            'commentaire'         => 'Échec connexion base Oracle',
        ],
        [
            'id'                  => 2,
            'nom'                 => 'JOB_EXTRACT_RH_HEBDO',
            'trigrame'            => 'RHX',
            'environnement'       => 'PROD',
            'unite_soumission'    => 'US_WIN_PROD_02',
            'statut'              => 'OK',
            'derniere_execution'  => '2026-03-15 01:00:00',
            'fichier_log'         => '/logs/prod/rhx/JOB_EXTRACT_RH_HEBDO_20260315.log',
            'consigne'            => 'https://wiki.interne/consignes/RHX/JOB_EXTRACT_RH_HEBDO',
            'commentaire'         => '',
        ],
        [
            'id'                  => 3,
            'nom'                 => 'JOB_SYNCHRO_STOCKS',
            'trigrame'            => 'STK',
            'environnement'       => 'PROD',
            'unite_soumission'    => 'US_UNIX_PROD_03',
            'statut'              => 'HORAIRE_DEPASSE',
            'derniere_execution'  => '2026-03-14 23:30:00',
            'fichier_log'         => '/logs/prod/stk/JOB_SYNCHRO_STOCKS_20260314.log',
            'consigne'            => 'https://wiki.interne/consignes/STK/JOB_SYNCHRO_STOCKS',
            'commentaire'         => 'Durée max 90 min dépassée',
        ],
        [
            'id'                  => 4,
            'nom'                 => 'JOB_PURGE_ARCHIVE_DOC',
            'trigrame'            => 'GED',
            'environnement'       => 'PROD',
            'unite_soumission'    => 'US_UNIX_PROD_01',
            'statut'              => 'OK',
            'derniere_execution'  => '2026-03-15 03:00:00',
            'fichier_log'         => '/logs/prod/ged/JOB_PURGE_ARCHIVE_DOC_20260315.log',
            'consigne'            => 'https://wiki.interne/consignes/GED/JOB_PURGE_ARCHIVE_DOC',
            'commentaire'         => '',
        ],
        [
            'id'                  => 5,
            'nom'                 => 'JOB_FACTURATION_MENSUELLE',
            'trigrame'            => 'FAC',
            'environnement'       => 'PROD',
            'unite_soumission'    => 'US_WIN_PROD_04',
            'statut'              => 'ERREUR',
            'derniere_execution'  => '2026-03-15 00:45:00',
            'fichier_log'         => '/logs/prod/fac/JOB_FACTURATION_MENSUELLE_20260315.log',
            'consigne'            => 'https://wiki.interne/consignes/FAC/JOB_FACTURATION_MENSUELLE',
            'commentaire'         => 'Return code 8 — voir log',
        ],
        [
            'id'                  => 6,
            'nom'                 => 'JOB_IMPORT_CLIENTS_EXT',
            'trigrame'            => 'CRM',
            'environnement'       => 'PREPROD',
            'unite_soumission'    => 'US_UNIX_PPD_01',
            'statut'              => 'EN_COURS',
            'derniere_execution'  => '2026-03-15 05:10:00',
            'fichier_log'         => '/logs/preprod/crm/JOB_IMPORT_CLIENTS_EXT_20260315.log',
            'consigne'            => 'https://wiki.interne/consignes/CRM/JOB_IMPORT_CLIENTS_EXT',
            'commentaire'         => '',
        ],
        [
            'id'                  => 7,
            'nom'                 => 'JOB_CALCUL_PROVISIONS',
            'trigrame'            => 'CPT',
            'environnement'       => 'PROD',
            'unite_soumission'    => 'US_UNIX_PROD_02',
            'statut'              => 'HORAIRE_DEPASSE',
            'derniere_execution'  => '2026-03-14 22:00:00',
            'fichier_log'         => '/logs/prod/cpt/JOB_CALCUL_PROVISIONS_20260314.log',
            'consigne'            => 'https://wiki.interne/consignes/CPT/JOB_CALCUL_PROVISIONS',
            'commentaire'         => 'Attente verrou table PROV_ANN',
        ],
        [
            'id'                  => 8,
            'nom'                 => 'JOB_BACKUP_DB_NUIT',
            'trigrame'            => 'INF',
            'environnement'       => 'PROD',
            'unite_soumission'    => 'US_UNIX_PROD_01',
            'statut'              => 'OK',
            'derniere_execution'  => '2026-03-15 04:00:00',
            'fichier_log'         => '/logs/prod/inf/JOB_BACKUP_DB_NUIT_20260315.log',
            'consigne'            => 'https://wiki.interne/consignes/INF/JOB_BACKUP_DB_NUIT',
            'commentaire'         => '',
        ],
        [
            'id'                  => 9,
            'nom'                 => 'JOB_REPORTING_VENTES',
            'trigrame'            => 'VTE',
            'environnement'       => 'PROD',
            'unite_soumission'    => 'US_WIN_PROD_02',
            'statut'              => 'EN_ATTENTE',
            'derniere_execution'  => null,
            'fichier_log'         => null,
            'consigne'            => 'https://wiki.interne/consignes/VTE/JOB_REPORTING_VENTES',
            'commentaire'         => 'En attente du job prédécesseur',
        ],
        [
            'id'                  => 10,
            'nom'                 => 'JOB_ENVOI_ALERTES_SMS',
            'trigrame'            => 'NOT',
            'environnement'       => 'PROD',
            'unite_soumission'    => 'US_UNIX_PROD_03',
            'statut'              => 'ERREUR',
            'derniere_execution'  => '2026-03-15 05:30:00',
            'fichier_log'         => '/logs/prod/not/JOB_ENVOI_ALERTES_SMS_20260315.log',
            'consigne'            => 'https://wiki.interne/consignes/NOT/JOB_ENVOI_ALERTES_SMS',
            'commentaire'         => 'Passerelle SMS injoignable',
        ],
    ];
}

/**
 * Retourne les statistiques agrégées des jobs.
 */
function getJobsStats(array $jobs): array
{
    $stats = [
        'total'           => count($jobs),
        'erreur'          => 0,
        'horaire_depasse' => 0,
        'ok'              => 0,
        'en_cours'        => 0,
        'en_attente'      => 0,
    ];

    foreach ($jobs as $job) {
        match ($job['statut']) {
            'ERREUR'          => $stats['erreur']++,
            'HORAIRE_DEPASSE' => $stats['horaire_depasse']++,
            'OK'              => $stats['ok']++,
            'EN_COURS'        => $stats['en_cours']++,
            'EN_ATTENTE'      => $stats['en_attente']++,
            default           => null,
        };
    }

    return $stats;
}
