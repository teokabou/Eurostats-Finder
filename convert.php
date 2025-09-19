<?php
if (!isset($_GET['data_url'])) {
    http_response_code(400);
    echo "Missing data_url parameter.";
    exit;
}

$dataUrl = $_GET['data_url'];
$dataXml = @file_get_contents($dataUrl);

if (!$dataXml) {
    http_response_code(500);
    echo "Failed to fetch XML.";
    exit;
}

$xml = simplexml_load_string($dataXml);
$xml->registerXPathNamespace("m", "http://www.sdmx.org/resources/sdmxml/schemas/v3_0/message");

$seriesList = $xml->xpath('//m:DataSet/*');

header("Content-Type: text/plain"); // ΜΗΝ είναι text/html

echo "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n";
echo "@prefix sdmx: <http://purl.org/linked-data/sdmx/2009/measure#> .\n";
echo "@prefix ex: <http://example.org/resource/> .\n\n";

$obsCounter = 0;
foreach ($seriesList as $series) {
    if ($series->getName() !== 'Series') continue;

    $dimensions = [];
    foreach ($series->attributes() as $key => $val) {
        $dimensions[$key] = (string)$val;
    }

    foreach ($series->Obs as $obs) {
        $obsUri = "ex:obs" . (++$obsCounter);
        $time = (string)$obs['TIME_PERIOD'];
        $value = (string)$obs['OBS_VALUE'];

        echo "$obsUri a sdmx:Observation ;\n";
        echo "    sdmx:time \"$time\" ;\n";
        echo "    sdmx:value \"$value\"^^<http://www.w3.org/2001/XMLSchema#decimal> ;\n";

        foreach ($dimensions as $dim => $val) {
            echo "    sdmx:$dim \"$val\" ;\n";
        }

        echo "    .\n\n";
    }
}
?>