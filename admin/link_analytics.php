<?php
// link_analytics.php

// Chemin vers le fichier de journal des liens
$logFile = '../logs/links_log.json';
$logs = [];

if (file_exists($logFile)) {
    $fileContent = file_get_contents($logFile);
    if (!empty($fileContent)) {
        $logs = json_decode($fileContent, true) ?: [];
    }
}

// Initialisation des statistiques
$stats = [
    'total_links' => 0,
    'total_unique_links' => 0,
    'total_clicks' => 0,
    'click_through_rate' => 0,
    'links_per_conversation' => 0,
    'most_clicked_links' => [],
    'daily_link_counts' => [],
    'links_by_domain' => []
];

        // Traiter les données du journal
$linkDetections = [];
$linkClicks = [];
$uniqueLinks = [];
$linksByConversation = [];
$linkClicksByDay = [];
$domains = [];

// Assurer qu'il y a au moins des données pour aujourd'hui même si vide
$today = date('Y-m-d');
$linkClicksByDay[$today] = [
    'detections' => 0,
    'clicks' => 0
];

foreach ($logs as $entry) {
    if ($entry['event_type'] === 'link_detected') {
        $linkDetections[] = $entry;
        $uniqueLinks[$entry['link']] = true;
        
        // Compter les liens par conversation
        if (!isset($linksByConversation[$entry['conversation_id']])) {
            $linksByConversation[$entry['conversation_id']] = 0;
        }
        $linksByConversation[$entry['conversation_id']]++;
        
        // Extraire le domaine
        $parsedUrl = parse_url($entry['link']);
        $domain = isset($parsedUrl['host']) ? $parsedUrl['host'] : 'unknown';
        
        if (!isset($domains[$domain])) {
            $domains[$domain] = [
                'detections' => 0,
                'clicks' => 0,
                'ctr' => 0
            ];
        }
        $domains[$domain]['detections']++;
        
        // Compter par jour
        $day = date('Y-m-d', strtotime($entry['timestamp']));
        if (!isset($linkClicksByDay[$day])) {
            $linkClicksByDay[$day] = [
                'detections' => 0,
                'clicks' => 0
            ];
        }
        $linkClicksByDay[$day]['detections']++;
    } elseif ($entry['event_type'] === 'link_clicked') {
        $linkClicks[] = $entry;
        
        // Ajouter aux compteurs de domaine
        $parsedUrl = parse_url($entry['link']);
        $domain = isset($parsedUrl['host']) ? $parsedUrl['host'] : 'unknown';
        
        if (!isset($domains[$domain])) {
            $domains[$domain] = [
                'detections' => 0,
                'clicks' => 0,
                'ctr' => 0
            ];
        }
        $domains[$domain]['clicks']++;
        
        // Compter par jour
        $day = date('Y-m-d', strtotime($entry['timestamp']));
        if (!isset($linkClicksByDay[$day])) {
            $linkClicksByDay[$day] = [
                'detections' => 0,
                'clicks' => 0
            ];
        }
        $linkClicksByDay[$day]['clicks']++;
    }
}

// Calculer les statistiques
$stats['total_links'] = count($linkDetections);
$stats['total_unique_links'] = count($uniqueLinks);
$stats['total_clicks'] = count($linkClicks);
$stats['click_through_rate'] = $stats['total_links'] > 0 ? round(($stats['total_clicks'] / $stats['total_links']) * 100, 2) : 0;
$stats['links_per_conversation'] = count($linksByConversation) > 0 ? round(array_sum($linksByConversation) / count($linksByConversation), 2) : 0;

// Calculer les taux de clics par domaine
foreach ($domains as $domain => &$data) {
    $data['ctr'] = $data['detections'] > 0 ? round(($data['clicks'] / $data['detections']) * 100, 2) : 0;
}
unset($data); // Casser la référence

// Trier les domaines par nombre de clics
arsort($domains);
$stats['links_by_domain'] = $domains;

// Formater les données pour les graphiques
// Graphique quotidien
$chartData = [];

// S'assurer qu'on a des données pour les 7 derniers jours, même si c'est 0
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    if (!isset($linkClicksByDay[$day])) {
        $linkClicksByDay[$day] = [
            'detections' => 0,
            'clicks' => 0
        ];
    }
}

// Trier par ordre chronologique
ksort($linkClicksByDay);
$linkClicksByDay = array_slice($linkClicksByDay, -30); // Limiter aux 30 derniers jours

foreach ($linkClicksByDay as $day => $counts) {
    $chartData[] = [
        'day' => $day,
        'detections' => $counts['detections'],
        'clicks' => $counts['clicks'],
        'ctr' => $counts['detections'] > 0 ? round(($counts['clicks'] / $counts['detections']) * 100, 2) : 0
    ];
}
$stats['daily_link_counts'] = $chartData;

// Trouver les liens les plus cliqués
$linkClickCounts = [];
foreach ($linkClicks as $click) {
    if (!isset($linkClickCounts[$click['link']])) {
        $linkClickCounts[$click['link']] = 0;
    }
    $linkClickCounts[$click['link']]++;
}
arsort($linkClickCounts);
$stats['most_clicked_links'] = array_slice($linkClickCounts, 0, 10, true);

// Données en JSON pour JavaScript
$statsJson = json_encode($stats);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analyse des Liens - Absa</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.js"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <header class="mb-10">
            <h1 class="text-3xl font-bold text-gray-800">Analyse des Liens - Absa</h1>
            <p class="text-gray-600">Statistiques de suivi des liens • Mis à jour le <?php echo date('d/m/Y à H:i'); ?></p>
            <div class="mt-4 flex space-x-4">
                <a href="index.php" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm font-medium">
                    Conversations
                </a>
                <a href="link_analytics.php" class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700 text-sm font-medium">
                    Analyse des Liens
                </a>
            </div>
        </header>
        
        <!-- Métriques Principales -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-500 text-sm font-medium">Liens affichés</h3>
                <p class="text-3xl font-bold text-gray-800"><?php echo $stats['total_links']; ?></p>
                <div class="mt-2">
                    <span class="text-gray-600 text-sm">
                        <?php echo $stats['total_unique_links']; ?> liens uniques
                    </span>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-500 text-sm font-medium">Liens cliqués</h3>
                <p class="text-3xl font-bold text-gray-800"><?php echo $stats['total_clicks']; ?></p>
                <div class="mt-2">
                    <span class="text-gray-600 text-sm">
                        <?php echo $stats['links_per_conversation']; ?> liens par conversation
                    </span>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-500 text-sm font-medium">Taux de clic</h3>
                <p class="text-3xl font-bold text-gray-800"><?php echo $stats['click_through_rate']; ?>%</p>
                <div class="mt-2">
                    <span class="text-gray-600 text-sm">
                        Des liens généré
                    </span>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-500 text-sm font-medium">Meilleur domaine</h3>
                <?php
                $topDomain = !empty($domains) ? array_keys($domains)[0] : 'Aucun';
                $topDomainCTR = !empty($domains) ? $domains[$topDomain]['ctr'] : 0;
                ?>
                <p class="text-3xl font-bold text-gray-800"><?php echo $topDomain; ?></p>
                <div class="mt-2">
                    <span class="text-gray-600 text-sm">
                        Taux de clic: <?php echo $topDomainCTR; ?>%
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Graphiques -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-700 font-medium mb-4">Activité des liens par jour</h3>
                <canvas id="dailyLinksChart" height="300"></canvas>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-700 font-medium mb-4">Taux de clic quotidien</h3>
                <canvas id="dailyCTRChart" height="300"></canvas>
            </div>
        </div>
        
        <!-- Liens les plus cliqués et domaines -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-700 font-medium mb-4">Liens les plus cliqués</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lien</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Clics</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($stats['most_clicked_links'] as $link => $clicks): ?>
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900 truncate max-w-xs">
                                    <a href="<?php echo htmlspecialchars($link); ?>" target="_blank" class="text-blue-600 hover:underline">
                                        <?php echo htmlspecialchars($link); ?>
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 text-right"><?php echo $clicks; ?></td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($stats['most_clicked_links'])): ?>
                            <tr>
                                <td colspan="2" class="px-4 py-3 text-center text-sm text-gray-500">
                                    Aucun lien cliqué trouvé.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-gray-700 font-medium mb-4">Performance par domaine</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Domaine</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Affichages</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Clics</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Taux</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($stats['links_by_domain'] as $domain => $data): ?>
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900 truncate max-w-xs">
                                    <?php echo htmlspecialchars($domain); ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 text-right"><?php echo $data['detections']; ?></td>
                                <td class="px-4 py-3 text-sm text-gray-900 text-right"><?php echo $data['clicks']; ?></td>
                                <td class="px-4 py-3 text-sm text-gray-900 text-right"><?php echo $data['ctr']; ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($stats['links_by_domain'])): ?>
                            <tr>
                                <td colspan="4" class="px-4 py-3 text-center text-sm text-gray-500">
                                    Aucun domaine trouvé.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Journal des événements -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-gray-700 font-medium">Journal des événements récents</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date/Heure</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Conversation</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lien</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php 
                        // Prendre les 20 derniers événements (les plus récents d'abord)
                        $recentLogs = array_reverse(array_slice($logs, -20));
                        
                        foreach ($recentLogs as $log): 
                        ?>
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?php echo $log['timestamp']; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <?php if ($log['event_type'] === 'link_detected'): ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                    Détecté
                                </span>
                                <?php else: ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    Cliqué
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?php echo substr($log['conversation_id'], 0, 10) . '...'; ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 truncate max-w-xs">
                                <a href="<?php echo htmlspecialchars($log['link']); ?>" target="_blank" class="text-blue-600 hover:underline">
                                    <?php echo htmlspecialchars($log['link']); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($recentLogs)): ?>
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-center text-sm text-gray-500">
                                Aucun événement de lien trouvé.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
    // Données pour les graphiques
    const stats = <?php echo $statsJson; ?>;
    console.log('Stats data:', stats); // Pour déboguer
    
    document.addEventListener('DOMContentLoaded', function() {
        // Vérifier si nous avons des données pour les graphiques
        if (!stats.daily_link_counts || stats.daily_link_counts.length === 0) {
            // Si pas de données, créer des données fictives pour éviter les erreurs
            stats.daily_link_counts = [];
            
            // Ajouter les 7 derniers jours avec des valeurs à zéro
            for (let i = 6; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                const formattedDate = date.toISOString().split('T')[0]; // Format YYYY-MM-DD
                
                stats.daily_link_counts.push({
                    day: formattedDate,
                    detections: 0,
                    clicks: 0,
                    ctr: 0
                });
            }
        }
        
        // Préparer les données pour les graphiques
        const days = stats.daily_link_counts.map(item => item.day);
        const detections = stats.daily_link_counts.map(item => item.detections);
        const clicks = stats.daily_link_counts.map(item => item.clicks);
        const ctrs = stats.daily_link_counts.map(item => item.ctr);
        
        console.log('Days:', days);
        console.log('Detections:', detections);
        console.log('Clicks:', clicks);
        
        // Graphique des liens quotidiens
        const dailyLinksCtx = document.getElementById('dailyLinksChart').getContext('2d');
        
        // Vérifier que le canvas existe
        if (!dailyLinksCtx) {
            console.error("Canvas 'dailyLinksChart' introuvable");
            return;
        }
        
        new Chart(dailyLinksCtx, {
            type: 'line',
            data: {
                labels: days,
                datasets: [
                    {
                        label: 'Liens détectés',
                        data: detections,
                        backgroundColor: 'rgba(59, 130, 246, 0.2)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                        tension: 0.4
                    },
                    {
                        label: 'Liens cliqués',
                        data: clicks,
                        backgroundColor: 'rgba(16, 185, 129, 0.2)',
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(16, 185, 129, 1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
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
        
        // Graphique du taux de clic quotidien
        const dailyCTRCtx = document.getElementById('dailyCTRChart').getContext('2d');
        
        // Vérifier que le canvas existe
        if (!dailyCTRCtx) {
            console.error("Canvas 'dailyCTRChart' introuvable");
            return;
        }
        
        console.log('CTRs:', ctrs);
        new Chart(dailyCTRCtx, {
            type: 'bar',
            data: {
                labels: days,
                datasets: [{
                    label: 'Taux de clic (%)',
                    data: ctrs,
                    backgroundColor: 'rgba(124, 58, 237, 0.7)',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.raw + '%';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });
    });
</script>