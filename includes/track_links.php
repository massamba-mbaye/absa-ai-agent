<?php
// track_links.php
header('Content-Type: application/json');

// Vérifier la méthode de requête
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

// Obtenir le contenu JSON de la requête
$data = json_decode(file_get_contents('php://input'), true);

// Vérifier les données requises
if (!isset($data['event_type']) || !isset($data['conversation_id']) || !isset($data['link'])) {
    echo json_encode(['success' => false, 'error' => 'Données incomplètes']);
    exit;
}

// Sanitiser les entrées
$eventType = filter_var($data['event_type'], FILTER_SANITIZE_STRING);
$conversationId = filter_var($data['conversation_id'], FILTER_SANITIZE_STRING);
$link = filter_var($data['link'], FILTER_SANITIZE_URL);
$messageId = isset($data['message_id']) ? filter_var($data['message_id'], FILTER_SANITIZE_STRING) : '';

// Vérifier le type d'événement
if ($eventType !== 'link_detected' && $eventType !== 'link_clicked') {
    echo json_encode(['success' => false, 'error' => 'Type d\'événement non valide']);
    exit;
}

// Créer une entrée de journal
$logEntry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'event_type' => $eventType,
    'conversation_id' => $conversationId,
    'message_id' => $messageId,
    'link' => $link,
    'ip' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
];

// Chemin vers le fichier de journal des liens
$logFile = '../logs/links_log.json';
$logDir = dirname($logFile);

// Créer le répertoire logs s'il n'existe pas
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Lire le fichier de journal existant ou créer un nouveau tableau
$logs = [];
if (file_exists($logFile)) {
    $fileContent = file_get_contents($logFile);
    if (!empty($fileContent)) {
        $logs = json_decode($fileContent, true) ?: [];
    }
}

// Ajouter la nouvelle entrée
$logs[] = $logEntry;

// Écrire dans le fichier de journal
if (file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT))) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Erreur d\'écriture dans le fichier de journal']);
}
?>