<?php
// export_conversation.php

// Vérifier que l'ID est fourni
if (!isset($_GET['id'])) {
    header('Content-Type: text/plain');
    echo 'Erreur: ID de conversation non fourni';
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
    header('Content-Type: text/plain');
    echo 'Erreur: Conversation non trouvée';
    exit;
}

// Lire le contenu du fichier
$content = file_get_contents($filePath);
$conversationData = json_decode($content, true);

if (!$conversationData) {
    header('Content-Type: text/plain');
    echo 'Erreur: Impossible de décoder le fichier de conversation';
    exit;
}

// Préparer le contenu pour l'export
$exportContent = "Conversation ID: $conversationId\n";
$exportContent .= "Date d'export: " . date('d/m/Y H:i:s') . "\n\n";

foreach ($conversationData as $message) {
    $role = $message['role'] === 'user' ? 'Utilisateur' : 'Assistant';
    $exportContent .= "[$role]\n";
    $exportContent .= $message['content'] . "\n\n";
}

// Configurer les en-têtes pour le téléchargement
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="conversation_' . $conversationId . '.txt"');
header('Content-Length: ' . strlen($exportContent));
header('Cache-Control: no-store, no-cache');

// Envoyer le contenu
echo $exportContent;
?>