<?php
if (!isset($_GET['code'])) {
    http_response_code(400);
    echo "Missing code.";
    exit;
}

$code = $_GET['code'];
$folder = __DIR__ . '/cache/dcat_unzipped/datasets/';
$pattern = $folder  . $code . '.rdf';

$matches = glob($pattern);

if (!$matches || count($matches) === 0) {
    http_response_code(404);
    echo "No file found for code $code.";
    exit;
}

// Αν βρεις πολλά, παίρνεις το πρώτο
$foundFile = basename($matches[0]);
echo $foundFile;
?>