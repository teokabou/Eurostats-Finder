<?php
function downloadWithCurl($url, $cacheFile) {
    set_time_limit(300);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "User-Agent: EurostatsFinderBot/1.0"
    ]);

    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);

    echo "HTTP Code: $httpCode<br>";
    echo "Content-Type: $contentType<br>";

    if ($data !== false && $httpCode === 200) {
        file_put_contents($cacheFile, $data);
        echo "✅ Downloaded and saved: $url<br>";
        return true;
    } else {
        echo "❌ Failed downloading from $url<br>";
        echo "cURL Error: $error<br><br>";
        return false;
    }
}

$tocUrl = 'https://ec.europa.eu/eurostat/api/dissemination/catalogue/toc/xml';
$dcatUrl = 'https://ec.europa.eu/eurostat/api/dissemination/catalogue/dcat/ESTAT/FULL';

$tocCache = __DIR__ . '/cache/toc.xml';
$dcatCache = __DIR__ . '/cache/dcat.zip';

// Κλήσεις
$successToc = downloadWithCurl($tocUrl, $tocCache);
$successDcat = downloadWithCurl($dcatUrl, $dcatCache);


if ($successToc && $successDcat) {
    echo "✅ All caches updated successfully.";
} else {
    echo "❌ Error updating one or more cache files.";
}

$output = shell_exec('php extractZipDCAT.php 2>&1');
echo $output;
?>