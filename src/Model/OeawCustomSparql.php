<?php

namespace Drupal\oeaw\Model;

use Drupal\oeaw\Model\OeawStorage;
use Drupal\oeaw\Helper\ModelFunctions as MF;
use Drupal\oeaw\ConfigConstants;
use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\FedoraResource;
use acdhOeaw\util\RepoConfig as RC;
use EasyRdf\Graph;
use EasyRdf\Resource;

/**
 * This class cointains the custom sparql queries for the ARCHE modul
 *
 * You can run this queries with the OEAWStorage->runUserSparql() function.
 *
 * @author nczirjak
 */
class OeawCustomSparql implements OeawCustomSparqlInterface
{
    private $modelFunctions;

    /**
     * Set up the necessary properties
     */
    public function __construct()
    {
        \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');
        $this->modelFunctions = new MF();
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
     * Creates the sparql for the complex search
     *
     * @param array $data
     * @param string $limit
     * @param string $page
     * @param bool $count
     * @param string $order
     * @param type $lang
     * @return string
     * @throws \ErrorException
     */
    public function createFullTextSparql(array $data, string $limit, string $page, bool $count = false, string $order = "datedesc", $lang = "en"): string
    {
        $wordsQuery = "";
        $query = "";
                
        if (count($data) <= 0) {
            return $query;
        }
        $lang = strtolower($lang);
        
        //Let's process the order argument
        switch ($order) {
            case "titleasc":
                $order = "ASC( fn:lower-case(?title))";
                break;
            case "titledesc":
                $order = "DESC( fn:lower-case(?title))";
                break;
            case "dateasc":
                $order = "ASC(?availableDate)";
                break;
            case "datedesc":
                $order = "DESC(?availableDate)";
                break;
            default:
                $order = "ASC( fn:lower-case(?title))";
        }

        
        $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> ';
        if ($count == true) {
            $select = "SELECT (COUNT(?uri) as ?count) ";
        } else {
            $select = 'SELECT DISTINCT ?uri ?title ?pid ?availableDate ?hasTitleImage ?acdhType ?accessRestriction 
                (GROUP_CONCAT(DISTINCT ?rdfType;separator=",") AS ?rdfTypes) 
                (GROUP_CONCAT(DISTINCT ?descriptions;separator=",") AS ?description) 
                (GROUP_CONCAT(DISTINCT ?author;separator=",") AS ?authors) 
                (GROUP_CONCAT(DISTINCT ?contrib;separator=",") AS ?contribs) 
                (GROUP_CONCAT(DISTINCT ?identifiers;separator=",") AS ?identifier) ';
        }
        
        $conditions = "";
        $query .= " ?uri ?prop ?obj . \n";
        $query .= $this->modelFunctions->filterLanguage("uri", RC::titleProp(), "title", $lang);
        //$query .= " ?uri <".RC::titleProp()."> ?title . \n
        $query .= "?uri <".RC::idProp()."> ?identifiers . \n       
            OPTIONAL { ?uri <".RC::get('epicPidProp')."> ?pid .  } 
            FILTER( ?prop IN (<".RC::titleProp().">, <".RC::get('drupalHasDescription').">, <".RC::get('drupalHasContributor')."> )) .   \n";
        
        if (isset($data["words"])) {
            $wd = explode('+', $data["words"]);
            $not = false;
            
            foreach ($wd as $w) {
                if ($w == "and") {
                    continue;
                }

                if ($w == "not") {
                    $not = true;
                    continue;
                }
                if ($not == true) {
                    $query .= "FILTER (!contains(lcase(?obj), lcase('".$w."' ))) .  \n";
                    $not = false;
                } else {
                    $query .= "FILTER (contains(lcase(?obj), lcase('".$w."' ))) .  \n";
                }
            }
        }
        
        //check the rdf types from the query
        if (isset($data["type"])) {
            $td = explode('+', $data["type"]);
            $not = false;
            $or = false;
            $storage =  new OeawStorage();
            $acdhTypes = $storage->getACDHTypes();
        
            if (count($acdhTypes) > 0) {
                $query .= " { ";
                foreach ($td as $dtype) {
                    foreach ($acdhTypes as $t) {
                        $val = explode(RC::get('fedoraVocabsNamespace'), $t["type"]);
                        $val = strtolower($val[1]);
                        
                        if ($dtype == "or") {
                            $or = true;
                            continue;
                        }
                        
                        if (($dtype == "not") || ($dtype == "and")) {
                            continue;
                        }
                        
                        if (strpos(strtolower($dtype), $val) !== false) {
                            if ($or == true) {
                                $query .= " UNION ";
                                $or = false;
                            }
                            $query .= " { SELECT * WHERE { ?uri <".RC::get('drupalRdfType')."> <".$t['type']."> . } }\n";
                        }
                    }
                }
                $query .= " } ";
            }
        }
        
        if (isset($data["years"])) {
            $yd = explode('+', $data["years"]);
            $years = array();
            foreach ($yd as $y) {
                if ($y == "or") {
                    continue;
                } else {
                    $years[]=$y;
                }
            }
            $maxYear = max($years);
            $minYear = min($years);
            $conditions .= " ?uri <".RC::get('drupalHasAvailableDate')."> ?date . \n";
            if (\DateTime::createFromFormat('Y', $maxYear) !== false && \DateTime::createFromFormat('Y', $minYear) !== false) {
                $query .= "FILTER (  xsd:dateTime(?date) <= '".$maxYear."-12-31T00:00:000+01:00'^^xsd:dateTime &&  xsd:dateTime(?date) >= '".$minYear."-01-01T00:00:000+01:00'^^xsd:dateTime)  \n";
            } else {
                //if we have a wrong date then we will select the actual date
                $min = date("Y");
                $query .= "FILTER ( (CONCAT(str(substr(?date, 0, 4)))) <= '".$min."' && (CONCAT(str(substr(?date, 0, 4)))) >= '".$min."')  \n";
            }
        } else {
            if (isset($data["mindate"]) && isset($data["maxdate"])) {
                if (!empty($data["mindate"]) && ($data["maxdate"])) {
                    if ((bool)strtotime($data["mindate"])) {
                        $mindate = new \DateTime($data["mindate"]);
                    } else {
                        throw new \ErrorException(t("Error").':'.t("Minimum").' '.t("Date"));
                    }
                    if ((bool)strtotime($data["maxdate"])) {
                        $maxdate = new \DateTime($data["maxdate"]);
                    } else {
                        throw new \ErrorException(t("Error").':'.t("Maximum").' '.t("Date"));
                    }
                    if (isset($mindate) && isset($maxdate)) {
                        $conditions .= " ?uri <".RC::get('drupalHasAvailableDate')."> ?date . \n";
                        $query .= "FILTER (str(?date) < '".$maxdate->format('Y-m-d')."' && str(?date) > '".$mindate->format('Y-m-d')."')  \n";
                    }
                } else {
                    throw new \ErrorException(t("Empty").':'.t("Minimum").' '.t("or").' '.t("Maximum").' '.t("Date"));
                }
            }
        }
        
        $query .= $this->modelFunctions->filterLanguage("uri", RC::get('drupalHasDescription'), "descriptions", $lang, true);
        $query .= ' ?uri  <'.RC::get("drupalRdfType").'> ?acdhType . '
                   . 'FILTER regex(str(?acdhType),"vocabs.acdh","i") .  ';
        $query .= "OPTIONAL{ ?uri <".RC::get('drupalHasAuthor')."> ?author .}	    	
        OPTIONAL{ ?uri <".RC::get('drupalHasContributor')."> ?contrib .}	
    	OPTIONAL{ ?uri <".RC::get('drupalRdfType')."> ?rdfType . }
        OPTIONAL{ ?uri <".RC::get('drupalHasTitleImage')."> ?hasTitleImage .}                
        OPTIONAL{ ?uri <".RC::get('drupalHasAvailableDate')."> ?availableDate . }";
        $query .= " OPTIONAL {?uri <".RC::get('fedoraAccessRestrictionProp')."> ?accessRestriction . } ";
        
        $query = $prefix.$select." Where { ".$conditions." ".$query." } GROUP BY ?title ?uri ?pid ?hasTitleImage ?availableDate ?acdhType ?accessRestriction ORDER BY " . $order;
        if ($limit) {
            $query .= " LIMIT ".$limit." ";
            if ($page) {
                $query .= " OFFSET ".$page." ";
            }
        }
        return $query;
    }
    
    /**
     * The sparql gets a fedora Collection all child elements till the depth 5
     *
     * @param string $url
     * @param string $lang
     * @return string
     */
    public function getCollectionBinaries(string $url, string $lang = "en"): string
    {
        $query = "";
        $lang = strtolower($lang);
        
        $query = ' select ?uri ?title ?rootTitle ?binarySize ?filename ?path ?accessRestriction ?parentId ?resShortId (GROUP_CONCAT(DISTINCT ?identifiers;separator=",") AS ?identifier)  where { ';
        //get the child elements with inverse link
        $query .= " <".$url."> ( <".RC::get('fedoraRelProp')."> / ^<".RC::get('fedoraIdProp').">)* ?main . ";
        //create a uri to we can work on with the child elements, to get their data/properties
        $query .= " ?main (<".RC::get('fedoraIdProp')."> / ^<".RC::get('fedoraRelProp').">)+ ?uri . ";
        
        $query .= " OPTIONAL {  ";
        $query .= $this->modelFunctions->filterLanguage("uri", RC::titleProp(), "title", $lang);
        $query .= " } ";
      
        $query .= " OPTIONAL {  ";
        $query .= " ?uri <".RC::get('fedoraIdProp')."> ?identifiers . ";
        $query .= " } ";
        
        $query .= " OPTIONAL {  ";
        $query .= " ?uri <".RC::get('fedoraRelProp')."> ?isPartOf . ";
        $query .= " ?rUri <".RC::get('fedoraIdProp')."> ?isPartOf . ";
        $query .= $this->modelFunctions->filterLanguage("rUri", RC::titleProp(), "rootTitle", $lang);
        $query .=  ' BIND(REPLACE(str(?isPartOf), "https://id.acdh.oeaw.ac.at/uuid/", "", "i") AS ?parentId) . ';
        $query .= " } ";
                
        $query .= " OPTIONAL {  ";
        $query .= " ?uri <".RC::get('fedoraAccessRestrictionProp')."> ?accessRestriction . ";
        $query .= " } ";
        
        $query .= " OPTIONAL {  ";
        $query .= " ?uri <".RC::get('fedoraExtentProp')."> ?binarySize .
            ?uri <http://www.ebu.ch/metadata/ontologies/ebucore/ebucore#filename> ?filename .  
            ?uri <".RC::get('fedoraLocProp')."> ?path . ";
        $query .= " } ";
        
        $query .= " OPTIONAL {  ";
        $query .= " ?uri <".RC::get('fedoraIdProp')."> ?resOwnId . ";
        $query .= " FILTER (contains(lcase(str(?resOwnId)), lcase('https://id.acdh.oeaw.ac.at/uuid/' ))) .";
        $query .= ' BIND(REPLACE(str(?resOwnId), "https://id.acdh.oeaw.ac.at/uuid/", "", "i") AS ?resShortId) .';
        $query .= " } ";
        
        $query .= " } ";
        $query .= "
            GROUP BY ?uri ?title ?rootTitle ?binarySize ?filename ?path ?accessRestriction ?parentId ?resShortId
            ORDER BY ?rootTitle ?isPartOf ?filename ?title 
        ";

        return $query;
    }
}
