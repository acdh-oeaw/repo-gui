<?php

namespace Drupal\oeaw\Model;

use Drupal\oeaw\Model\OeawStorage;
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
class OeawCustomSparql implements OeawCustomSparqlInterface {
    
    
    /**
     * 
     * This function creates a sparql query for the Persons API call
     * 
     * @param string $str : search text
     */
    public function createPersonsApiSparql(string $str): string {
        
        $query = "";
        
        if(empty($str)){
            return $query;
        }
        
        $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> ';
        $select = 'SELECT DISTINCT ?uri ?title (GROUP_CONCAT(DISTINCT ?identifier;separator=",") AS ?identifiers)   ';
        $where = "WHERE {"
                . "?uri ?prop ?obj . "
                . "?uri <".RC::get('fedoraTitleProp')."> ?title . "
                . "FILTER( ?prop IN (<".RC::get('fedoraTitleProp').">, <".RC::get('drupalHasLastName').">, <".RC::get('drupalHasFirstName').">, <".RC::get('fedoraIdProp')."> )) . "
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
     * 
     * This function creates a sparql query for the Publication API call
     * 
     * @param string $str : search text
     */
    public function createPublicationsApiSparql(string $str): string {
        
        $query = "";
        
        if(empty($str)){
            return $query;
        }
        
        $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> ';
        $select = 'SELECT DISTINCT ?uri ?title (GROUP_CONCAT(DISTINCT ?identifier;separator=",") AS ?identifiers)'
                . ' (GROUP_CONCAT(DISTINCT ?author;separator=",") AS ?authors) (GROUP_CONCAT(DISTINCT ?editor;separator=",") AS ?editors)  ';
        $where = "WHERE {"
                . "?uri ?prop ?obj . "
                . "?uri <".RC::get('fedoraTitleProp')."> ?title . "
                . "FILTER( ?prop IN (<".RC::get('fedoraTitleProp').">, <".RC::get('drupalHasAlternativeTitle').">, <".RC::get('drupalHasAuthor').">, <".RC::get('drupalHasEditor').">, <".RC::get('fedoraIdProp')."> )) . "
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
     * This function creates a sparql query for the Basic API calls by type
     * 
     * @param string $str : search text
     */
    public function createBasicApiSparql(string $str, string $type): string {
        
        $query = "";
        
        if(empty($str) || empty($type)){
            return $query;
        }
        
        $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> ';
        $select = 'SELECT DISTINCT ?uri ?title ?altTitle (GROUP_CONCAT(DISTINCT ?identifier;separator=",") AS ?identifiers)   ';
        $where = "WHERE {"
                . "?uri ?prop ?obj . "
                . "?uri <".RC::get('fedoraTitleProp')."> ?title . "
                . "FILTER( ?prop IN (<".RC::get('fedoraTitleProp').">, <".RC::get('drupalHasAlternativeTitle').">, <".RC::get('fedoraIdProp')."> )) . "
                . "FILTER (contains(lcase(str(?obj)), lcase('".$str."' ))) .  "
                . "?uri <".RC::get('fedoraIdProp')."> ?identifier ."
                . "OPTIONAL { ?uri <".RC::get('drupalHasAlternativeTitle')."> ?altTitle . } . "
                . "?uri <".RC::get('drupalRdfType')."> <".$type."> . "
                . "}";
        $groupby = ' GROUP BY ?title ?uri ?altTitle ';
        $orderby = ' ORDER BY ASC( fn:lower-case(?title)) LIMIT 10 ';
        
        $query = $prefix.$select.$where.$groupby.$orderby;
        
        return $query;
        
    }
    
     /**
     * 
     * Creates the sparql for the complex search
     * 
     * @param array $data
     * @param string $limit
     * @param string $page
     * @param bool $count
     * @param string $order
     * @return string
     */
    public function createFullTextSparql(array $data, string $limit, string $page, bool $count = false, string $order = "datedesc"): string{

        $wordsQuery = "";
        $query = "";
                
        if(count($data) <= 0){
            return $query;
        }
        
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
        if($count == true){
            $select = "SELECT (COUNT(?uri) as ?count) ";
        }else {
            $select = 'SELECT DISTINCT ?uri ?title ?pid ?availableDate ?hasTitleImage (GROUP_CONCAT(DISTINCT ?rdfType;separator=",") AS ?rdfTypes) 
                        (GROUP_CONCAT(DISTINCT ?descriptions;separator=",") AS ?description)                       
                        (GROUP_CONCAT(DISTINCT ?author;separator=",") AS ?authors) 
                        (GROUP_CONCAT(DISTINCT ?contrib;separator=",") AS ?contribs) 
                        (GROUP_CONCAT(DISTINCT ?identifiers;separator=",") AS ?identifier) ';
        }
        
        $conditions = "";
        $query .= "?uri ?prop ?obj . \n
            ?uri <".RC::titleProp()."> ?title . \n
            ?uri <".RC::idProp()."> ?identifiers . \n       
            OPTIONAL { ?uri <".RC::get('epicPidProp')."> ?pid .  } 
            FILTER( ?prop IN (<".RC::titleProp().">, <".RC::get('drupalHasDescription').">, <".RC::get('drupalHasContributor')."> )) .   \n";
        
        if(isset($data["words"])){
            $wd = explode('+', $data["words"]);
            $not = false;
            
            foreach ($wd as $w){

                if($w == "and"){ continue; }

                if($w == "not"){
                    $not = true;
                    continue;
                }
                if($not == true){
                    $query .= "FILTER (!contains(lcase(?obj), lcase('".$w."' ))) .  \n";
                    $not = false;
                }else {
                    $query .= "FILTER (contains(lcase(?obj), lcase('".$w."' ))) .  \n";
                }
            }
        }
        
        //check the rdf types from the query
        if(isset($data["type"])){
            
            $td = explode('+', $data["type"]);
            $not = false;
            $or = false;
            $storage =  new OeawStorage();
            $acdhTypes = $storage->getACDHTypes();
        
            if(count($acdhTypes) > 0){
                $query .= " { ";    
                foreach($td as $dtype){                        
                    foreach($acdhTypes as $t){
                        
                        $val = explode(RC::get('fedoraVocabsNamespace'), $t["type"]);
                        $val = strtolower($val[1]);
                        
                        if($dtype == "or"){ $or = true; continue;}
                        
                        if( ($dtype == "not") || ($dtype == "and") ){
                            continue;
                        }
                        
                        if (strpos(strtolower($dtype), $val) !== false) {
                            if($or == true){$query .= " UNION "; $or = false;}
                            $query .= " { SELECT * WHERE { ?uri <".RC::get('drupalRdfType')."> <".$t['type']."> . } }\n";
                        }
                    }
                }
                $query .= " } ";
            }            
        }
        
        if(isset($data["years"])){
            
            $yd = explode('+', $data["years"]);
            $years = array();
            foreach ($yd as $y){
                if($y == "or"){continue;}else{ $years[]=$y;
                }
            }
            $maxYear = max($years);
            $minYear = min($years);
            $conditions .= " ?uri <".RC::get('drupalHasAvailableDate')."> ?date . \n";
            if (\DateTime::createFromFormat('Y', $maxYear) !== FALSE && \DateTime::createFromFormat('Y', $minYear) !== FALSE) {                
                $query .= "FILTER ( (CONCAT(str(substr(?date, 0, 4)))) <= '".$maxYear."' && (CONCAT(str(substr(?date, 0, 4)))) >= '".$minYear."')  \n";
            }else {
                //if we have a wrong date then we will select the actual date
                $min = date("Y");
                $query .= "FILTER ( (CONCAT(str(substr(?date, 0, 4)))) <= '".$min."' && (CONCAT(str(substr(?date, 0, 4)))) >= '".$min."')  \n";
            }
            
        }else{
            if(isset($data["mindate"]) && isset($data["maxdate"])){
            
                if( (bool)strtotime($data["mindate"])  ){
                    $mindate = new \DateTime($data["mindate"]);
                }else  {
                    $msg = base64_encode("The Minimum date is wrong!");
                    $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
                    $response->send();
                    return "";
                }
                if( (bool)strtotime($data["maxdate"]) ){
                    $maxdate = new \DateTime($data["maxdate"]);
                }else  {
                    $msg = base64_encode("The Maximum date is wrong!");
                    $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
                    $response->send();
                    return "";
                }
                
                $conditions .= " ?uri <".RC::get('drupalHasAvailableDate')."> ?date . \n";
                $query .= "FILTER (str(?date) < '".$maxdate->format('Y-m-d')."' && str(?date) > '".$mindate->format('Y-m-d')."')  \n";
            }
        }
        
        $query .= "OPTIONAL{ ?uri <".RC::get('drupalHasDescription')."> ?descriptions .}                
    	OPTIONAL{ ?uri <".RC::get('drupalHasAuthor')."> ?author .}	    	
        OPTIONAL{ ?uri <".RC::get('drupalHasContributor')."> ?contrib .}	
    	OPTIONAL{ ?uri <".RC::get('drupalRdfType')."> ?rdfType . }
        OPTIONAL{ ?uri <".RC::get('drupalHasTitleImage')."> ?hasTitleImage .}                
        OPTIONAL{ ?uri <".RC::get('drupalHasAvailableDate')."> ?availableDate . }";
        
        $query = $prefix.$select." Where { ".$conditions." ".$query." } GROUP BY ?title ?uri ?pid ?hasTitleImage ?availableDate ORDER BY " . $order;
        if($limit){
            $query .= " LIMIT ".$limit." ";
            if($page){
                $query .= " OFFSET ".$page." ";
            }
        }
        return $query;
    }
    
    /**
     * 
     * The sparql gets a fedora Collection all child elements till the depth 5
     * 
     * @param string $url
     * @return string
     */
    public function getCollectionBinaries(string $url): string{
        
        $query = "";
        //?nUri <".RC::get('drupalRdfType')."> ?types .
        
        $query = "
        select ?uri ?title ?rootTitle ?binarySize ?filename 
        where {  
            ?uri ( <".RC::get('fedoraRelProp')."> / ^<".RC::get('fedoraIdProp').">)* <".$url."> .
            FILTER(?uri = ?nUri){
                select ?nUri ?title ?rootTitle  ?binarySize ?filename 
                where {
                    ?nUri <".RC::get('fedoraTitleProp')."> ?title .
                    ?nUri <".RC::get('fedoraRelProp')."> ?isPartOf .
                    ?rUrl <".RC::get('fedoraIdProp')."> ?isPartOf .
                    ?rUrl <".RC::get('fedoraTitleProp')."> ?rootTitle .
                    OPTIONAL { 
                        ?nUri <".RC::get('fedoraExtentProp')."> ?binarySize .
                        ?nUri <http://www.ebu.ch/metadata/ontologies/ebucore/ebucore#filename> ?filename . 
                    }
                }
            }
        }
        GROUP BY ?uri ?title ?rootTitle ?binarySize ?filename
        ORDER BY ?filename ?title ?rootTitle
        ";
        
        return $query;
        
    }
    
}
