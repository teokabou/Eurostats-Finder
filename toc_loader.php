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


$result = [];
foreach ($branches as $branch) {
    $parsed = parseBranch($branch);
    error_log("Parsed branch: " . json_encode($parsed));
    $result[] = $parsed;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

///////////////////////////////////////////////////////////////////////////////////
/*header('Content-Type: application/json');

// Φόρτωση XML ως string
$url = "https://ec.europa.eu/eurostat/api/dissemination/catalogue/toc/xml";
$xmlString = file_get_contents($url);
if (!$xmlString) {
    die(json_encode(["error" => "Failed to load XML."]));
}

$xml = simplexml_load_string($xmlString);
if (!$xml) {
    die(json_encode(["error" => "Invalid XML."]));
}

// Δήλωση namespace
$ns = $xml->getNamespaces(true);
$xml->registerXPathNamespace('nt', $ns['nt'] ?? 'urn:eu.europa.ec.eurostat.navtree');

// Βρες τα branches
$branches = $xml->xpath('//nt:tree/nt:branch');

function parseBranch($branch) {
    $ns = $branch->getNamespaces(true);
    $branch->registerXPathNamespace('nt', $ns['nt'] ?? 'urn:eu.europa.ec.eurostat.navtree');

    // Πάρε τον τίτλο στα αγγλικά
    $titleNodes = $branch->xpath('nt:title[@language="en"]');
    $title = (string)($titleNodes[0] ?? '');

    $code = (string)($branch->xpath('nt:code')[0] ?? '');

    // Επεξεργασία υποκατηγοριών
    $children = [];
    $childBranches = $branch->xpath('nt:children/nt:branch');
    foreach ($childBranches as $child) {
        $children[] = parseBranch($child);
    }

    return [
        'title' => $title,
        'code' => $code,
        'children' => $children
    ];
}

$result = [];
foreach ($branches as $branch) {
    $result[] = parseBranch($branch);
}

echo json_encode($result);*/


////////////////////////////////////////////////////////////////////////////////////////////////////

/*$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

curl_setopt($ch, CURLOPT_VERBOSE, true); // Προσθέτει debug
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo 'cURL Error: ' . curl_error($ch);
} else {
    echo htmlspecialchars($response); // για να δεις την XML
}

/*
require 'vendor/autoload.php'; // EasyRDF
use EasyRdf\Graph;
use EasyRdf\Resource;
// Επιτρέπει αιτήματα από οποιαδήποτε προέλευση (CORS)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Ορίζουμε τον τύπο περιεχομένου σε JSON
//header("Content-Type: application/json");
$api=$_GET['api'];
//$dataset=$_GET['dataset'];
$url=$_GET['url'];
$strUrl=$_GET['strUrl']??null; // if it doesn't exist, then null


// Φορτώνουμε το TOC XML
$tocXml = fetchTOCXML();

if ($tocXml === null) {
    echo "Δεν μπόρεσε να φορτωθεί το TOC XML.";
    exit;
}

// Για δοκιμή, εμφανίζουμε το όνομα του πρώτου στοιχείου
echo "Πρώτο category: " . $tocXml->xpath('//category')[0]['id'] . "\n";
exit;


//fetch_and_return($url,$api,$strUrl);



function fetchTOCXML() {
    $url = 'https://ec.europa.eu/eurostat/api/dissemination/catalogue/toc/xml';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
        return null;
    }

    $xml = simplexml_load_string($response);
    if (!$xml) {
        return null;
    }
    return $xml;
}

function printCategoryTree($category, $level = 0) {
    $indent = str_repeat("  ", $level);
    $id = (string)$category['id'];
    $label = (string)$category->label;  // Το label είναι πολυγλωσσικό, εδώ απλά παίρνουμε το πρώτο

    echo $indent . "- [$id] $label\n";

    // Ελέγχουμε αν έχει υποκατηγορίες
    if (isset($category->category)) {
        foreach ($category->category as $subcat) {
            printCategoryTree($subcat, $level + 1);
        }
    }

    // Ελέγχουμε αν έχει datasets
    if (isset($category->dataset)) {
        foreach ($category->dataset as $dataset) {
            $dsId = (string)$dataset['id'];
            $dsLabel = (string)$dataset->label;
            echo $indent . "  * [$dsId] $dsLabel\n";
        }
    }
}

// Καλούμε τη συνάρτηση με τη ρίζα του TOC
foreach ($tocXml->category as $topCategory) {
    printCategoryTree($topCategory);
}



*/
?>