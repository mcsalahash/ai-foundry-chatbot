# Documentation de sécurité — Chatbot GPT-4 Azure AI Foundry

Ce document décrit l'ensemble des mesures de sécurité implémentées dans le chatbot.
Toute la logique de sécurité est centralisée dans `security.php` et appliquée par `api.php`.

---

## Table des matières

1. [Session sécurisée](#1-session-sécurisée)
2. [Protection CSRF](#2-protection-csrf)
3. [En-têtes HTTP de sécurité](#3-en-têtes-http-de-sécurité)
4. [Politique CORS](#4-politique-cors)
5. [Rate limiting](#5-rate-limiting)
6. [Validation et assainissement des messages](#6-validation-et-assainissement-des-messages)
7. [Validation de la requête HTTP](#7-validation-de-la-requête-http)
8. [Appel à Azure OpenAI](#8-appel-à-azure-openai)
9. [Paramètres configurables](#9-paramètres-configurables)

---

## 1. Session sécurisée

**Fonction** : `startSecureSession()` — `security.php:10`

Le cookie de session PHP est configuré avec les attributs suivants avant tout démarrage de session :

| Attribut | Valeur | Rôle |
|---|---|---|
| `lifetime` | `0` | Session expirée à la fermeture du navigateur |
| `secure` | `true` si HTTPS | Transmission du cookie uniquement en HTTPS |
| `httponly` | `true` | Cookie inaccessible au JavaScript (protection XSS) |
| `samesite` | `Strict` | Cookie envoyé uniquement aux requêtes same-origin (protection CSRF) |

---

## 2. Protection CSRF

**Fonctions** : `getCsrfToken()`, `verifyCsrfToken()` — `security.php:29-50`

### Fonctionnement

1. À chaque chargement de `index.php`, un token CSRF est généré via `bin2hex(random_bytes(32))` (256 bits d'entropie) et stocké en session.
2. Le token est injecté dans la page HTML et envoyé par le JavaScript dans l'en-tête `X-CSRF-Token` à chaque requête POST.
3. `api.php` vérifie le token avec `hash_equals()` — comparaison en temps constant pour éviter les attaques temporelles.

### Réponse en cas d'échec

```
HTTP 403 Forbidden
{"error": "Token CSRF invalide ou manquant."}
```

---

## 3. En-têtes HTTP de sécurité

**Fonction** : `sendSecurityHeaders(string $nonce)` — `security.php:59-78`

Envoyés à chaque réponse par `index.php` (avec nonce) et `api.php` (sans nonce).

| En-tête | Valeur | Protection |
|---|---|---|
| `Content-Security-Policy` | voir ci-dessous | Injection de scripts, clickjacking, data exfiltration |
| `X-Content-Type-Options` | `nosniff` | MIME-sniffing |
| `X-Frame-Options` | `DENY` | Clickjacking via iframe |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Fuite d'URL dans le Referer |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=(), payment=()` | Accès aux APIs sensibles du navigateur |

### Content-Security-Policy détaillée

```
default-src 'self';
script-src  'self' 'nonce-<NONCE>';
style-src   'self' 'unsafe-inline';
img-src     'self' data:;
connect-src 'self';
frame-ancestors 'none';
base-uri    'self';
form-action 'self'
```

- **`script-src`** : seuls les scripts servis par l'origine et portant le nonce unique sont autorisés. Le nonce est généré par `base64_encode(random_bytes(16))` à chaque chargement de page.
- **`connect-src 'self'`** : les requêtes `fetch` ne peuvent cibler que la même origine (bloque l'exfiltration de données).
- **`frame-ancestors 'none'`** : la page ne peut pas être intégrée dans une iframe (remplace `X-Frame-Options`).

---

## 4. Politique CORS

**Fonctions** : `getAllowedOrigin()`, `sendCorsHeaders()` — `security.php:86-107`

Les requêtes cross-origin sont restreintes à la même origine que le serveur.

| En-tête | Valeur |
|---|---|
| `Access-Control-Allow-Origin` | Origine calculée dynamiquement ou variable d'env `ALLOWED_ORIGIN` |
| `Access-Control-Allow-Methods` | `POST, OPTIONS` |
| `Access-Control-Allow-Headers` | `Content-Type, X-CSRF-Token` |
| `Access-Control-Max-Age` | `86400` (cache le pré-vol CORS 24 h) |
| `Vary` | `Origin` |

Les requêtes de pré-vol `OPTIONS` reçoivent un `HTTP 204` et sont traitées immédiatement sans exécuter le reste de la logique.

**Configuration** : par défaut l'origine autorisée est `<scheme>://<HTTP_HOST>`. Elle peut être surchargée avec la variable d'environnement `ALLOWED_ORIGIN`.

---

## 5. Rate limiting

**Fonctions** : `checkRateLimit()`, `getClientIp()` — `security.php:117-166`

### Mécanisme

- L'identification du client repose **uniquement sur `REMOTE_ADDR`** pour éviter le spoofing via `X-Forwarded-For`.
- L'adresse IP est hashée en SHA-256 avant d'être utilisée comme nom de fichier (anonymisation).
- Les données de rate limiting sont stockées dans des fichiers JSON dans `sys_get_temp_dir()/chatbot_rl/`, avec permissions `0700`.
- Les accès fichier utilisent `LOCK_EX` pour éviter les race conditions.

### Comportement

| Situation | Action |
|---|---|
| Quota non atteint | Requête autorisée, timestamp enregistré |
| Quota dépassé | IP bloquée 5 minutes, `HTTP 429` retourné |
| IP actuellement bloquée | `HTTP 429` retourné immédiatement |

En cas de blocage, l'en-tête `Retry-After: 300` est envoyé.

```
HTTP 429 Too Many Requests
Retry-After: 300
{"error": "Trop de requêtes. Veuillez réessayer dans quelques minutes."}
```

**Configuration** : voir `RATE_LIMIT_REQUESTS` et `RATE_LIMIT_WINDOW` dans `config.php`.

---

## 6. Validation et assainissement des messages

**Fonction** : `validateAndSanitizeMessages()` — `security.php:181-225`

Chaque tableau de messages envoyé par le client passe par cette validation avant d'être transmis à Azure OpenAI.

| Vérification | Détail |
|---|---|
| Limite de l'historique | Seuls les `MAX_HISTORY_MESSAGES` derniers messages sont conservés |
| Rôles autorisés | Uniquement `user` et `assistant` (le rôle `system` ne peut pas être injecté) |
| Type du contenu | `content` doit être une chaîne de caractères |
| Suppression de caractères dangereux | Octets nuls et caractères de contrôle (`\x00-\x08`, `\x0B`, `\x0C`, `\x0E-\x1F`, `\x7F`) supprimés |
| Troncature | Contenu tronqué à `MAX_MESSAGE_LENGTH` caractères (via `mb_strlen`/`mb_substr`) |
| Messages vides | Ignorés après assainissement |

---

## 7. Validation de la requête HTTP

Effectuée dans `api.php` avant toute autre logique.

| Contrôle | Code retour |
|---|---|
| Méthode doit être `POST` | `HTTP 405` |
| `Content-Type` doit contenir `application/json` | `HTTP 415` |
| Token CSRF valide | `HTTP 403` |
| IP non bloquée par le rate limiter | `HTTP 429` |
| Corps de la requête ≤ 512 Ko | `HTTP 413` |
| Corps JSON valide | `HTTP 400` |
| Champ `messages` présent et tableau | `HTTP 400` |
| Au moins un message valide après assainissement | `HTTP 400` |

---

## 8. Appel à Azure OpenAI

Effectué dans `api.php:87-110`.

| Paramètre cURL | Valeur | Rôle |
|---|---|---|
| `CURLOPT_SSL_VERIFYPEER` | `true` | Vérifie le certificat TLS du serveur |
| `CURLOPT_SSL_VERIFYHOST` | `2` | Vérifie que le CN du certificat correspond au host |
| `CURLOPT_TIMEOUT` | `30 s` | Limite la durée de la requête |

En cas d'erreur cURL ou de code HTTP différent de 200, l'erreur détaillée est journalisée côté serveur via `error_log()` mais **jamais exposée au client**, qui reçoit un message générique :

```
HTTP 502 Bad Gateway
{"error": "Impossible de contacter le service IA. Veuillez réessayer."}
```

---

## 9. Paramètres configurables

Définis dans `config.php`, surchargeable via variables d'environnement.

| Constante | Variable d'env | Défaut | Description |
|---|---|---|---|
| `AZURE_ENDPOINT` | `AZURE_OPENAI_ENDPOINT` | — | URL de l'endpoint Azure OpenAI |
| `AZURE_API_KEY` | `AZURE_OPENAI_API_KEY` | — | Clé d'API Azure OpenAI |
| `AZURE_DEPLOYMENT` | `AZURE_OPENAI_DEPLOYMENT` | `gpt-4` | Nom du déploiement |
| `AZURE_API_VERSION` | `AZURE_OPENAI_API_VERSION` | `2024-02-01` | Version de l'API |
| `MAX_HISTORY_MESSAGES` | — | `50` | Taille maximale de l'historique |
| `MAX_MESSAGE_LENGTH` | — | `4000` | Longueur maximale d'un message (caractères) |
| `RATE_LIMIT_REQUESTS` | — | `20` | Requêtes autorisées par fenêtre |
| `RATE_LIMIT_WINDOW` | — | `60` | Durée de la fenêtre (secondes) |
| — | `ALLOWED_ORIGIN` | Origine calculée dynamiquement | Origine CORS autorisée |
