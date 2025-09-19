<?php
$zipPath = 'dcat.zip';
$extractTo = 'dcat_unzipped/';

$zip = new ZipArchive;
if ($zip->open($zipPath) === TRUE) {
    $zip->extractTo($extractTo);
    $zip->close();
    echo "✅ Extracted to $extractTo\n";
} else {
    echo "❌ Failed to open ZIP file.\n";
}
?>