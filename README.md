# Chatbot GPT-4 — Azure AI Foundry

Exemple de chatbot web en PHP utilisant **Azure OpenAI GPT-4** via **Azure AI Foundry**.

## Prérequis

- PHP 8.0+ avec extension `curl`
- Un compte Azure avec un déploiement GPT-4 sur **Azure AI Foundry**

## Configuration

### 1. Déployer un modèle GPT-4

1. Ouvre [AI Foundry](https://ai.azure.com)
2. Crée un hub → un projet
3. Va dans **Déploiements** → déploie `gpt-4`
4. Note l'**endpoint** et la **clé API**

### 2. Configurer l'application

Édite `config.php` avec tes valeurs :

```php
define('AZURE_ENDPOINT',   'https://YOUR_RESOURCE.openai.azure.com');
define('AZURE_API_KEY',    'YOUR_API_KEY');
define('AZURE_DEPLOYMENT', 'gpt-4');
```

Ou utilise des variables d'environnement (recommandé en production) :

```bash
export AZURE_OPENAI_ENDPOINT="https://YOUR_RESOURCE.openai.azure.com"
export AZURE_OPENAI_API_KEY="YOUR_API_KEY"
export AZURE_OPENAI_DEPLOYMENT="gpt-4"
export AZURE_OPENAI_API_VERSION="2024-02-01"   # optionnel, valeur par défaut
export ALLOWED_ORIGIN="https://your-domain.com" # optionnel, pour CORS
```

### 3. Paramètres de sécurité (config.php)

| Constante | Défaut | Description |
|---|---|---|
| `MAX_HISTORY_MESSAGES` | `50` | Nombre maximum de messages conservés dans l'historique |
| `MAX_MESSAGE_LENGTH` | `4000` | Longueur maximale du contenu d'un message (caractères) |
| `RATE_LIMIT_REQUESTS` | `20` | Nombre maximum de requêtes par fenêtre de temps |
| `RATE_LIMIT_WINDOW` | `60` | Durée de la fenêtre de rate limiting (secondes) |

### 4. Lancer en local

```bash
php -S localhost:8080
```

Puis ouvre http://localhost:8080

## Structure

```
├── index.php      # Interface utilisateur (HTML/CSS/JS)
├── api.php        # Backend — appel à Azure OpenAI
├── config.php     # Configuration (endpoint, clé, deployment, paramètres de sécurité)
├── security.php   # Mesures de sécurité centralisées
└── .gitignore
```

## Sécurité

Le fichier `security.php` centralise toutes les mesures de sécurité :

### Protection CSRF
Chaque chargement de page génère un token CSRF stocké en session. Ce token est transmis dans l'en-tête `X-CSRF-Token` à chaque requête et vérifié côté serveur avec `hash_equals` pour éviter les attaques temporelles.

### En-têtes HTTP de sécurité
Envoyés automatiquement à chaque réponse :
- `Content-Security-Policy` — restreint les sources de scripts, styles, images et connexions
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy` — désactive caméra, micro, géolocalisation et paiement

Un nonce CSP unique est généré à chaque chargement de page pour autoriser les scripts inline.

### CORS
Les requêtes cross-origin sont restreintes à la même origine. L'en-tête `ALLOWED_ORIGIN` peut être surchargé via la variable d'environnement `ALLOWED_ORIGIN`.

### Rate limiting
Limitation par IP via un stockage fichier dans le répertoire temporaire système. En cas de dépassement du quota, l'IP est bloquée 5 minutes et l'en-tête `Retry-After: 300` est retourné.

### Validation des messages
- Seuls les rôles `user` et `assistant` sont acceptés
- Les contenus sont tronqués à `MAX_MESSAGE_LENGTH` caractères
- Les caractères de contrôle et octets nuls sont supprimés
- L'historique est limité à `MAX_HISTORY_MESSAGES` messages

### Session sécurisée
Cookie de session configuré avec `HttpOnly`, `SameSite=Strict` et `Secure` (si HTTPS actif).

### Bonnes pratiques
- Ne jamais committer `config.php` avec de vraies clés
- Utiliser des variables d'environnement en production
- Ajouter une authentification si exposé publiquement
- Configurer un **Content Filter** dans Azure AI Foundry

## Déploiement

Compatible avec tout hébergeur PHP (Azure App Service, Apache, Nginx+PHP-FPM).

Pour Azure App Service, configure les variables dans **Configuration > Paramètres d'application**.
