# Supervision VTOM — Tableau de bord des jobs ordonnancés

Interface web de monitoring des jobs de l'ordonnanceur **VTOM** (Visual TOM).
Regroupe en un seul tableau l'ensemble des jobs de production avec leur statut, leur trigramme applicatif, leur environnement, leur unité de soumission, le lien vers le fichier de log et la consigne à appliquer en cas d'incident.

---

## Sommaire

1. [Présentation](#présentation)
2. [Fonctionnalités](#fonctionnalités)
3. [Structure des fichiers](#structure-des-fichiers)
4. [Installation](#installation)
5. [Configuration](#configuration)
6. [Brancher les données VTOM réelles](#brancher-les-données-vtom-réelles)
7. [Description du tableau de bord](#description-du-tableau-de-bord)
8. [Statuts des jobs](#statuts-des-jobs)
9. [Sécurité](#sécurité)
10. [Captures d'écran](#captures-décran)

---

## Présentation

Dans un environnement de production, l'ordonnanceur **VTOM** pilote des centaines de jobs automatisés répartis sur plusieurs applications, environnements et agents d'exécution.

Ce tableau de bord centralise la supervision en temps quasi-réel :
- les équipes d'exploitation identifient **instantanément** les jobs en erreur ou dont l'horaire est dépassé
- chaque job expose directement le **lien vers sa consigne** d'exploitation pour une remédiation rapide
- les **fichiers de log** sont accessibles en un clic (chemin copié dans le presse-papier)

---

## Fonctionnalités

| Fonctionnalité | Détail |
|---|---|
| **Tableau des jobs** | Nom, trigramme, environnement, unité de soumission, statut, dernière exécution, log, consigne |
| **Badges de statut colorés** | ERREUR (rouge), HORAIRE DÉPASSÉ (orange), OK (vert), EN COURS (bleu), EN ATTENTE (violet) |
| **Compteurs agrégés** | Totaux par statut affichés en haut de page |
| **Filtres combinables** | Recherche texte libre + filtre statut + filtre environnement + filtre trigramme |
| **Tri par colonne** | Clic sur n'importe quel en-tête de colonne |
| **Auto-refresh** | Actualisation automatique toutes les 60 secondes avec countdown visible |
| **Copie du chemin de log** | Clic sur le bouton Log → chemin copié dans le presse-papier |
| **Lien consigne** | Lien externe vers la procédure Wiki/Confluence à appliquer |
| **Export CSV** | Export du tableau complet, encodé UTF-8 BOM (compatible Excel) |
| **Thème sombre** | Interface dark mode adaptée aux salles de supervision 24/7 |

---

## Structure des fichiers

```
ai-foundry-chatbot/
├── dashboard.php       # Page principale du tableau de bord
├── jobs_data.php       # Couche de données — à connecter à VTOM ou à votre BDD
├── security.php        # Module de sécurité mutualisé (headers, CSRF, rate-limit)
├── index.php           # Chatbot GPT-4 (lien vers le dashboard en en-tête)
├── api.php             # Backend chatbot
└── config.php          # Configuration Azure OpenAI
```

### Rôle de chaque fichier VTOM

**`dashboard.php`**
Interface complète du tableau de bord. Charge les données via `jobs_data.php`, applique les headers de sécurité et génère la page HTML avec le tableau, les filtres et les scripts JS.

**`jobs_data.php`**
Couche de données isolée. Contient deux fonctions :
- `getJobsData()` — retourne le tableau des jobs (statique en l'état, à remplacer par un appel API/BDD)
- `getJobsStats(array $jobs)` — calcule les compteurs agrégés par statut

---

## Installation

### Prérequis

- PHP 8.0+ avec l'extension `curl`
- Serveur web Apache, Nginx + PHP-FPM, ou serveur intégré PHP

### Démarrage rapide

```bash
# Cloner le dépôt
git clone https://github.com/mcsalahash/ai-foundry-chatbot.git
cd ai-foundry-chatbot

# Lancer le serveur PHP intégré
php -S localhost:8080

# Ouvrir le tableau de bord
# http://localhost:8080/dashboard.php
```

---

## Configuration

Aucune configuration n'est requise pour démarrer avec les données de démonstration.
Le tableau s'affiche immédiatement avec 10 jobs exemples couvrant les différents statuts.

---

## Brancher les données VTOM réelles

Toute la logique de récupération des données est isolée dans `jobs_data.php`.
Il suffit de remplacer le contenu de la fonction `getJobsData()` par votre source réelle.

### Option 1 — API REST VTOM

VTOM expose une API REST. Exemple d'appel avec cURL :

```php
function getJobsData(): array
{
    $vtomUrl = 'https://vtom-server:8080/vtom/api/v1/jobs?env=PROD';
    $token   = getenv('VTOM_API_TOKEN');

    $ch = curl_init($vtomUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $token"],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    // Adapter les champs VTOM au format attendu par dashboard.php
    return array_map(fn($job) => [
        'id'                 => $job['id'],
        'nom'                => $job['name'],
        'trigrame'           => $job['application']['trigram'],
        'environnement'      => $job['environment'],
        'unite_soumission'   => $job['submissionUnit'],
        'statut'             => mapVtomStatus($job['status']),  // voir ci-dessous
        'derniere_execution' => $job['lastExecution'] ?? null,
        'fichier_log'        => $job['logFile'] ?? null,
        'consigne'           => $job['procedureUrl'],
        'commentaire'        => $job['comment'] ?? '',
    ], $data['jobs'] ?? []);
}

// Correspondance statuts VTOM → statuts du dashboard
function mapVtomStatus(string $vtomStatus): string
{
    return match ($vtomStatus) {
        'ERROR', 'ABORTED'    => 'ERREUR',
        'OVERTIME'            => 'HORAIRE_DEPASSE',
        'ENDED_OK'            => 'OK',
        'RUNNING'             => 'EN_COURS',
        'WAITING', 'PLANNED'  => 'EN_ATTENTE',
        default               => 'EN_ATTENTE',
    };
}
```

### Option 2 — Base de données (PDO)

```php
function getJobsData(): array
{
    $pdo = new PDO(
        'mysql:host=db-server;dbname=vtom_supervision;charset=utf8',
        getenv('DB_USER'),
        getenv('DB_PASS')
    );

    $stmt = $pdo->query("
        SELECT j.id, j.nom, a.trigrame, j.environnement,
               j.unite_soumission, j.statut,
               j.derniere_execution, j.fichier_log,
               j.consigne, j.commentaire
        FROM jobs j
        JOIN applications a ON j.application_id = a.id
        WHERE j.actif = 1
        ORDER BY j.statut DESC, j.nom ASC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

### Option 3 — Fichier JSON (supervision externe)

Si votre outil de supervision génère un export JSON :

```php
function getJobsData(): array
{
    $json = file_get_contents('/var/vtom/exports/jobs_status.json');
    return json_decode($json, true) ?? [];
}
```

### Format attendu par le dashboard

Chaque job doit respecter cette structure :

```php
[
    'id'                 => 1,               // int — identifiant unique
    'nom'                => 'JOB_XXX',       // string — nom du job VTOM
    'trigrame'           => 'CPT',           // string — trigramme applicatif (3 lettres)
    'environnement'      => 'PROD',          // string — PROD | PREPROD | REC | DEV
    'unite_soumission'   => 'US_UNIX_01',    // string — agent d'exécution VTOM
    'statut'             => 'ERREUR',        // string — voir tableau des statuts ci-dessous
    'derniere_execution' => '2026-03-15 02:15:00', // string|null — format Y-m-d H:i:s
    'fichier_log'        => '/logs/xxx.log', // string|null — chemin ou URL du log
    'consigne'           => 'https://...',   // string — URL vers la consigne
    'commentaire'        => 'Message libre', // string — note optionnelle
]
```

---

## Description du tableau de bord

### En-tête

- Titre + date/heure de dernière mise à jour
- Countdown de la prochaine actualisation automatique (60 s)
- Bouton **Actualiser** (rechargement immédiat)
- Bouton **Export CSV** (export du tableau complet)
- Lien retour vers le chatbot

### Barre de compteurs

Six tuiles affichant le nombre de jobs par catégorie :
Total · En erreur · Horaire dépassé · Terminés OK · En cours · En attente

### Filtres

| Filtre | Comportement |
|---|---|
| Champ texte | Recherche dans le nom du job, le trigramme, l'unité de soumission et le commentaire |
| Statut | Filtre sur un statut exact |
| Environnement | Filtre sur PROD, PREPROD, REC, DEV (liste dynamique) |
| Trigramme | Filtre sur un trigramme applicatif (liste dynamique) |

Les filtres sont **cumulables** et s'appliquent en temps réel à la saisie.

### Tableau

| Colonne | Description |
|---|---|
| Nom du job | Identifiant technique du job dans VTOM |
| Trigramme | Code à 3 lettres identifiant l'application métier |
| Environnement | Environnement d'exécution du job |
| Unité de soumission | Agent VTOM responsable de l'exécution |
| Statut | État courant du job (voir section suivante) |
| Dernière exécution | Date et heure de la dernière exécution connue |
| Fichier log | Bouton copiant le chemin du log dans le presse-papier |
| Consigne | Lien vers la procédure d'exploitation à appliquer |
| Commentaire | Note de contexte (message d'erreur, cause, etc.) |

---

## Statuts des jobs

| Statut | Couleur | Description |
|---|---|---|
| `ERREUR` | Rouge | Le job s'est terminé en erreur (return code non nul, exception, etc.) |
| `HORAIRE_DEPASSE` | Orange | Le job est toujours en cours alors que sa durée maximale est dépassée |
| `OK` | Vert | Le job s'est terminé avec succès |
| `EN_COURS` | Bleu | Le job est actuellement en cours d'exécution |
| `EN_ATTENTE` | Violet | Le job attend son heure de déclenchement ou un job prédécesseur |

---

## Sécurité

Le tableau de bord hérite du module `security.php` commun à l'application :

- **Headers HTTP** : `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`
- **Content Security Policy** : nonce unique par chargement de page pour les scripts inline
- **Encodage des sorties** : toutes les données affichées sont échappées via `htmlspecialchars()`
- **Liens externes** : attributs `rel="noopener noreferrer"` sur tous les liens ouvrant un nouvel onglet

### Recommandations pour la production

- Placer le tableau de bord derrière une **authentification** (SSO, LDAP, reverse-proxy Nginx avec `auth_basic`)
- Restreindre l'accès par **filtrage IP** (réseau d'exploitation uniquement)
- Ne pas exposer les chemins de logs complets si le serveur est accessible depuis internet — utiliser des identifiants opaques et résoudre côté serveur
- Activer **HTTPS** (certificat TLS obligatoire en production)
- Configurer l'auto-refresh selon la charge acceptable sur l'API VTOM (valeur par défaut : 60 s)
