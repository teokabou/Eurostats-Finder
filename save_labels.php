<?php
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    http_response_code(400);
    echo "Invalid JSON";
    exit;
}
if (!is_dir("cache")) {
    mkdir("cache", 0777, true);
}
$normalized = [];
foreach ($data as $dim => $codes) {
    $normalized[strtoupper($dim)] = $codes;
}
file_put_contents(
    "cache/labels.json",
    json_encode(
        $normalized,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    )
);
echo "Labels saved";
?>