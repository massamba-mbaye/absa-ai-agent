<?php
// Définition des fonctions d'analyse des conversations
$conversationsDir = '../conversations/'; // Chemin ajusté pour accéder au dossier conversations

function scanConversationFiles($directory = '../conversations/') {
    $conversationFiles = [];
    $logFile = null;
    
    // Vérifier que le répertoire existe
    if (!is_dir($directory)) {
        return [
            'conversations' => [],
            'log' => null,
            'error' => "Le répertoire $directory n'existe pas"
        ];
    }
    
    // Scan du répertoire pour trouver tous les fichiers
    $files = scandir($directory);
    
    foreach ($files as $file) {
        if (strpos($file, 'conv_') === 0 && pathinfo($file, PATHINFO_EXTENSION) === 'json') {
            $conversationFiles[] = $file;
        } elseif ($file === 'conversations-log.txt') {
            $logFile = $file;
        }
    }
    
    return [
        'conversations' => $conversationFiles,
        'log' => $logFile,
        'error' => null
    ];
}

function parseConversationFile($filename, $basePath = '../conversations/') {
    $fullPath = $basePath . $filename;
    
    if (!file_exists($fullPath)) {
        return null;
    }
    
    $content = file_get_contents($fullPath);
    $conversation = json_decode($content, true);
    
    if (!$conversation) {
        return null;
    }
    
    // Extraire l'ID de conversation du nom de fichier
    $conversationId = str_replace('.json', '', $filename);
    
    // Analyser les messages
    $messageCount = count($conversation);
    $userMessages = 0;
    $assistantMessages = 0;
    
    foreach ($conversation as $message) {
        if ($message['role'] === 'user') {
            $userMessages++;
        } elseif ($message['role'] === 'assistant') {
            $assistantMessages++;
        }
    }
    
    // Extraire la date de la conversation à partir du log
    $timestamp = extractTimestampFromLog($conversationId);
    
    return [
        'id' => $conversationId,
        'timestamp' => $timestamp,
        'messageCount' => $messageCount,
        'userMessages' => $userMessages,
        'assistantMessages' => $assistantMessages,
        'duration' => calculateDuration($conversationId)
    ];
}

function extractTimestampFromLog($conversationId, $logFile = '../conversations/conversations-log.txt') {
    if (!file_exists($logFile)) {
        return null;
    }
    
    $logContent = file_get_contents($logFile);
    preg_match('/\[(.*?)\].*?\[ID: ' . preg_quote($conversationId) . '\]/', $logContent, $matches);
    
    if (isset($matches[1])) {
        return strtotime($matches[1]);
    }
    
    return null;
}

function calculateDuration($conversationId, $logFile = '../conversations/conversations-log.txt') {
    if (!file_exists($logFile)) {
        return null;
    }
    
    $logContent = file_get_contents($logFile);
    preg_match_all('/\[(.*?)\].*?\[ID: ' . preg_quote($conversationId) . '\]/', $logContent, $matches);
    
    if (isset($matches[1]) && count($matches[1]) >= 2) {
        $firstTime = strtotime($matches[1][0]);
        $lastTime = strtotime(end($matches[1]));
        
        return $lastTime - $firstTime;
    }
    
    return null;
}

function analyzeAllConversations($files, $basePath = '../conversations/') {
    $conversations = [];
    $totalMessages = 0;
    $totalUserMessages = 0;
    $totalAssistantMessages = 0;
    
    foreach ($files as $file) {
        $conversation = parseConversationFile($file, $basePath);
        
        if ($conversation) {
            $conversations[] = $conversation;
            $totalMessages += $conversation['messageCount'];
            $totalUserMessages += $conversation['userMessages'];
            $totalAssistantMessages += $conversation['assistantMessages'];
        }
    }
    
    // Trier les conversations par date (plus récentes en premier)
    usort($conversations, function($a, $b) {
        return ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0);
    });
    
    return [
        'conversations' => $conversations,
        'metrics' => [
            'totalConversations' => count($conversations),
            'totalMessages' => $totalMessages,
            'totalUserMessages' => $totalUserMessages,
            'totalAssistantMessages' => $totalAssistantMessages,
            'averageMessagesPerConversation' => count($conversations) > 0 ? round($totalMessages / count($conversations), 1) : 0,
            'responseRate' => $totalUserMessages > 0 ? round(($totalAssistantMessages / $totalUserMessages) * 100, 1) : 0
        ]
    ];
}

function getConversationsByPeriod($conversations, $period = 'day') {
    $result = [];
    $now = time();
    
    foreach ($conversations as $conversation) {
        $timestamp = $conversation['timestamp'] ?? null;
        
        if (!$timestamp) {
            continue;
        }
        
        switch ($period) {
            case 'day':
                $key = date('Y-m-d', $timestamp);
                break;
            case 'week':
                $key = date('Y-W', $timestamp);
                break;
            case 'month':
                $key = date('Y-m', $timestamp);
                break;
            default:
                $key = date('Y-m-d', $timestamp);
        }
        
        if (!isset($result[$key])) {
            $result[$key] = [
                'count' => 0,
                'messages' => 0
            ];
        }
        
        $result[$key]['count']++;
        $result[$key]['messages'] += $conversation['messageCount'];
    }
    
    return $result;
}

function getTopics($conversations, $logFile = '../conversations/conversations-log.txt', $topCount = 10, $minLength = 4) {
    if (!file_exists($logFile)) {
        return [];
    }
    
    // Récupérer le contenu du fichier de log
    $logContent = file_get_contents($logFile);
    
    // Convertir tout en minuscules pour ignorer la casse
    $logContent = mb_strtolower($logContent, 'UTF-8');
    
    // Parcourir chaque fichier de conversation pour analyser le contenu réel des messages
    $allContent = '';
    foreach ($conversations as $conversation) {
        $conversationId = $conversation['id'];
        $conversationFile = '../conversations/' . $conversationId . '.json';
        
        if (file_exists($conversationFile)) {
            $content = file_get_contents($conversationFile);
            $convData = json_decode($content, true);
            
            if ($convData) {
                foreach ($convData as $message) {
                    // Ne compter que les messages des utilisateurs
                    if ($message['role'] === 'user') {
                        $allContent .= ' ' . mb_strtolower($message['content'], 'UTF-8');
                    }
                }
            }
        }
    }
    
    // Liste de mots à exclure (mots vides/stop words en français)
    $stopWords = [
        'le', 'la', 'les', 'un', 'une', 'des', 'ce', 'cette', 'ces', 'mon', 'ma', 'mes', 'ton', 'ta', 'tes', 
        'son', 'sa', 'ses', 'notre', 'nos', 'votre', 'vos', 'leur', 'leurs', 'du', 'de', 'à', 'au', 'aux',
        'en', 'dans', 'sur', 'sous', 'avec', 'pour', 'par', 'et', 'ou', 'que', 'qui', 'quoi', 'dont', 'où',
        'comment', 'pourquoi', 'quand', 'je', 'tu', 'il', 'elle', 'on', 'nous', 'vous', 'ils', 'elles',
        'est', 'sont', 'était', 'étaient', 'sera', 'seront', 'été', 'être', 'avoir', 'fait', 'faire',
        'plus', 'moins', 'peu', 'très', 'trop', 'beaucoup', 'pas', 'ne', 'non', 'oui', 'si',
        'alors', 'mais', 'car', 'donc', 'or', 'ni', 'cependant', 'toutefois', 'néanmoins',
        'ceci', 'cela', 'ça', 'celui', 'celle', 'ceux', 'celles', 'celui-ci', 'celle-ci',
        'est-ce', 'qu\'il', 'qu\'elle', 'qu\'on', 'd\'un', 'd\'une', 's\'il', 's\'ils',
        'peut', 'peuvent', 'pouvez', 'merci', 'bonjour', 'bonsoir', 'salut'
    ];
    
    // Nettoyer le texte et extraire les mots
    $cleanContent = preg_replace('/[^\p{L}\s]/u', ' ', $allContent); // Remplacer tout ce qui n'est pas une lettre par un espace
    $words = preg_split('/\s+/', $cleanContent, -1, PREG_SPLIT_NO_EMPTY);
    
    // Compter les occurrences de chaque mot
    $wordCounts = [];
    foreach ($words as $word) {
        // Ne considérer que les mots suffisamment longs et qui ne sont pas des stop words
        if (mb_strlen($word, 'UTF-8') >= $minLength && !in_array($word, $stopWords)) {
            if (!isset($wordCounts[$word])) {
                $wordCounts[$word] = 0;
            }
            $wordCounts[$word]++;
        }
    }
    
    // Trier par nombre d'occurrences décroissant
    arsort($wordCounts);
    
    // Ne garder que les X premiers mots
    return array_slice($wordCounts, 0, $topCount, true);
}

// Traitement principal

$files = scanConversationFiles($conversationsDir);
$data = ['metrics' => ['totalConversations' => 0, 'totalMessages' => 0, 'totalUserMessages' => 0, 'totalAssistantMessages' => 0, 'averageMessagesPerConversation' => 0, 'responseRate' => 0], 'conversations' => []];
$conversationsByDay = [];
$topics = [];
$error = $files['error'];

if (!$error) {
    $data = analyzeAllConversations($files['conversations'], $conversationsDir);
    $conversationsByDay = getConversationsByPeriod($data['conversations'], 'day');
    $topics = getTopics($data['conversations'], $conversationsDir . $files['log']);
}

// Préparation des données pour les graphiques
$chartData = [
    'conversations' => [],
    'messages' => []
];

// Derniers 7 jours
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dateFormatted = date('d/m', strtotime("-$i days"));
    
    $chartData['conversations'][] = [
        'date' => $dateFormatted,
        'count' => isset($conversationsByDay[$date]) ? $conversationsByDay[$date]['count'] : 0
    ];
    
    $chartData['messages'][] = [
        'date' => $dateFormatted,
        'count' => isset($conversationsByDay[$date]) ? $conversationsByDay[$date]['messages'] : 0
    ];
}

// Conversion en JSON pour JavaScript
$chartDataJson = json_encode($chartData);
$topicsJson = json_encode($topics);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord des conversations</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/locale/fr.js"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
	<!-- Ajoutez ce code après le header dans index.php -->
	<header class="mb-10">
	    <h1 class="text-3xl font-bold text-gray-800">Tableau de Bord des Conversations</h1>
	    <p class="text-gray-600">Analyse des conversations • Mis à jour le <?php echo date('d/m/Y à H:i'); ?></p>
	    <div class="mt-4 flex space-x-4">
	        <a href="index.php" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm font-medium">
	            Conversations
	        </a>
	        <a href="link_analytics.php" class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700 text-sm font-medium">
	            Analyse des Liens
	        </a>
	    </div>
	    <?php if ($error): ?>
	    <div class="mt-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
	        <p class="font-bold">Erreur :</p>
	        <p><?php echo $error; ?></p>
	    </div>
	    <?php endif; ?>
	</header>
        
        <!-- Métriques Principales -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-500 text-sm font-medium">Conversations totales</h3>
                <p class="text-3xl font-bold text-gray-800"><?php echo $data['metrics']['totalConversations']; ?></p>
                <div class="mt-2">
                    <span class="text-green-500 text-sm font-medium">
                        <span class="text-green-500">+<?php echo isset($conversationsByDay[date('Y-m-d')]['count']) ? $conversationsByDay[date('Y-m-d')]['count'] : 0; ?></span> aujourd'hui
                    </span>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-500 text-sm font-medium">Messages échangés</h3>
                <p class="text-3xl font-bold text-gray-800"><?php echo $data['metrics']['totalMessages']; ?></p>
                <div class="mt-2 flex items-center">
                    <span class="text-sm text-gray-600">
                        Moy. <?php echo $data['metrics']['averageMessagesPerConversation']; ?> par conversation
                    </span>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-500 text-sm font-medium">Taux de réponse</h3>
                <p class="text-3xl font-bold text-gray-800"><?php echo $data['metrics']['responseRate']; ?>%</p>
                <div class="mt-2 flex items-center">
                    <span class="text-sm text-gray-600">
                        <?php echo $data['metrics']['totalUserMessages']; ?> messages utilisateurs
                    </span>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-500 text-sm font-medium">Durée moyenne</h3>
                <?php
                $totalDuration = 0;
                $validDurations = 0;
                
                foreach ($data['conversations'] as $conv) {
                    if (isset($conv['duration']) && $conv['duration']) {
                        $totalDuration += $conv['duration'];
                        $validDurations++;
                    }
                }
                
                $avgDuration = $validDurations > 0 ? round($totalDuration / $validDurations / 60, 1) : 0;
                ?>
                <p class="text-3xl font-bold text-gray-800"><?php echo $avgDuration; ?> min</p>
                <div class="mt-2 flex items-center">
                    <span class="text-sm text-gray-600">
                        Temps moyen par conversation
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Graphiques -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-700 font-medium mb-4">Conversations par jour</h3>
                <canvas id="conversationsChart" height="300"></canvas>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-700 font-medium mb-4">Messages par jour</h3>
                <canvas id="messagesChart" height="300"></canvas>
            </div>
        </div>
        
        <!-- Répartition et Sujets -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-700 font-medium mb-4">Répartition des messages</h3>
                <canvas id="distributionChart" height="300"></canvas>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-700 font-medium mb-4">Sujets les plus fréquents</h3>
                <canvas id="topicsChart" height="300"></canvas>
            </div>
        </div>
        
        <!-- Dernières Conversations -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-gray-700 font-medium">Dernières Conversations</h3>
                <a href="#" class="text-blue-500 text-sm hover:underline">Voir toutes</a>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Messages</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Durée</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php 
                        $recentConversations = array_slice($data['conversations'], 0, 5);
                        foreach ($recentConversations as $conv): 
                        ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo substr($conv['id'], 0, 15) . '...'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo isset($conv['timestamp']) && $conv['timestamp'] ? date('d/m/Y H:i', $conv['timestamp']) : 'N/A'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $conv['messageCount']; ?> 
                                <span class="text-xs text-gray-400">(<?php echo $conv['userMessages']; ?> util., <?php echo $conv['assistantMessages']; ?> asst.)</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php 
                                if (isset($conv['duration']) && $conv['duration']) {
                                    $minutes = floor($conv['duration'] / 60);
                                    $seconds = $conv['duration'] % 60;
                                    echo $minutes . 'm ' . $seconds . 's';
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="#" onclick="viewConversation('<?php echo $conv['id']; ?>')" class="text-blue-500 hover:text-blue-700">Voir</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($recentConversations)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                Aucune conversation trouvée.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Modal pour visualiser une conversation -->
        <div id="conversationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-screen overflow-y-auto">
                <div class="flex justify-between items-center p-4 border-b">
                    <h3 class="text-lg font-medium" id="modalTitle">Détails de la conversation</h3>
                    <button onclick="closeModal()" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="p-4" id="modalContent">
                    <p class="text-center text-gray-500">Chargement...</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Données pour les graphiques
    const chartData = <?php echo $chartDataJson; ?>;
    const topicsData = <?php echo $topicsJson; ?>;
    
    // Configuration des graphiques
    document.addEventListener('DOMContentLoaded', function() {
        // Graphique des conversations par jour
        const conversationsCtx = document.getElementById('conversationsChart').getContext('2d');
        new Chart(conversationsCtx, {
            type: 'line',
            data: {
                labels: chartData.conversations.map(d => d.date),
                datasets: [{
                    label: 'Conversations',
                    data: chartData.conversations.map(d => d.count),
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
        
        // Graphique des messages par jour
        const messagesCtx = document.getElementById('messagesChart').getContext('2d');
        new Chart(messagesCtx, {
            type: 'bar',
            data: {
                labels: chartData.messages.map(d => d.date),
                datasets: [{
                    label: 'Messages',
                    data: chartData.messages.map(d => d.count),
                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
        
        // Graphique de distribution des messages
        const distributionCtx = document.getElementById('distributionChart').getContext('2d');
        new Chart(distributionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Messages utilisateurs', 'Messages assistant'],
                datasets: [{
                    data: [
                        <?php echo $data['metrics']['totalUserMessages']; ?>, 
                        <?php echo $data['metrics']['totalAssistantMessages']; ?>
                    ],
                    backgroundColor: [
                        'rgba(245, 158, 11, 0.7)',
                        'rgba(79, 70, 229, 0.7)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Graphique des sujets
        const topicsCtx = document.getElementById('topicsChart').getContext('2d');
        new Chart(topicsCtx, {
            type: 'bar',
            data: {
                labels: Object.keys(topicsData),
                datasets: [{
                    label: 'Occurrences',
                    data: Object.values(topicsData),
                    backgroundColor: 'rgba(124, 58, 237, 0.7)',
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    });
    
    // Fonctions pour la modal
    function viewConversation(id) {
        const modal = document.getElementById('conversationModal');
        const modalContent = document.getElementById('modalContent');
        const modalTitle = document.getElementById('modalTitle');
        
        modalTitle.textContent = 'Conversation: ' + id;
        modalContent.innerHTML = '<p class="text-center text-gray-500">Chargement des détails...</p>';
        modal.classList.remove('hidden');
        
        // Effectuer une requête AJAX pour récupérer les détails de la conversation
        fetch('get_conversation.php?id=' + encodeURIComponent(id))
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erreur lors de la récupération des données');
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    modalContent.innerHTML = `
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
                            <p class="font-bold">Erreur :</p>
                            <p>${data.error}</p>
                        </div>`;
                    return;
                }
                
                // Afficher les métadonnées de la conversation
                let html = `
                    <div class="bg-gray-100 p-4 mb-4 rounded">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="font-medium">ID de conversation:</p>
                                <p class="text-sm text-gray-600">${data.id}</p>
                            </div>
                            <div>
                                <p class="font-medium">Date:</p>
                                <p class="text-sm text-gray-600">${data.date || 'Non disponible'}</p>
                            </div>
                            <div>
                                <p class="font-medium">Nombre de messages:</p>
                                <p class="text-sm text-gray-600">${data.messageCount} (${data.userMessages} util., ${data.assistantMessages} asst.)</p>
                            </div>
                            <div>
                                <p class="font-medium">Durée:</p>
                                <p class="text-sm text-gray-600">${data.duration || 'Non disponible'}</p>
                            </div>
                        </div>
                    </div>`;
                
                // Afficher les messages de la conversation
                html += '<div class="space-y-4">';
                
                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(message => {
                        const isUser = message.role === 'user';
                        const alignClass = isUser ? 'items-end' : 'items-start';
                        const bgColor = isUser ? 'bg-blue-100' : 'bg-gray-100';
                        const textColor = isUser ? 'text-blue-800' : 'text-gray-800';
                        
                        html += `
                            <div class="flex flex-col ${alignClass}">
                                <div class="flex items-center mb-1">
                                    <span class="text-xs font-medium text-gray-500">${isUser ? 'Utilisateur' : 'Assistant'}</span>
                                    ${message.timestamp ? `<span class="text-xs text-gray-400 ml-2">${message.timestamp}</span>` : ''}
                                </div>
                                <div class="${bgColor} ${textColor} rounded-lg p-3 max-w-3xl">
                                    <p class="whitespace-pre-wrap">${escapeHtml(message.content)}</p>
                                </div>
                            </div>`;
                    });
                } else {
                    html += '<p class="text-center text-gray-500">Aucun message disponible.</p>';
                }
                
                html += '</div>';
                
                // Ajouter des boutons d'action
                html += `
                    <div class="mt-6 flex justify-end space-x-3">
                        <button onclick="exportConversation('${id}')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                            Exporter
                        </button>
                        <button onclick="closeModal()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                            Fermer
                        </button>
                    </div>`;
                
                modalContent.innerHTML = html;
            })
            .catch(error => {
                console.error('Erreur:', error);
                modalContent.innerHTML = `
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
                        <p class="font-bold">Erreur :</p>
                        <p>Impossible de récupérer les détails de la conversation. Veuillez réessayer.</p>
                    </div>`;
            });
    }

    // Fonction utilitaire pour échapper les caractères HTML spéciaux
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Fonction pour exporter la conversation
    function exportConversation(id) {
        // Rediriger vers un script PHP qui génère un fichier d'exportation
        window.location.href = 'export_conversation.php?id=' + encodeURIComponent(id);
    }
    
    function closeModal() {
        const modal = document.getElementById('conversationModal');
        modal.classList.add('hidden');
    }
    
    // Fermer la modal si on clique en dehors
    window.onclick = function(event) {
        const modal = document.getElementById('conversationModal');
        if (event.target === modal) {
            closeModal();
        }
    }
    </script>
</body>
</html>