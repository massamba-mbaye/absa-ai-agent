<?php
session_start(); // Démarrer la session

// Remplacez par votre clé API Mistral AI
$apiKey = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';

// Remplacez par l'identifiant de votre agent
$agentId = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';

// Créer le dossier de conversations s'il n'existe pas
$conversationDir = '../conversations';
if (!file_exists($conversationDir)) {
    mkdir($conversationDir, 0755, true);
}

// Générer un ID de session unique s'il n'existe pas déjà
if (!isset($_SESSION['conversation_id'])) {
    $_SESSION['conversation_id'] = uniqid('conv_', true);
}
$conversationId = $_SESSION['conversation_id'];

// Vérifier si la session contient déjà une conversation
if (!isset($_SESSION['conversation'])) {
    $_SESSION['conversation'] = []; // Initialiser l'historique
}

// Récupérer le message de l'utilisateur
$input = json_decode(file_get_contents('php://input'), true);
$userMessage = $input['userMessage'] ?? '';

// Vérifier si le message de l'utilisateur est vide
if (empty($userMessage)) {
    echo json_encode(['success' => false, 'error' => 'Le message de l\'utilisateur est vide.']);
    exit;
}

// Ajouter le message utilisateur à la session
$_SESSION['conversation'][] = ['role' => 'user', 'content' => $userMessage];

// Préparer la requête avec l'historique de la conversation
$data = [
    'agent_id' => $agentId,
    'messages' => $_SESSION['conversation'] // Envoyer tout l'historique
];

$apiUrl = 'https://api.mistral.ai/v1/agents/completions';

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

// Exécuter la requête et obtenir la réponse
$response = curl_exec($ch);
curl_close($ch);

// Vérifier si la réponse est valide
if ($response === false) {
    echo json_encode(['success' => false, 'error' => 'Erreur lors de la communication avec l\'API Mistral AI.']);
    exit;
}

// Décoder la réponse JSON
$responseData = json_decode($response, true);

// Vérifier si la réponse contient le contenu attendu
if (isset($responseData['choices'][0]['message']['content'])) {
    $botResponse = $responseData['choices'][0]['message']['content'];

    // Ajouter la réponse du bot à la session
    $_SESSION['conversation'][] = ['role' => 'assistant', 'content' => $botResponse];

    // Enregistrer la conversation dans un fichier unique par session
    $conversationFile = "$conversationDir/$conversationId.json";
    file_put_contents($conversationFile, json_encode($_SESSION['conversation'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Ajouter au fichier global de logs
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [ID: $conversationId] Utilisateur: $userMessage\n[$timestamp] [ID: $conversationId] Absa: $botResponse\n\n";
    file_put_contents("$conversationDir/conversations-log.txt", $logMessage, FILE_APPEND);

    echo json_encode(['success' => true, 'response' => $botResponse]);
} else {
    echo json_encode(['success' => false, 'error' => 'Réponse invalide du serveur.']);
}