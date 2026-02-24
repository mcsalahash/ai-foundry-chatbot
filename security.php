<?php
/**
 * security.php — Mesures de sécurité centralisées du chatbot
 *
 * Fournit : session sécurisée, CSRF, rate limiting, en-têtes HTTP, validation.
 */

// ── Session sécurisée ────────────────────────────────────────────────────────

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

// ── CSRF ─────────────────────────────────────────────────────────────────────

/**
 * Génère (ou récupère) le token CSRF de la session courante.
 */
function getCsrfToken(): string
{
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie le token CSRF transmis dans l'en-tête X-CSRF-Token.
 * Utilise hash_equals pour éviter les attaques temporelles.
 */
function verifyCsrfToken(): bool
{
    startSecureSession();
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// ── En-têtes de sécurité HTTP ────────────────────────────────────────────────

/**
 * Envoie les en-têtes de sécurité HTTP recommandés.
 *
 * @param string $nonce Nonce CSP pour les scripts inline (optionnel).
 */
function sendSecurityHeaders(string $nonce = ''): void
{
    $scriptSrc = "'self'" . ($nonce !== '' ? " 'nonce-{$nonce}'" : '');

    header(
        "Content-Security-Policy: "
        . "default-src 'self'; "
        . "script-src {$scriptSrc}; "
        . "style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data:; "
        . "connect-src 'self'; "
        . "frame-ancestors 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self'"
    );
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
}

// ── CORS (même origine uniquement) ───────────────────────────────────────────

/**
 * Retourne l'origine autorisée (même origine que le serveur).
 */
function getAllowedOrigin(): string
{
    $configured = getenv('ALLOWED_ORIGIN');
    if (!empty($configured)) {
        return $configured;
    }
    $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

/**
 * Envoie les en-têtes CORS restreints à la même origine.
 */
function sendCorsHeaders(): void
{
    $origin = getAllowedOrigin();
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    header('Access-Control-Max-Age: 86400');
}

// ── Rate limiting (stockage fichier) ─────────────────────────────────────────

/**
 * Vérifie si l'IP dépasse la limite de débit.
 * En cas de dépassement, bloque l'IP pendant 5 minutes.
 *
 * @return bool true si la requête est autorisée, false si bloquée.
 */
function checkRateLimit(string $ip, int $maxRequests, int $windowSeconds): bool
{
    $dir = sys_get_temp_dir() . '/chatbot_rl';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    $file = $dir . '/' . hash('sha256', $ip) . '.json';
    $now  = time();

    $data = ['requests' => [], 'blocked_until' => 0];
    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
    }

    // IP bloquée ?
    if ((int)($data['blocked_until'] ?? 0) > $now) {
        return false;
    }

    // Nettoyer les entrées hors fenêtre
    $data['requests'] = array_values(
        array_filter((array)$data['requests'], fn($ts) => $ts > $now - $windowSeconds)
    );

    // Quota dépassé → bloquer 5 minutes
    if (count($data['requests']) >= $maxRequests) {
        $data['blocked_until'] = $now + 300;
        @file_put_contents($file, json_encode($data), LOCK_EX);
        return false;
    }

    $data['requests'][] = $now;
    @file_put_contents($file, json_encode($data), LOCK_EX);
    return true;
}

/**
 * Retourne l'adresse IP du client (REMOTE_ADDR uniquement pour éviter le spoofing).
 */
function getClientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// ── Validation et assainissement des messages ────────────────────────────────

/**
 * Valide et assainit le tableau de messages :
 *  - Limite le nombre de messages à MAX_HISTORY_MESSAGES
 *  - N'autorise que les rôles 'user' et 'assistant'
 *  - Tronque les contenus dépassant MAX_MESSAGE_LENGTH
 *  - Supprime les caractères de contrôle dangereux
 *  - Ignore les messages vides
 *
 * @return array Messages validés et assainis.
 */
function validateAndSanitizeMessages(array $messages): array
{
    $allowedRoles = ['user', 'assistant'];
    $maxMessages  = MAX_HISTORY_MESSAGES;
    $maxLength    = MAX_MESSAGE_LENGTH;

    // Garder seulement les N derniers messages
    if (count($messages) > $maxMessages) {
        $messages = array_slice($messages, -$maxMessages);
    }

    $validated = [];
    foreach ($messages as $msg) {
        if (!is_array($msg)) {
            continue;
        }
        if (!isset($msg['role'], $msg['content'])) {
            continue;
        }
        if (!in_array($msg['role'], $allowedRoles, true)) {
            continue;
        }
        if (!is_string($msg['content'])) {
            continue;
        }

        $content = $msg['content'];

        // Supprimer les octets nuls et les caractères de contrôle
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);

        // Tronquer si trop long
        if (mb_strlen($content) > $maxLength) {
            $content = mb_substr($content, 0, $maxLength);
        }

        // Ignorer les messages vides après assainissement
        if (trim($content) === '') {
            continue;
        }

        $validated[] = ['role' => $msg['role'], 'content' => $content];
    }

    return $validated;
}
