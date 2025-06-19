<?php
session_start();

// Génération d'un nouvel identifiant de conversation
$_SESSION['conversation_id'] = uniqid('conv_', true);

// Réinitialisation de l'historique de conversation
$_SESSION['conversation'] = [];

// Réponse JSON indiquant le succès
echo json_encode(['success' => true]);