<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Ελέγχουμε αν υπάρχει λέξη προς αναζήτηση
if (!isset($_GET['q']) || mb_strlen(trim($_GET['q'])) < 2) {
    echo json_encode([]);
    exit;
}

$q = trim($_GET['q']);
// Προσοχή: Βεβαιώσου ότι θα βάλεις το αρχείο eurostat.db στον ίδιο φάκελο με αυτό το PHP αρχείο!
$dbPath = __DIR__ . '/eurostat.db';

if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database file not found on server.']);
    exit;
}

try {
    // Σύνδεση στη βάση SQLite μέσω του ενσωματωμένου PDO της PHP
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Η μαγική SQL με το BM25 Scoring (ακριβώς όπως την είχαμε στο Node.js)
    $query = '
        SELECT 
            dataset_id AS code, 
            title, 
            bm25(datasets_fts, 1.0, 2.0, 5.0, 5.0, 10.0) AS score
        FROM datasets_fts
        WHERE datasets_fts MATCH ?
        ORDER BY score ASC
        LIMIT 50
    ';

    $stmt = $db->prepare($query);
    // Προσθέτουμε τον αστερίσκο για wildcard αναζήτηση
    $stmt->execute(['"' . $q . '"*']);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($results);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>