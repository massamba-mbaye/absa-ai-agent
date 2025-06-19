<?php
// get_conversation.php

// Headers pour AJAX
header('Content-Type: application/json');

// Vérifier que l'ID est fourni
if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'ID de conversation non fourni']);
    exit;
}

$conversationId = $_GET['id'];
$conversationsDir = '../conversations/'; // Même chemin que dans l'index.php

// Nettoyer l'ID pour éviter les attaques par traversée de chemin
$conversationId = str_replace(['../', '/', '\\'], '', $conversationId);

// Construire le chemin du fichier
$filePath = $conversationsDir . $conversationId . '.json';

// Vérifier si le fichier existe
if (!file_exists($filePath)) {
    echo json_encode(['error' => 'Conversation non trouvée']);
    exit;
}

// Lire le contenu du fichier
$content = file_get_contents($filePath);
$conversationData = json_decode($content, true);

if (!$conversationData) {
    echo json_encode(['error' => 'Erreur de lecture du fichier de conversation']);
    exit;
}

// Récupérer les métadonnées de la conversation depuis le log
$logFile = $conversationsDir . 'conversations-log.txt';
$timestamp = null;
$duration = null;

if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    preg_match_all('/\[(.*?)\].*?\[ID: ' . preg_quote($conversationId) . '\]/', $logContent, $matches);
    
    if (isset($matches[1]) && !empty($matches[1])) {
        $firstTime = strtotime($matches[1][0]);
        $timestamp = date('d/m/Y H:i', $firstTime);
        
        if (count($matches[1]) >= 2) {
            $lastTime = strtotime(end($matches[1]));
            $durationSeconds = $lastTime - $firstTime;
            $minutes = floor($durationSeconds / 60);
            $seconds = $durationSeconds % 60;
            $duration = $minutes . 'm ' . $seconds . 's';
        }
    }
}

// Compter les messages par type
$userMessages = 0;
$assistantMessages = 0;

foreach ($conversationData as $message) {
    if ($message['role'] === 'user') {
        $userMessages++;
    } elseif ($message['role'] === 'assistant') {
        $assistantMessages++;
    }
}

// Formatter les messages pour l'affichage
$formattedMessages = [];
foreach ($conversationData as $index => $message) {
    // Ajouter un horodatage fictif basé sur l'index des messages (puisque nous n'avons pas d'horodatage réel par message)
    // Dans un système réel, vous pourriez avoir des horodatages pour chaque message
    $messageTimestamp = null;
    if ($timestamp) {
        $baseTime = strtotime($timestamp);
        $messageTime = $baseTime + ($index * 60); // Ajouter une minute par message de façon fictive
        $messageTimestamp = date('H:i', $messageTime);
    }
    
    $formattedMessages[] = [
        'role' => $message['role'],
        'content' => $message['content'],
        'timestamp' => $messageTimestamp
    ];
}

// Préparer la réponse
$response = [
    'id' => $conversationId,
    'date' => $timestamp,
    'duration' => $duration,
    'messageCount' => count($conversationData),
    'userMessages' => $userMessages,
    'assistantMessages' => $assistantMessages,
    'messages' => $formattedMessages
];

echo json_encode($response);
?>