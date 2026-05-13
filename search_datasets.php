<?php
header('Content-Type: application/json');

$q = strtolower(trim($_GET['q'] ?? ''));
if ($q === '') {
    echo json_encode([]);
    exit;
}

include 'euroVoc.php';

$terms = [$q];                      // αρχικός όρος
$expanded = eurovocLiveExpand($q);  // EuroVoc LIVE expansion 
$terms = array_merge($terms, $expanded);
$terms = array_unique($terms);

//for debugging
error_log("Query: " . $q);
error_log("Terms: " . json_encode($terms));

$tocPath = __DIR__ . '/cache/toc.xml';
$xml = simplexml_load_file($tocPath);
$ns = $xml->getNamespaces(true);
$xml->registerXPathNamespace('nt', $ns['nt']);
$leaves = $xml->xpath('//nt:leaf');
$results = [];
foreach ($leaves as $leaf) {

    $title = (string)($leaf->xpath('nt:title[@language="en"]')[0] ?? '');
    $code  = (string)($leaf->xpath('nt:code')[0] ?? '');
    $meta  = strip_tags((string)($leaf->xpath('nt:metadata')[0] ?? ''));

    $haystack = strtolower($title . " " . $code . " " . $meta);

    foreach ($terms as $term) {
        if (strpos($haystack, strtolower($term)) !== false) {

            $results[] = [
                'title' => $title,
                'code' => $code
            ];

            break;
        }
    }
}
// optional limit
$results = array_slice($results, 0, 50);
echo json_encode($results, JSON_UNESCAPED_UNICODE);

/* ΠΑΛΙΟ
$q = strtolower(trim($_GET['q'] ?? ''));

if ($q === '') {
    echo json_encode([]);
    exit;
}

$tocPath = __DIR__ . '/cache/toc.xml';
$xml = simplexml_load_file($tocPath);

$ns = $xml->getNamespaces(true);
$xml->registerXPathNamespace('nt', $ns['nt']);

$leaves = $xml->xpath('//nt:leaf');

$results = [];

foreach ($leaves as $leaf) {

    $title = (string)($leaf->xpath('nt:title[@language="en"]')[0] ?? '');
    $code  = (string)($leaf->xpath('nt:code')[0] ?? '');
    $meta  = strip_tags((string)($leaf->xpath('nt:metadata')[0] ?? ''));

    $haystack = strtolower($title . " " . $code . " " . $meta);

    if (strpos($haystack, $q) !== false) {
        $results[] = [
            'title' => $title,
            'code' => $code
        ];
    }
}

echo json_encode($results, JSON_UNESCAPED_UNICODE);*/
?>