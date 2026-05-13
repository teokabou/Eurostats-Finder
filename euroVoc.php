<?php
function eurovocLiveExpand($term) {
    $endpoint = "https://publications.europa.eu/webapi/rdf/sparql";
    // basic sanitization
    $safeTerm = preg_replace('/[^a-zA-Z0-9 ]/', '', $term);
    $query = "
    PREFIX skos: <http://www.w3.org/2004/02/skos/core#>

    SELECT DISTINCT ?label WHERE {
      ?concept skos:prefLabel ?label .
      FILTER (LANG(?label) = 'en')
      FILTER (CONTAINS(LCASE(?label), LCASE('$safeTerm')))
    }
    LIMIT 10
    ";
    $url = $endpoint . "?query=" . urlencode($query) . "&format=json";
    $response = @file_get_contents($url);
    if (!$response) {
        return []; // fallback
    }
    $json = json_decode($response, true);
    if (!isset($json['results']['bindings'])) {
        return [];
    }
    $results = [];
    foreach ($json['results']['bindings'] as $row) {
        $results[] = $row['label']['value'];
    }
    return array_unique($results);
}
?>