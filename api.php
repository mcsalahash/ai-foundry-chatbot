<?php
/**
 * api.php — Backend du chatbot GPT-4 via Azure AI Foundry
 * 
 * Reçoit les messages du frontend et interroge l'API Azure OpenAI.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

// Lecture du body JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['messages']) || !is_array($input['messages'])) {
    echo json_encode(['error' => 'Paramètre messages manquant ou invalide.']);
    exit;
}

$messages = $input['messages'];

// Ajout du system prompt
array_unshift($messages, [
    'role'    => 'system',
    'content' => SYSTEM_PROMPT
]);

// Appel Azure OpenAI
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
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['error' => 'Erreur cURL : ' . $curlError]);
    exit;
}

$data = json_decode($response, true);

if ($httpCode !== 200) {
    $errorMsg = $data['error']['message'] ?? 'Erreur inconnue (HTTP ' . $httpCode . ')';
    echo json_encode(['error' => $errorMsg]);
    exit;
}

$reply = $data['choices'][0]['message']['content'] ?? 'Réponse vide.';

echo json_encode(['reply' => $reply]);
