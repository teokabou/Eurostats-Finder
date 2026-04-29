<?php

set_time_limit(0); // no timeout for update job

function downloadWithCurl($url, $cacheFile) {

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => [
            "User-Agent: EurostatsFinderBot/1.0"
        ]
    ]);

    $data = curl_exec($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    if ($data !== false && $httpCode === 200) {
        file_put_contents($cacheFile, $data);
        return [
            "success" => true,
            "httpCode" => $httpCode,
            "error" => null
        ];
    }

    return [
        "success" => false,
        "httpCode" => $httpCode,
        "error" => $error
    ];
}

$tocUrl  = 'https://ec.europa.eu/eurostat/api/dissemination/catalogue/toc/xml';
//$dcatUrl = 'https://ec.europa.eu/eurostat/api/dissemination/catalogue/dcat/ESTAT/FULL';

$tocCache  = __DIR__ . '/cache/toc.xml';
//$dcatCache = __DIR__ . '/cache/dcat.zip';

$result = [];

// TOC
$result["toc"] = downloadWithCurl($tocUrl, $tocCache);

// DCAT
//$result["dcat"] = downloadWithCurl($dcatUrl, $dcatCache);
/*
// unzip only if downloaded
if ($result["dcat"]["success"]) {

    $output = shell_exec('php extractZipDCAT.php 2>&1');

    $result["extract"] = $output;
}*/

header('Content-Type: application/json; charset=utf-8');

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>