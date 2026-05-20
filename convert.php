<?php
set_time_limit(0); 
ini_set('memory_limit', '256M');

$context = stream_context_create([
    'http' => ['timeout' => 60]
]);

if (!isset($_GET['data_url'])) {
    http_response_code(400);
    echo "Missing data_url parameter.";
    exit;
}
$dataUrl = $_GET['data_url'];
$labels = [];
if (file_exists("cache/labels.json")) {
    $labels = json_decode(file_get_contents("cache/labels.json"), true) ?: [];
}

$dataXml = @file_get_contents($dataUrl, false, $context);
if (!$dataXml) {
    http_response_code(504);
    echo "Failed to fetch XML or Timeout from Eurostat.";
    exit;
}
logStep("XML fetched, size: " . strlen($dataXml));
$xml = simplexml_load_string($dataXml);
$xml->registerXPathNamespace(
    "m",
    "http://www.sdmx.org/resources/sdmxml/schemas/v3_0/message"
);
preg_match('~/dataflow/ESTAT/([^/]+)/~', $dataUrl, $matches);
$datasetId  = $matches[1] ?? 'unknown';
$datasetUri = "estat:dataset/" . strtolower($datasetId);
$seriesList = $xml->xpath('//m:DataSet/*');

header("Content-Type: text/turtle; charset=utf-8");

echo "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n";
echo "@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .\n";
echo "@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .\n";
echo "@prefix skos: <http://www.w3.org/2004/02/skos/core#> .\n";
echo "@prefix qb: <http://purl.org/linked-data/cube#> .\n";
echo "@prefix sdmx-measure: <http://purl.org/linked-data/sdmx/2009/measure#> .\n";
echo "@prefix sdmx-dimension: <http://purl.org/linked-data/sdmx/2009/dimension#> .\n";
echo "@prefix sdmx-attribute: <http://purl.org/linked-data/sdmx/2009/attribute#> .\n";
echo "@prefix estat: <http://eurostat.linked-statistics.org/> .\n";
echo "@prefix estatdim: <http://eurostat.linked-statistics.org/dimension/> .\n";
echo "@prefix estatcode: <http://eurostat.linked-statistics.org/code/> .\n\n";

echo "# Dataset\n\n";
echo "$datasetUri a qb:DataSet ;\n";
echo "    rdfs:label \"Eurostat Dataset: $datasetId\"@en ;\n";
echo "    qb:structure estat:dsd/$datasetId .\n\n";

$printedLabels = [];
$skosBlocks    = [];

echo "# Observations\n\n";
logStep("Before echo output");
logStep("XML parsed, series count: " . count($seriesList));
foreach ($seriesList as $series) {
    if ($series->getName() !== 'Series') {
        continue;
    }
    $dimensions = [];
    foreach ($series->attributes() as $key => $val) {
        $dimensions[strtoupper((string)$key)] = (string)$val;
    }
    foreach ($series->Obs as $obs) {
        $time  = (string)$obs['TIME_PERIOD'];
        $value = (string)$obs['OBS_VALUE'];
        $obsUriParts = [];
        foreach ($dimensions as $dim => $val) {
            $obsUriParts[] = strtoupper($dim) . "-" . strtoupper($val);
        }
        $obsUriParts[] = "time-" . $time;
        $obsUri =
            "estat:obs/"
            . strtolower($datasetId)
            . "/"
            . implode("/", $obsUriParts);
        echo "$obsUri a qb:Observation ;\n";
        echo "    qb:dataSet $datasetUri ;\n";
        echo "    sdmx-dimension:timePeriod \"$time\"^^xsd:gYear ;\n";
        if (is_numeric($value)) {
            echo "    sdmx-measure:obsValue \"$value\"^^xsd:decimal ;\n";
        } else {
            echo "    sdmx-measure:obsValue \"$value\" ;\n";
        }
        foreach ($dimensions as $dim => $val) {
            $dimUpper = strtoupper($dim);
            $valUpper = strtoupper($val);
            echo "    estatdim:$dimUpper estatcode:$dimUpper/$valUpper ;\n";
            $uniqueKey = $dimUpper . ":" . $valUpper;
            if (!isset($printedLabels[$uniqueKey])) {
                $printedLabels[$uniqueKey] = true;
                $label = $labels[$dimUpper][$valUpper] ?? null;
                file_put_contents("debug_labels.log",
                    "[$dimUpper][$valUpper] => " . var_export($label, true) . "\n",
                    FILE_APPEND
                );
                $escaped = addslashes($label);
                $skosBlocks[] =
                    "estatcode:$dimUpper/$valUpper a skos:Concept ;\n"
                    . "    skos:notation \"$valUpper\" ;\n"
                    . "    rdfs:label \"$escaped\"@en ;\n"
                    . "    skos:prefLabel \"$escaped\"@en .\n\n";
            }
        }

        echo "    .\n\n";
    }
}
echo "# Code Labels\n\n";
if (!empty($skosBlocks)) {
    echo implode("", $skosBlocks);
}
$output = ob_get_clean();
echo $output;
?>