<?php
header('Content-Type: application/json');

$tocPath = __DIR__ . '/cache/toc.xml';
if (!file_exists($tocPath)) {
    die(json_encode(["error" => "toc.xml not found in cache."]));
}

$xmlString = file_get_contents($tocPath);
$xml = simplexml_load_string($xmlString);
if (!$xml) {
    die(json_encode(["error" => "Invalid XML."]));
}

$ns = $xml->getNamespaces(true);
$xml->registerXPathNamespace('nt', $ns['nt'] ?? 'urn:eu.europa.ec.eurostat.navtree');

// Πάρε τα κύρια branches
$branches = $xml->xpath('//nt:tree/nt:branch');

function parseBranch($branch) {
    $ns = $branch->getNamespaces(true);
    $branch->registerXPathNamespace('nt', $ns['nt'] ?? 'urn:eu.europa.ec.eurostat.navtree');

    // Τίτλος και κωδικός του branch
    $title = (string)($branch->xpath('nt:title[@language="en"]')[0] ?? '');
    $code = (string)($branch->xpath('nt:code')[0] ?? '');

    $children = [];
    $datasets = [];

    // Αναδρομικά πάρε τα παιδιά του branch
    $childBranches = $branch->xpath('nt:children/nt:branch');
    foreach ($childBranches as $child) {
        $children[] = parseBranch($child);
    }

    // Αν υπάρχουν leaves, πέρασέ τα ξεχωριστά
    $leafNodes = $branch->xpath('nt:children/nt:leaf');
    foreach ($leafNodes as $leaf) {
        $datasets[] = parseLeaf($leaf); 

    }

    return [
        'title' => $title,
        'code' => $code,
        'children' => $children,
        'datasets' => $datasets
    ];
}

function parseLeaf($leaf) {
    $ns = $leaf->getNamespaces(true);
    if (isset($ns['nt'])) {
        $leaf->registerXPathNamespace('nt', $ns['nt']);
        $codeNode = $leaf->xpath('nt:code');
    } else {
        $codeNode = $leaf->xpath('*[local-name()="code"]');
    }

    $code = isset($codeNode[0]) ? (string)$codeNode[0] : '';

    $title = (string)($leaf->xpath('nt:title[@language="en"]')[0] ?? '');
    $lastUpdate = (string)($leaf->xpath('nt:lastUpdate')[0] ?? '');
    $lastModified = (string)($leaf->xpath('nt:lastModified')[0] ?? '');
    $dataStart = (string)($leaf->xpath('nt:dataStart')[0] ?? '');
    $dataEnd = (string)($leaf->xpath('nt:dataEnd')[0] ?? '');
    $metadata = (string)($leaf->xpath('nt:metadata[@format="html"]')[0] ?? '');

    return [
        'title' => $title,
        'code' => $code,
        'lastUpdate' => $lastUpdate,
        'lastModified' => $lastModified,
        'dataStart' => $dataStart,
        'dataEnd' => $dataEnd,
        'metadata' => $metadata
    ];
}

//NEW WITHOUT 3 ROOT NODES

$result = [];

foreach ($branches as $branch) {

    // πάρε τα παιδιά του κάθε root branch
    $childBranches = $branch->xpath('nt:children/nt:branch');

    foreach ($childBranches as $child) {
        $parsed = parseBranch($child);
        $result[] = $parsed;
    }
}

//Alphabetical order
usort($result, function($a, $b) {
    return strcmp($a['title'], $b['title']);
});

/* OLD
$result = [];
foreach ($branches as $branch) {
    $parsed = parseBranch($branch);
    error_log("Parsed branch: " . json_encode($parsed));
    $result[] = $parsed;
}*/

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

?>