<?php
header("Access-Control-Allow-Origin: *");
header("Content-type:text/javascript");
mb_language("japanese");
mb_internal_encoding("UTF-8");
mb_http_output("UTF-8");

$mbgd_endpoint = 'https://orth.dbcls.jp/sparql?query=';
/* $mbgd_endpoint = 'http://mbgd.genome.ad.jp:8047/sparql?query='; */
/*$mbgd_endpoint = 'http://mbgd.genome.ad.jp:3000/sparql?query=';*/
$dbpedia_endpoint = 'http://dbpedia.org/sparql?default-graph-uri=http%3A%2F%2Fdbpedia.org&query=';
/*$dbpedia_endpoint = 'http://mbgd.genome.ad.jp:3001/sparql?default-graph-uri=http%3A%2F%2Fdbpedia.org&query=';*/

function curlRequest($url){
    if (!function_exists('curl_init')){ 
	    die('Curl is not installed!');
    }
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('accept:application/sparql-results+json'));
    $json = curl_exec($ch);
    curl_close($ch);
    return $json;
}

if (isset($_GET['genome_type'])) { /* Get taxa as candidates */
    $query = '
PREFIX dct: <http://purl.org/dc/terms/>
PREFIX mbgd: <http://purl.jp/bio/11/mbgd#>
PREFIX orth: <http://purl.jp/bio/11/orth#>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX taxont: <http://ddbj.nig.ac.jp/ontologies/taxonomy/>
SELECT ?depth ?name ?taxid ?count
WHERE {
    {   
        SELECT ?taxid (COUNT(?genome) AS ?count)
        WHERE {
            ?genome a mbgd:' . $_GET['genome_type'] . ' ;
                    mbgd:inTaxon ?taxid . 
        }
    }
    ?taxid rdfs:label ?name ;
           taxont:rank ?rank .
#    VALUES ?rank { taxont:Superkingdom taxont:Kingdom taxont:Phylum taxont:Class taxont:Order taxont:Family taxont:Genus taxont:Species }
    ?rank mbgd:taxRankDepth ?depth .
}
#ORDER BY ?name
ORDER BY DESC(?count) ?depth ?name
            ';
    $jsonarray = json_decode(curlRequest($mbgd_endpoint . urlencode($query)), true);
    $array=array(); 
    for($i=0; $i< count($jsonarray['results']['bindings']); $i++) {
	    $name = $jsonarray['results']['bindings'][$i]['name']['value'];
	    $array[$name] = 1;
    }
    echo json_encode($array, true);


} else if (isset($_GET["sci_name"])) { /* Scientific name to taxID */
    $query = '
PREFIX taxont: <http://ddbj.nig.ac.jp/ontologies/taxonomy/>
PREFIX mbgd: <http://purl.jp/bio/11/mbgd#>
#SELECT DISTINCT ?taxon ?rank
SELECT ?rank ?taxon (COUNT(?organism) AS ?count)
WHERE {
    ?taxon taxont:scientificName "' . $_GET["sci_name"] . '" ;
           taxont:rank ?rank .
    ?organism mbgd:inTaxon ?taxon ;
           a mbgd:' . $_GET["genome_type_to_search"] . ' .
    VALUES ?rank { taxont:Superkingdom taxont:Kingdom taxont:Phylum taxont:Class taxont:Order taxont:Family taxont:Genus taxont:Species }
}
#ORDER BY DESC(?count)
ORDER BY ?count
           ';
    echo curlRequest($mbgd_endpoint . urlencode($query));


} else if (isset($_GET["taxid_to_get_dataset"])) {
    $query = '
PREFIX mbgd: <http://purl.jp/bio/11/mbgd#>
PREFIX mbgdr: <http://mbgd.genome.ad.jp/rdf/resource/>
SELECT ?count
WHERE {
    mbgdr:tax' . $_GET["taxid_to_get_dataset"] . ' mbgd:organismCount ?count .
}
    ';
    echo curlRequest($mbgd_endpoint . urlencode($query));


} else if (isset($_GET["taxid_to_get_upper"])) { /* Taxonomic hierarchy */
    $query = '
PREFIX taxid: <http://identifiers.org/taxonomy/>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX taxont: <http://ddbj.nig.ac.jp/ontologies/taxonomy/>
PREFIX mbgd: <http://purl.jp/bio/11/mbgd#>
#SELECT ?level ?rank ?label ?taxon
SELECT ?level ?rank ?label ?taxon (COUNT(?organism) AS ?count)
WHERE {
    taxid:' . $_GET["taxid_to_get_upper"] . ' rdfs:subClassOf ?taxon option(transitive, t_direction 1, t_min 1, t_step("step_no") as ?level) . # only for Virtuoso
    ?taxon taxont:rank ?rank ;
           taxont:scientificName ?label .
    ?organism mbgd:inTaxon ?taxon ;
           a mbgd:' . $_GET["genome_type_to_search"] . ' .
    VALUES ?rank { taxont:Superkingdom taxont:Kingdom taxont:Phylum taxont:Class taxont:Order taxont:Family taxont:Genus taxont:Species }
}
ORDER BY DESC(?level)
           ';
    echo curlRequest($mbgd_endpoint . urlencode($query));


} else if (isset($_GET["taxid_to_get_upper2"])) { /* Taxonomic hierarchy */
    $query = '
PREFIX taxid: <http://identifiers.org/taxonomy/>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX taxont: <http://ddbj.nig.ac.jp/ontologies/taxonomy/>
PREFIX mbgd: <http://purl.jp/bio/11/mbgd#>
#SELECT ?level ?rank ?label ?taxon
SELECT ?depth ?rank ?label ?taxon (COUNT(?organism) AS ?count)
WHERE {
    taxid:' . $_GET["taxid_to_get_upper2"] . ' rdfs:subClassOf+ ?taxon .
    ?taxon taxont:rank ?rank ;
           taxont:scientificName ?label .
    ?rank mbgd:taxRankDepth ?depth .
    ?organism mbgd:inTaxon ?taxon ;
           a mbgd:' . $_GET["genome_type_to_search"] . ' .
}
ORDER BY ?depth
           ';
    echo curlRequest($mbgd_endpoint . urlencode($query));


} else if (isset($_GET["taxid_to_get_lower"])) { /* Taxonomic hierarchy */
    if ($_GET["genome_type_to_search"] == "CompleteGenome") {
	    $property = "parentTaxonComplete";
    } else {
	    $property = "parentTaxonDraft";
    }
    $query = '
PREFIX mbgd: <http://purl.jp/bio/11/mbgd#>
PREFIX taxid: <http://identifiers.org/taxonomy/>
PREFIX taxont: <http://ddbj.nig.ac.jp/ontologies/taxonomy/>
SELECT ?rank ?label (COUNT(?organism) AS ?count)
WHERE {
    ?taxid mbgd:' . $property . ' taxid:' . $_GET["taxid_to_get_lower"] . ' ;
           taxont:scientificName ?label ;
	   taxont:rank ?rank .
    ?organism mbgd:inTaxon ?taxid ;
           a mbgd:' . $_GET["genome_type_to_search"] . ' .
}
ORDER BY ?label
           ';
    echo curlRequest($mbgd_endpoint . urlencode($query));


} else if (isset($_GET["taxid_to_get_sisters"])) { /* Taxonomic hierarchy */
    if ($_GET["genome_type_to_search"] == "CompleteGenome") {
	    $property = "parentTaxonComplete";
    } else {
	    $property = "parentTaxonDraft";
    }
    $query = '
PREFIX mbgd: <http://purl.jp/bio/11/mbgd#>
PREFIX taxid: <http://identifiers.org/taxonomy/>
PREFIX taxont: <http://ddbj.nig.ac.jp/ontologies/taxonomy/>
SELECT ?rank ?label ?taxid (COUNT(?organism) AS ?count)
WHERE {
    taxid:' . $_GET["taxid_to_get_sisters"] . ' mbgd:' . $property . ' ?parent .
    ?taxid mbgd:' . $property . ' ?parent .
    ?taxid taxont:scientificName ?label ;
	   taxont:rank ?rank .
    ?organism mbgd:inTaxon ?taxid ;
           a mbgd:' . $_GET["genome_type_to_search"] . ' .
}
ORDER BY ?label
           ';
    echo curlRequest($mbgd_endpoint . urlencode($query));


} else if (isset($_GET["tax_list_to_get_local"])) { /* Translate to local language */
    $query = '
PREFIX dbpedia: <http://dbpedia.org/resource/>
PREFIX dbo: <http://dbpedia.org/ontology/>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
SELECT ?dbpedia_resource ?label_local ?label_en
WHERE {
    VALUES (?dbpedia_resource) { ' . $_GET["tax_list_to_get_local"] . ' }
    OPTIONAL {
        ?dbpedia_resource dbo:wikiPageRedirects?/rdfs:label ?label_local .
        FILTER(lang(?label_local) = "' . $_GET["local_lang"] . '")
    }
    OPTIONAL {
        ?dbpedia_resource rdfs:label ?label_en .
        FILTER(lang(?label_en) = "en")
    }
}
        ';
    echo curlRequest($dbpedia_endpoint . urlencode($query));


} else if (isset($_GET["taxon_to_default_orgs"])) { /* Get default organisms in taxon */
    $query = '
PREFIX taxid: <http://identifiers.org/taxonomy/>
PREFIX mbgd: <http://purl.jp/bio/11/mbgd#>
PREFIX mbgdr: <http://mbgd.genome.ad.jp/rdf/resource/>
SELECT (COUNT(?genome) AS ?count)
WHERE {
    mbgdr:default mbgd:organism ?genome .
    ?genome mbgd:inTaxon taxid:' . $_GET["taxon_to_default_orgs"] . ' .
}
    ';
    echo curlRequest($mbgd_endpoint . urlencode($query));


} else if (isset($_GET["dbpedia_entry"])) { /* DBpedia information */
    $local_lang_list = '("en")';
    if ($_GET["local_lang"] && $_GET["local_lang"] != 'en') {
	    $local_lang_list = '("en") ("' . $_GET["local_lang"] . '")';
    }
    $query = '
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX dbo: <http://dbpedia.org/ontology/>
PREFIX dbpedia: <http://dbpedia.org/resource/>
SELECT ?label ?abst ?wiki ?image
WHERE {
    ' . $_GET["dbpedia_entry"] . ' dbo:wikiPageRedirects? ?dbpedia_entry .
    ?dbpedia_entry rdfs:label ?label ;
                   dbo:abstract ?abst ;
                   <http://xmlns.com/foaf/0.1/isPrimaryTopicOf> ?wiki . 
    OPTIONAL { 
        ?dbpedia_entry <http://xmlns.com/foaf/0.1/depiction> ?image .
    }
    BIND(lang(?label) AS ?lang)
    VALUES (?lang) {  ' . $local_lang_list . ' }
    BIND(lang(?abst) AS ?abst_lang)
    VALUES (?abst_lang) { ' . $local_lang_list . ' }
}
    ';
    echo curlRequest($dbpedia_endpoint . urlencode($query));


} else if (isset($_GET["taxon_to_search_genomes"])) { /* Search genomes under the taxon */
    $query = '
 PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
 PREFIX mbgd: <http://purl.jp/bio/11/mbgd#>
 PREFIX taxid: <http://identifiers.org/taxonomy/>
 PREFIX dct: <http://purl.org/dc/terms/>
 PREFIX taxont: <http://ddbj.nig.ac.jp/ontologies/taxonomy/>
 SELECT DISTINCT ?taxid ?code ?organism ?sum_genes ?sum_length ?date ?pubmed ?assembly
 WHERE {
    ?genome a mbgd:' . $_GET["genome_type_to_search"] . ' ;
        mbgd:inTaxon taxid:' . $_GET["taxon_to_search_genomes"] . ' ;
        rdfs:label ?organism ;
	mbgd:taxon ?taxid ;
        dct:identifier ?code ;
#        mbgd:assembly ?assembly ;
        dct:issued ?date .
    OPTIONAL {
	?genome dct:references ?pubmed .
    }
    {
	SELECT ?genome (SUM(?genes) AS ?sum_genes) (SUM(?length) AS ?sum_length)
	WHERE {
	    ?genome mbgd:nucSeq ?seq .
	    ?seq mbgd:geneCount ?genes ;
                 mbgd:nucLength ?length .
	}
    }
 }
 ORDER BY ?organism
        ';
    echo curlRequest($mbgd_endpoint . urlencode($query));
}

?>
