<?php


namespace Drupal\oeaw\Model;

use acdhOeaw\util\RepoConfig as RC;
use Drupal\oeaw\Helper\ModelFunctions;
use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\metadataQuery\SimpleQuery;

/**
 * Description of ApiModel
 *
 * @author nczirjak
 */
class ApiModel
{
    private $modelFunctions;
    private $fedora;
        
    public function __construct()
    {
        $this->modelFunctions = new ModelFunctions();
        $this->fedora = new Fedora();
    }
    
    /**
    * This function creates a sparql query for the Basic API calls by type
    *
    * @param string $str
    * @param string $type
    * @param array $filters
    * @return string
    */
    public function createBasicApiSparql(string $str, string $type, array $filters = array()): string
    {
        $query = "";
        if (empty($str) || empty($type)) {
            return $query;
        }
        
        if (count($filters) == 0) {
            $filters[] = RC::get('fedoraTitleProp');
            $filters[] = RC::get('drupalHasAlternativeTitle');
            $filters[] = RC::get('fedoraIdProp');
        }

        $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> ';
        $select = 'SELECT DISTINCT ?uri ?title ?altTitle (GROUP_CONCAT(DISTINCT ?identifier;separator=",") AS ?identifiers)   ';
        $where = "WHERE {"
                . " { "
                . "?uriM rdfs:subClassOf <".$type."> . "
                . "?uriM <".RC::get('fedoraIdProp')."> ?id . "
                ." ?uri <".RC::get('drupalRdfType')."> ?id . "
                . "?uri ?prop ?obj . ";
        $where  .= "?uri <".RC::get('fedoraTitleProp')."> ?title . ";
        $where  .= "FILTER( ?prop IN ( ";
                
        for ($x = 0; $x <= count($filters) - 1; $x++) {
            $where .= "<".$filters[$x]."> ";
            if ($x !== count($filters) - 1) {
                $where .= ", ";
            }
        }
        //<".RC::get('fedoraTitleProp').">, <".RC::get('drupalHasAlternativeTitle').">, <".RC::get('fedoraIdProp').">
        $where .= " )) . "
                . "FILTER (contains(lcase(str(?obj)), lcase('".$str."' ))) .  "
                . "?uri <".RC::get('fedoraIdProp')."> ?identifier ."
                . "OPTIONAL { ?uri <".RC::get('drupalHasAlternativeTitle')."> ?altTitle . } . "
                . " } UNION { "
                . " ?uri <".RC::get('drupalRdfType').">  <".$type."> . "
                . " ?uri ?prop ?obj . ";
        
        $where  .= "?uri <".RC::get('fedoraTitleProp')."> ?title . ";
        $where  .= "FILTER( ?prop IN ( ";
                
        for ($x = 0; $x <= count($filters) - 1; $x++) {
            $where .= "<".$filters[$x]."> ";
            if ($x !== count($filters) - 1) {
                $where .= ", ";
            }
        }
        $where .= " )) . "
                . "FILTER (contains(lcase(str(?obj)), lcase('".$str."' ))) .  "
                . "?uri <".RC::get('fedoraIdProp')."> ?identifier ."
                . "OPTIONAL { ?uri <".RC::get('drupalHasAlternativeTitle')."> ?altTitle . } . "
                . " }";
        $where .= "}";
        $groupby = ' GROUP BY ?title ?uri ?altTitle ';
        $orderby = ' ORDER BY ASC( fn:lower-case(?title)) LIMIT 10 ';
        
        $query = $prefix.$select.$where.$groupby.$orderby;
        return $query;
    }
    
    
    /**
     * This function creates a sparql query for the Persons API call
     *
     * @param string $str : search text
     * @param string $lang
     * @return string
     */
    public function createPersonsApiSparql(string $str): string
    {
        $query = "";
        
        if (empty($str)) {
            return $query;
        }
        $lang = strtolower($lang);
        
        $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> ';
        $select = 'SELECT DISTINCT ?uri ?title (GROUP_CONCAT(DISTINCT ?identifier;separator=",") AS ?identifiers)   ';
        $where = "WHERE {"
                . "?uri ?prop ?obj . ";
        $where .= "?uri <".RC::get('fedoraTitleProp')."> ?title . ";
        $where .= "FILTER( ?prop IN (<".RC::get('fedoraTitleProp').">, <".RC::get('drupalHasLastName').">, <".RC::get('drupalHasFirstName').">, <".RC::get('fedoraIdProp')."> )) . "
                . "FILTER (contains(lcase(str(?obj)), lcase('".$str."' ))) .  "
                . "?uri <".RC::get('fedoraIdProp')."> ?identifier ."
                . "?uri <".RC::get('drupalRdfType')."> <".RC::get('drupalPerson')."> . "
                . "}";
        $groupby = ' GROUP BY ?title ?uri ';
        $orderby = ' ORDER BY ASC( fn:lower-case(?title)) LIMIT 10 ';
        
        $query = $prefix.$select.$where.$groupby.$orderby;
       
        return $query;
    }
    
    
    /**
     * This function creates a sparql query for the GND Persons API call
     *
     * @param string $order
     * @param string $limit
     * @return string
     */
    public function createGNDPersonsApiSparql(): string
    {
        $query = "";
        
        $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> ';
        $select = ' SELECT ?lname ?fname ?dnb ?identifier ';
        $where = " WHERE {"
                . " ?uri <".RC::get('drupalRdfType')."> <".RC::get('drupalPerson')."> . "
                . " ?uri <".RC::get('fedoraIdProp')."> ?dnb . "
                . " FILTER (contains(lcase(str(?dnb)), lcase('d-nb.info/gnd/' ))) . "
                . " ?uri <".RC::get('fedoraIdProp')."> ?identifier .  "
                . " FILTER (contains(lcase(str(?identifier)), lcase('id.acdh.oeaw.ac.at/uuid/' ))) . "
                . " ?uri <".RC::get('drupalHasLastName')."> ?lname . "
                . " ?uri <".RC::get('drupalHasFirstName')."> ?fname . "
                . " } ";
        
        $groupby = ' GROUP BY ?lname ?fname ?dnb ?identifier ';
        $orderby = ' ORDER BY asc ( fn:lower-case(?lname)) ';
        $query = $prefix.$select.$where.$groupby.$orderby;
       
        return $query;
    }
    
    /**
     * This function creates a sparql query for the Publication API call
     * @param string $str : query string
     * @param string $lang
     * @return string
     */
    public function createPublicationsApiSparql(string $str, string $lang = "en"): string
    {
        $query = "";
        
        if (empty($str)) {
            return $query;
        }
        $lang = strtolower($lang);
        
        $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> ';
        $select = 'SELECT DISTINCT ?uri ?title (GROUP_CONCAT(DISTINCT ?identifier;separator=",") AS ?identifiers)'
                . ' (GROUP_CONCAT(DISTINCT ?author;separator=",") AS ?authors) (GROUP_CONCAT(DISTINCT ?editor;separator=",") AS ?editors)  ';
        $where = "WHERE {"
                . "?uri ?prop ?obj . ";
        
        $where .= "?uri <".RC::get('fedoraTitleProp')."> ?title . ";
        $where .= "FILTER( ?prop IN (<".RC::get('fedoraTitleProp').">, <".RC::get('drupalHasAlternativeTitle').">, "
                . " <".RC::get('drupalHasAuthor').">, <".RC::get('drupalHasEditor').">, <".RC::get('fedoraIdProp')."> )) . "
                . "FILTER (contains(lcase(str(?obj)), lcase('".$str."' ))) .  "
                . "?uri <".RC::get('fedoraIdProp')."> ?identifier ."
                . "?uri <".RC::get('drupalRdfType')."> <".RC::get('drupalPublication')."> . "
                . "OPTIONAL { ?uri <".RC::get('drupalHasAlternativeTitle')."> ?altTitle . } . "
                . "OPTIONAL { ?uri <".RC::get('drupalHasAuthor')."> ?author . } . "
                . "OPTIONAL { ?uri <".RC::get('drupalHasEditor')."> ?editor . } . "
                . "}";
        $groupby = ' GROUP BY ?title ?uri ';
        $orderby = ' ORDER BY ASC( fn:lower-case(?title)) LIMIT 10 ';
        
        $query = $prefix.$select.$where.$groupby.$orderby;
        return $query;
    }
    
    /**
    *
    * @param string $classString
    * @param string $lang
    * @return array
    */
    public function getMetadataForGuiTable(string $classString, string $lang = "en"): array
    {
        if (empty($classString)) {
            return drupal_set_message(t('Empty').' '.t('Values').' -->'.__FUNCTION__, 'error');
        }
        
        $lang = strtolower($lang);
        
        $prefix = "prefix owl: <http://www.w3.org/2002/07/owl#> "
                . "prefix skos: <http://www.w3.org/2004/02/skos/core#> ";
                
        $select = "select ?uri ?property ?machine_name ?ordering ?maxCardinality ?minCardinality ?cardinality "
                . "(GROUP_CONCAT(DISTINCT ?recommendedClasses;separator=',') AS ?recommendedClass)  "
                . "where { ";
        
        $where = " ?mainURI <".RC::get('fedoraIdProp').">  <".RC::get('fedoraVocabsNamespace').$classString."> . ";
        $where .= "?mainURI (rdfs:subClassOf / ^<".RC::get('fedoraIdProp').">)* / rdfs:subClassOf ?class . ";
        $where .= " ?uri rdfs:domain ?class . ";
        
        $where .= " ?uri <".RC::get('fedoraIdProp')."> ?propID . ";
        
        $where .= $this->modelFunctions->filterLanguage("uri", "http://www.w3.org/2004/02/skos/core#altLabel", "property", $lang, false);
        
        $optionals = "OPTIONAL {
            ?uri <".RC::get('fedoraTitleProp')."> ?machine_name .
        }";
        
        $optionals .= "Optional {
            ?uri <".RC::get('fedoraVocabsNamespace')."ordering> ?ordering .
        }";
        $optionals .= "
            OPTIONAL {
                ?uri <".RC::get('fedoraVocabsNamespace')."recommendedClass> ?recommendedClasses .
            }
        ";
        
        $optionals .= "OPTIONAL {
  		?uriProp owl:onProperty ?propID .
                OPTIONAL {
                    ?uriProp <".RC::get('drupalOwlMaxCardinality')."> ?maxCardinality .
                }
                OPTIONAL {
                    ?uriProp <".RC::get('drupalOwlMinCardinality')."> ?minCardinality .
                }
                OPTIONAL {
                    ?uriProp <".RC::get('drupalOwlCardinality')."> ?cardinality .
                }
        }";
        
        
        $groupby = " GROUP BY ?uri ?property ?machine_name ?ordering ?maxCardinality ?minCardinality ?cardinality ";
        $orderby = " ORDER BY ?ordering ";
        
        $string = $prefix.$select.$where.$optionals." } ".$groupby.$orderby;
        
        try {
            $q = new SimpleQuery($string);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            
            $fields = $res->getFields();
            $result = $this->modelFunctions->createSparqlResult($res, $fields);
            return $result;
        } catch (\Exception $ex) {
            return array();
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return array();
        }
    }
    
    /**
     * Get the digital rescources Meta data and the cardinality data by ResourceUri
     *
     * @param string $classString
     * @param string $lang
     * @return array
     */
    public function getClassMetaForApi(string $classString, string $lang = ""): array
    {
        if (empty($classString)) {
            return drupal_set_message(t('Empty').' '.t('Values').' -->'.__FUNCTION__, 'error');
        }
        
        (empty($lang)) ? $lang = strtolower($this->siteLang) : $lang = strtolower($lang);
        
        $prefix = "prefix owl: <http://www.w3.org/2002/07/owl#> "
                . "prefix skos: <http://www.w3.org/2004/02/skos/core#> ";
                
        $select = "select ?uri ?propID ?propTitle ?range ?subUri ?cardinality ?maxCardinality ?minCardinality ?order ?vocabs "
                . "(GROUP_CONCAT(DISTINCT ?comments;separator=',') AS ?comment) "
                . "(GROUP_CONCAT(DISTINCT ?recommendedClasses;separator=',') AS ?recommendedClass)  "
                . "where { ";
        
        $where = " ?mainURI <".RC::get('fedoraIdProp').">  <".RC::get('fedoraVocabsNamespace').$classString."> . ";
        $where .= "?mainURI (rdfs:subClassOf / ^<".RC::get('fedoraIdProp').">)* / rdfs:subClassOf ?class . ";
        $where .= "{ ?uri rdfs:domain ?class . ";
        $where .= $this->modelFunctions->filterLanguage("uri", "http://www.w3.org/2004/02/skos/core#altLabel", "propTitle", $lang, false);
        $where .= "} UNION { ";
        $where .= " ?mainURI <".RC::get('fedoraIdProp')."> ?mainID .";
        $where .= " ?uri rdfs:domain ?mainID . ";
        $where .= $this->modelFunctions->filterLanguage("uri", "http://www.w3.org/2004/02/skos/core#altLabel", "propTitle", $lang, false);
        $where .= " } ";
        $where .= "?uri <".RC::get('fedoraIdProp')."> ?propID . ";
        
        $optionals = "	
            OPTIONAL {
                ?uri <".RC::get('fedoraVocabsNamespace')."ordering> ?order .
            }
            OPTIONAL {
                ?uri <".RC::get('fedoraVocabsNamespace')."recommendedClass> ?recommendedClasses .
            }
            OPTIONAL {
                ?uri <".RC::get('fedoraVocabsNamespace')."vocabs> ?vocabs .
            }";
        $optionals .= $this->modelFunctions->filterLanguage("uri", RC::get('drupalRdfsComment'), "comments", $lang, true);
        $optionals .= " OPTIONAL{ 
                SELECT  * WHERE { 
                    ?uri rdfs:range ?range .
                }
            } ";
        $optionals .= "OPTIONAL {
  		?subUri owl:onProperty ?propID .
                OPTIONAL {
                    ?subUri <".RC::get('drupalOwlMaxCardinality')."> ?maxCardinality .
                }
                OPTIONAL {
                    ?subUri <".RC::get('drupalOwlMinCardinality')."> ?minCardinality .
                }
                OPTIONAL {
                    ?subUri <".RC::get('drupalOwlCardinality')."> ?cardinality .
                }
        }";
        
        $groupby = " GROUP BY ?uri ?propID ?propTitle ?range ?subUri ?cardinality ?maxCardinality ?minCardinality ?order ?vocabs"
                . " ORDER BY ?order";
        $string = $prefix.$select.$where.$optionals." } ".$groupby;
        
        try {
            $q = new SimpleQuery($string);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            
            $fields = $res->getFields();
            $result = $this->modelFunctions->createSparqlResult($res, $fields);
            return $result;
        } catch (\Exception $ex) {
            return array();
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return array();
        }
    }
    
    /**
     * Count the actual main (root) collections
     * 
     * @return string
     */
    private function countMainCollections(): string {
        $string = "";
                
        $select = 'SELECT (count(distinct ?uri) as ?collections) ';
        $where = "WHERE { ";
        $where .= "?uri rdf:type <".RC::get('drupalCollection')."> . ";
        $where .= " FILTER NOT EXISTS  { ";
            $where .= "?uri <".RC::get('fedoraRelProp')."> ?root . ";
        $where .= " }";
        $where .= " }";
        
        $string = $select.$where;
        try {
            $q = new SimpleQuery($string);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            
            $fields = $res->getFields();
            $result = $this->modelFunctions->createSparqlResult($res, $fields);
            if(isset($result[0]['collections'])) {
                return $result[0]['collections'];
            }             
            return "";
        } catch (\Exception $ex) {
            return "";
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return "";
        }
    }
    
    /**
     * Count the actual binaries in the fedora db
     * 
     * @return string
     */
    private function countBinaries(): string {
        $string = "";
                
        $select = 'SELECT (count(distinct ?uri) as ?binaries) ';
        $where = "WHERE { ";
        $where .= "?uri rdf:type <".RC::get('fedoraVocabsNamespace')."Resource> . ";
        $where .= " } "; 
        
        $string = $select.$where;
        
        try {
            $q = new SimpleQuery($string);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            $fields = $res->getFields();
            $result = $this->modelFunctions->createSparqlResult($res, $fields);
            
            if(isset($result[0]['binaries'])) {
                return $result[0]['binaries'];
            }             
            return "";
        } catch (\Exception $ex) {
            return "";
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return "";
        }
    }
    
    /**
     * return the collection and binaries text for the ckeditor plugin
     * 
     * @return string
     */
    public function getDataForJSPlugin(string $lng = "en"): array {
        $binaries = "";
        $collections = "";
        
        $binaries = $this->countBinaries();
        $collections = $this->countMainCollections();
        return array("collections" => $collections, "resources" => $binaries);
    }
}
