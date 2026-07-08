<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if (!isset($_GET['q']) || mb_strlen(trim($_GET['q'])) < 2) {
    echo json_encode([]);
    exit;
}

// Μετατρέπουμε την αναζήτηση σε πεζά (μικρά) γράμματα
$q = mb_strtolower(trim($_GET['q']), 'UTF-8');
$jsonPath = __DIR__ . '/search_data.json';

if (!file_exists($jsonPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Search index file not found.']);
    exit;
}

// Διαβάζουμε το πλήρες JSON αρχείο
$jsonData = file_get_contents($jsonPath);
$datasets = json_decode($jsonData, true);

if (!$datasets) {
    echo json_encode([]);
    exit;
}

$results = [];

foreach ($datasets as $ds) {
    $titleLower = mb_strtolower($ds['title'] ?? '', 'UTF-8');
    $themeLower = mb_strtolower($ds['theme'] ?? '', 'UTF-8');
    $idLower = mb_strtolower($ds['id'] ?? '', 'UTF-8');
    $searchLower = mb_strtolower($ds['search_text'] ?? '', 'UTF-8');

    $score = 0;

    // Προσομοίωση αλγορίθμου BM25 με τα σωστά βάρη!
    if (mb_strpos($titleLower, $q) !== false) {
        $score += 100; // Βάρος 1.0 (Μέγιστη σημασία)
    }
    if (mb_strpos($themeLower, $q) !== false) {
        $score += 50;  // Βάρος 2.0 (Πολύ σημαντικό)
    }
    if (mb_strpos($idLower, $q) !== false) {
        $score += 40;  // Extra μπόνους αν έψαξε ακριβώς τον κωδικό
    }
    if (mb_strpos($searchLower, $q) !== false) {
        $score += 20;  // Βάρος 5.0 και 10.0 (EuroVoc / ESMS / Codelists)
    }

    if ($score > 0) {
        $results[] = [
            'code' => $ds['id'],
            'title' => $ds['title'],
            'score' => $score
        ];
    }
}

// Ταξινόμηση: Τα μεγαλύτερα σκορ (πιο σχετικά) εμφανίζονται πρώτα
usort($results, function($a, $b) {
    return $b['score'] <=> $a['score'];
});

// Επιστρέφουμε μόνο τα πρώτα 50 αποτελέσματα
echo json_encode(array_slice($results, 0, 50));
?>