<?php
header('Content-Type: application/json');

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

echo json_encode($results, JSON_UNESCAPED_UNICODE);
?>