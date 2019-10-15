<?php

namespace Drupal\oeaw\Model;

use acdhOeaw\util\RepoConfig as RC;
use Drupal\oeaw\Helper\ModelFunctions;

/**
 * Description of SearchViewModel
 *
 * @author nczirjak
 */
class ComplexSearchViewModel {
    
    private $modelFunctions;
    private $fedora;
    
    public function __construct($fedora) {
        \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');
        $this->modelFunctions = new ModelFunctions();
        $this->fedora = $fedora;
    }
        
    //createFullTextSparql
    //createBGFullTextSparql
    public function createBGFullTextSparql(array $data, string $limit, string $page, bool $count = false, string $order = "datedesc", $lang = "en"): string
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

        
        $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> '
                . 'PREFIX bds: <http://www.bigdata.com/rdf/search#> ';
        
        if ($count == true) {
            $select = "SELECT (COUNT(?uri) as ?count) ";
        } else {
            $select = 'SELECT DISTINCT ?uri ?title ?label ?pid ?availableDate ?hasTitleImage ?acdhType ?accessRestriction 
                (GROUP_CONCAT(DISTINCT ?rdfType;separator=",") AS ?rdfTypes) 
                (GROUP_CONCAT(DISTINCT ?descriptions;separator=",") AS ?description) 
                (GROUP_CONCAT(DISTINCT ?author;separator=",") AS ?authors) 
                (GROUP_CONCAT(DISTINCT ?contrib;separator=",") AS ?contribs) 
                (GROUP_CONCAT(DISTINCT ?identifiers;separator=",") AS ?identifier) ';
        }
        
        $conditions = "";
        
        //the main part
        
	$query .= " ?uri <".RC::idProp()."> ?identifiers .  ";
        
        //$query .= $this->modelFunctions->filterLanguage("uri", RC::titleProp(), "title", $lang);
        $not = "";
        //check the keywords
        if (isset($data["words"])) {
            $query .= " ?uri (<".RC::titleProp().">|<".RC::get('drupalHasDescription').">|<".RC::get('drupalHasContributor')."> ) ?label . ";
            $wd = explode('+', $data["words"]);
            $not = "";
            foreach ($wd as $k => $w) {
                
                if(!empty($not) && ( $not == $w )) {
                    continue;
                }
                
                if ($w == "not") {
                    if(isset($wd[$k+1])) {
                        $not = $wd[$k+1];
                    }
                    continue;
                }
                
                if ($w == "and") {
                    $query .="  UNION  ";
                    continue;
                }else {
                    $query .= " { ";
                
                        $query .= ' SERVICE <http://www.bigdata.com/rdf/search#search> { '
                                    . ' ?label bds:search "*'.$w.'*".  '
                                    . ' ?label bds:matchAllTerms "true". '
                                . ' } ';
                }
                //filter the language
                //$query .= " FILTER contains(lang(?label), '".$lang."') . "; 
                
                $query .= " } ";
            }
        }
        
        //if the words have a not
        if(!empty($not)) {
            $query .= ' FILTER (!contains(lcase(?label), lcase("'.$not.'") )) . '; 
        }
        
        //get the title
        $query .= $this->modelFunctions->filterLanguage("uri", RC::titleProp(), "title", $lang);
        
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
                        $val = $val[1];
                        
                        if ($dtype == "or") {
                            $or = true;
                            continue;
                        }
                        
                        if (($dtype == "not") || ($dtype == "and")) {
                            continue;
                        }
                        if ($dtype == $val) {
                            if ($or == true) {
                                $query .= " UNION ";
                                $or = false;
                            }
                            $query .= " { SELECT * WHERE { ?uri <".RC::get('drupalRdfType')."> <".$t['type']."> . } } ";
                        }
                    }
                }
                $query .= " } ";
            }
        }
        
        //selected years
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
        
        $query = $prefix.$select." Where { ".$conditions." ".$query." } GROUP BY ?title ?uri ?label ?pid ?hasTitleImage ?availableDate ?acdhType ?accessRestriction ORDER BY " . $order;
        if ($limit) {
            $query .= " LIMIT ".$limit." ";
            if ($page) {
                $query .= " OFFSET ".$page." ";
            }
        }
        
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
                        $val = $val[1];
                        
                        if ($dtype == "or") {
                            $or = true;
                            continue;
                        }
                        
                        if (($dtype == "not") || ($dtype == "and")) {
                            continue;
                        }
                        if ($dtype == $val) {
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
}
