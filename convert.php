<?php
//neo  rdf 
if (!isset($_GET['data_url'])) {
    http_response_code(400);
    echo "Missing data_url parameter.";
    exit;
}
$dataUrl = $_GET['data_url'];


$codeLabels = [];
if (isset($_GET['labels'])) {
    $codeLabels = json_decode($_GET['labels'], true) ?: [];
}


$dataXml = @file_get_contents($dataUrl);
if (!$dataXml) {
    http_response_code(500);
    echo "Failed to fetch XML.";
    exit;
}
$xml = simplexml_load_string($dataXml);
$xml->registerXPathNamespace("m", "[sdmx.org](http://www.sdmx.org/resources/sdmxml/schemas/v3_0/message)");
$xml->registerXPathNamespace("s", "[sdmx.org](http://www.sdmx.org/resources/sdmxml/schemas/v3_0/data/structurespecific)");
// Εξαγωγή dataset ID από το URL
preg_match('/data\/([^\/\?]+)/', $dataUrl, $matches);
$datasetId = $matches[1] ?? 'unknown';
$datasetUri = "estat:dataset/" . strtolower($datasetId);
$seriesList = $xml->xpath('//m:DataSet/*');
header("Content-Type: text/turtle; charset=utf-8");
// Prefixes σύμφωνα με τα standards
echo "@prefix rdf: <[w3.org](http://www.w3.org/1999/02/22-rdf-syntax-ns#)> .\n";
echo "@prefix rdfs: <[w3.org](http://www.w3.org/2000/01/rdf-schema#)> .\n";
echo "@prefix xsd: <[w3.org](http://www.w3.org/2001/XMLSchema#)> .\n";
echo "@prefix qb: <[purl.org](http://purl.org/linked-data/cube#)> .\n";
echo "@prefix sdmx-measure: <[purl.org](http://purl.org/linked-data/sdmx/2009/measure#)> .\n";
echo "@prefix sdmx-dimension: <[purl.org](http://purl.org/linked-data/sdmx/2009/dimension#)> .\n";
echo "@prefix sdmx-attribute: <[purl.org](http://purl.org/linked-data/sdmx/2009/attribute#)> .\n";
echo "@prefix sdmx-concept: <[purl.org](http://purl.org/linked-data/sdmx/2009/concept#)> .\n";
echo "@prefix estat: <[eurostat.linked-statistics.org](http://eurostat.linked-statistics.org/)> .\n";
echo "@prefix estatdim: <[eurostat.linked-statistics.org](http://eurostat.linked-statistics.org/dimension/)> .\n";
echo "@prefix estatcode: <[eurostat.linked-statistics.org](http://eurostat.linked-statistics.org/code/)> .\n\n";

// Δήλωση του DataSet
echo "# Dataset Definition\n";
echo "$datasetUri a qb:DataSet ;\n";
echo "    rdfs:label \"Eurostat Dataset: $datasetId\" ;\n";
echo "    qb:structure estat:dsd/$datasetId .\n\n";

echo "# Observations\n";

$obsCounter = 0;
foreach ($seriesList as $series) {
    if ($series->getName() !== 'Series') continue;
    $dimensions = [];
    foreach ($series->attributes() as $key => $val) {
        $dimensions[strtoupper((string)$key)] = (string)$val;
    }
    foreach ($series->Obs as $obs) {
        $obsCounter++;
        $time = (string)$obs['TIME_PERIOD'];
        $value = (string)$obs['OBS_VALUE'];
        // Δημιουργία μοναδικού URI για το observation
        $obsUriParts = [];
        foreach ($dimensions as $dim => $val) {
            $obsUriParts[] = strtolower($dim) . "-" . strtolower($val);
        }
        $obsUriParts[] = "time-" . $time;
        $obsUri = "estat:obs/" . strtolower($datasetId) . "/" . implode("/", $obsUriParts);
        echo "$obsUri a qb:Observation ;\n";
        echo "    qb:dataSet $datasetUri ;\n";
        // Time dimension - χρήση sdmx-dimension:refPeriod
        echo "    sdmx-dimension:refPeriod \"$time\"^^xsd:gYear ;\n";
        // Measure - η τιμή
        if (is_numeric($value)) {
            echo "    sdmx-measure:obsValue \"$value\"^^xsd:decimal ;\n";
        } else {
            echo "    sdmx-measure:obsValue \"$value\" ;\n";
        }
        // Dimensions ως URIs
        foreach ($dimensions as $dim => $val) {
            $dimLower = strtolower($dim);
            $valLower = strtolower($val);
            // Ειδική μεταχείριση για γνωστές dimensions
            switch ($dim) {
                case 'GEO':
                case 'REF_AREA':
                    echo "    sdmx-dimension:refArea estatcode:geo/$valLower ;\n";
                    break;
                case 'FREQ':
                    echo "    sdmx-dimension:freq estatcode:freq/$valLower ;\n";
                    break;
                case 'UNIT':
                case 'UNIT_MEASURE':
                    echo "    sdmx-attribute:unitMeasure estatcode:unit/$valLower ;\n";
                    break;
                default:
                    // Custom Eurostat dimensions
                    echo "    estatdim:$dimLower estatcode:$dimLower/$valLower ;\n";
                    break;
            }
        }

        echo "    .\n\n";
    }
}

/* palio kako rdf

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
}*/
?>