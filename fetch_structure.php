<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/xml");

if (!isset($_GET['url'])) {
    http_response_code(400);
    echo "Missing URL.";
    exit;
}

$url = $_GET['url'];
if (filter_var($url, FILTER_VALIDATE_URL) === false) {
    http_response_code(400);
    echo "Invalid URL.";
    exit;
}

$response = file_get_contents($url);
if ($response === false) {
    http_response_code(500);
    echo "Error retrieving URL.";
} else {
    echo $response;
}
?>