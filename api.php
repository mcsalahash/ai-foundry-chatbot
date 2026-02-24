<?php
/**
 * api.php — Backend du chatbot GPT-4 via Azure AI Foundry
 *
 * Reçoit les messages du frontend et interroge l'API Azure OpenAI.
 */

require_once 'config.php';
require_once 'security.php';

// ── En-têtes de base ──────────────────────────────────────────────────────────
header('Content-Type: application/json');
sendSecurityHeaders();
sendCorsHeaders();

// ── Pré-vol CORS (OPTIONS) ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Utilitaire : réponse d'erreur JSON ────────────────────────────────────────
function jsonError(string $message, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

// ── Validation de la méthode HTTP ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Méthode non autorisée.', 405);
}

// ── Validation du Content-Type ────────────────────────────────────────────────
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') === false) {
    jsonError('Content-Type application/json requis.', 415);
}

// ── Vérification du token CSRF ────────────────────────────────────────────────
if (!verifyCsrfToken()) {
    jsonError('Token CSRF invalide ou manquant.', 403);
}

// ── Rate limiting par IP ──────────────────────────────────────────────────────
$clientIp = getClientIp();
if (!checkRateLimit($clientIp, RATE_LIMIT_REQUESTS, RATE_LIMIT_WINDOW)) {
    header('Retry-After: 300');
    jsonError('Trop de requêtes. Veuillez réessayer dans quelques minutes.', 429);
}

// ── Lecture et validation du corps de la requête ──────────────────────────────
$rawBody = file_get_contents('php://input');
if (strlen($rawBody) > 512 * 1024) { // 512 Ko max
    jsonError('Corps de la requête trop volumineux.', 413);
}

$input = json_decode($rawBody, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    jsonError('Corps JSON invalide.');
}

if (!isset($input['messages']) || !is_array($input['messages'])) {
    jsonError('Paramètre messages manquant ou invalide.');
}

// ── Validation et assainissement des messages ─────────────────────────────────
$messages = validateAndSanitizeMessages($input['messages']);
if (empty($messages)) {
    jsonError('Aucun message valide fourni.');
}

// ── Ajout du system prompt ────────────────────────────────────────────────────
array_unshift($messages, [
    'role'    => 'system',
    'content' => SYSTEM_PROMPT,
]);

// ── Appel Azure OpenAI ────────────────────────────────────────────────────────
$payload = json_encode([
    'messages'    => $messages,
    'max_tokens'  => 1024,
    'temperature' => 0.7,
]);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => AZURE_ENDPOINT . '/openai/deployments/' . AZURE_DEPLOYMENT . '/chat/completions?api-version=' . AZURE_API_VERSION,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'api-key: ' . AZURE_API_KEY,
    ],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    // Journaliser l'erreur côté serveur sans l'exposer au client
    error_log('[chatbot] cURL error: ' . $curlError);
    jsonError('Impossible de contacter le service IA. Veuillez réessayer.', 502);
}

$data = json_decode($response, true);

if ($httpCode !== 200) {
    error_log('[chatbot] Azure OpenAI HTTP ' . $httpCode . ': ' . $response);
    jsonError('Erreur du service IA. Veuillez réessayer.', 502);
}

$reply = $data['choices'][0]['message']['content'] ?? 'Réponse vide.';

echo json_encode(['reply' => $reply]);
