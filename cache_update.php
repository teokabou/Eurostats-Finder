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
$tocCache  = __DIR__ . '/cache/toc.xml';
$result = [];
$result["toc"] = downloadWithCurl($tocUrl, $tocCache);

?>