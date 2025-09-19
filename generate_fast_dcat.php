<?php
$dir = __DIR__ . '/cache/dcat_unzipped/datasets';
$output = [];

$files = glob("$dir/*.rdf");
$total = count($files);
echo "Total files: $total\n\n";

foreach ($files as $i => $file) {
    echo "🔍 Reading: $file\n";
    $contents = file_get_contents($file);

    if ($contents === false) {
        echo "❌ Failed to read file: $file\n";
        continue;
    }

    $id = basename($file, '.rdf');
    $title = '(Untitled)';

    if (preg_match('/<dct:title[^>]*xml:lang=["\']en["\'][^>]*>(.*?)<\/dct:title>/is', $contents, $matches)) {
        $title = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_XML1, 'UTF-8'));
        echo "✅ Title found: $title\n";
    } else {
        echo "⚠️ No English title in: $file\n";
    }

    $output[] = [
        'id' => $id,
        'title' => $title
    ];
}

file_put_contents('mini_dcat.json', json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "✅ Done. Saved " . count($output) . " records.\n";
?>