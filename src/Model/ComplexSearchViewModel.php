<?php

namespace Drupal\oeaw\Model;

use acdhOeaw\util\RepoConfig as RC;
use Drupal\oeaw\Helper\ModelFunctions;

/**
 * Description of SearchViewModel
 *
 * @author nczirjak
 */
class ComplexSearchViewModel
{
    private $modelFunctions;
    private $fedora;
    
    public function __construct($fedora)
    {
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
                (GROUP_CONCAT(DISTINCT ?identifiers;separator=",") AS ?identifier) ';
        }
        
        $conditions = "";
        
        //the main part
        $query .= " ?uri <".RC::idProp()."> ?identifiers .  ";
        
        $not = "";
        //check the keywords
        if (isset($data["words"])) {
            $query .= " ?uri ("
                    . "<".RC::titleProp().">|<".RC::get('drupalHasAlternativeTitle').">|"
                    . "<https://vocabs.acdh.oeaw.ac.at/schema#hasAppliedMethod>|<https://vocabs.acdh.oeaw.ac.at/schema#hasAppliedMethodDescription>|"
                    . "<https://vocabs.acdh.oeaw.ac.at/schema#hasActor>|<https://vocabs.acdh.oeaw.ac.at/schema#hasSubject>|"
                    . "<https://vocabs.acdh.oeaw.ac.at/schema#hasTemporalCoverage>|<https://vocabs.acdh.oeaw.ac.at/schema#hasSubject>|"
                    . "<https://vocabs.acdh.oeaw.ac.at/schema#hasPublisher>|<https://vocabs.acdh.oeaw.ac.at/schema#hasSeriesInformation>|"
                    . "<".RC::get('drupalHasDescription').">|<".RC::get('drupalHasSpatialCoverage')."> "
                    . ""
                    . ") ?label . ";
            $wd = explode('+', $data["words"]);
            $not = "";
            foreach ($wd as $k => $w) {
                if (!empty($not) && ($not == $w)) {
                    continue;
                }
                
                if ($w == "not") {
                    if (isset($wd[$k+1])) {
                        $not = $wd[$k+1];
                    }
                    continue;
                }
                
                if ($w == "and") {
                    $query .="  UNION  ";
                    continue;
                } else if (!empty($w) ) {
                    $query .= " { ";
                
                    $query .= ' SERVICE <http://www.bigdata.com/rdf/search#search> { '
                                    . ' ?label bds:search "*'.$w.'*".  '
                                    . ' ?label bds:matchAllTerms "true". '
                                . ' } ';
                }
                //filter the language
                //$query .= " FILTER contains(lang(?label), '".$lang."') . ";
                if (!empty($w) ) {
                    $query .= " } ";
                }
            }
        }
        
        //if the words have a not
        if (!empty($not)) {
            $query .= ' FILTER (!contains(lcase(?label), lcase("'.$not.'") )) . ';
        }
        
        //get the title
        $query .= $this->modelFunctions->filterLanguage("uri", RC::titleProp(), "title", $lang);
        $query .= "  ?uri <".RC::get('drupalRdfType')."> ?rdfType . ";
        //check the rdf types from the query
        if (isset($data["type"])) {
            $td = explode('+', $data["type"]);
            
            $filterIn = array();
            $filterNot = array();
            $not = false;
            if (count($td) > 0) {
                if(count($td) == 1) {
                    $query .= " ?uri <".RC::get('drupalRdfType')."> <".RC::get('fedoraVocabsNamespace').$td[0]."> . ";
                }else {
                    foreach ($td as $dtype) {
                        
                        
                        if (($dtype == "not") ) {
                            $not = true;
                            continue;
                        }else if($not === true) {
                            $filterNot[] = "<".RC::get('fedoraVocabsNamespace').$dtype.">";
                            $not = false;
                        }else if($dtype == "and")  {
                            continue;
                        }else {
                            $filterIn[] = "<".RC::get('fedoraVocabsNamespace').$dtype.">";                            
                        }
                    }
                    if(count((array)$filterNot) > 0) {
                        foreach($filterNot as $fn) {
                            $query .= ' MINUS { ?uri <'.RC::get("drupalRdfType").'> '.$fn.' . } . ';
                        }
                    }
                    if(count((array)$filterIn) > 0) {
                        $query .= ' FILTER  ( ?rdfType IN ( ';
                        $query .= implode(",", $filterIn);
                        $query .= ' )) .';
                    }
                }
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
            $conditions .= " ?uri <".RC::get('drupalHasAvailableDate')."> ?date . ";
            if (\DateTime::createFromFormat('Y', $maxYear) !== false && \DateTime::createFromFormat('Y', $minYear) !== false) {
                $query .= "FILTER (  xsd:dateTime(?date) <= '".$maxYear."-12-31T00:00:000+01:00'^^xsd:dateTime &&  xsd:dateTime(?date) >= '".$minYear."-01-01T00:00:000+01:00'^^xsd:dateTime)  ";
            } else {
                //if we have a wrong date then we will select the actual date
                $min = date("Y");
                $query .= "FILTER ( (CONCAT(str(substr(?date, 0, 4)))) <= '".$min."' && (CONCAT(str(substr(?date, 0, 4)))) >= '".$min."')  ";
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
                        $conditions .= " ?uri <".RC::get('drupalHasAvailableDate')."> ?date . ";
                        $query .= "FILTER (str(?date) < '".$maxdate->format('Y-m-d')."' && str(?date) > '".$mindate->format('Y-m-d')."')  ";
                    }
                } else {
                    throw new \ErrorException(t("Empty").':'.t("Minimum").' '.t("or").' '.t("Maximum").' '.t("Date"));
                }
            }
        }
        
        $query .= $this->modelFunctions->filterLanguage("uri", RC::get('drupalHasDescription'), "descriptions", $lang, true);
        $query .= ' ?uri  <'.RC::get("drupalRdfType").'> ?acdhType . '
                   . 'FILTER regex(str(?acdhType),"vocabs.acdh","i") .  ';
        $query .= "
        OPTIONAL{ ?uri <".RC::get('drupalHasTitleImage')."> ?hasTitleImage .}                
        OPTIONAL{ ?uri <".RC::get('drupalHasAvailableDate')."> ?availableDate . }";
        $query .= " OPTIONAL {?uri <".RC::get('fedoraAccessRestrictionProp')."> ?accessRestriction . } ";
        $groupby = "";
        if($count === false) {
            $groupby = "GROUP BY ?title ?uri ?label ?pid ?hasTitleImage ?availableDate ?acdhType ?accessRestriction ORDER BY " . $order;
        }
        $query = $prefix.$select." Where { ".$conditions." ".$query." } ".$groupby;
        if ($limit) {
            $query .= " LIMIT ".$limit." ";
            if ($page) {
                $query .= " OFFSET ".$page." ";
            }
        }
        //error_log("fulltext BG: ");
        //error_log(print_r($query, true));
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
                (GROUP_CONCAT(DISTINCT ?identifiers;separator=",") AS ?identifier) ';
        }
        
        $conditions = "";
        $query .= " ?uri ?prop ?obj . \n";
        $query .= $this->modelFunctions->filterLanguage("uri", RC::titleProp(), "title", $lang);
        //$query .= " ?uri <".RC::titleProp()."> ?title . \n
        $query .= "?uri <".RC::idProp()."> ?identifiers .        
            OPTIONAL { ?uri <".RC::get('epicPidProp')."> ?pid .  } 
            FILTER( ?prop IN (<".RC::titleProp().">, <".RC::get('drupalHasDescription').">, <".RC::get('drupalHasContributor')."> )) .   ";
        
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
                    $query .= "FILTER (!contains(lcase(?obj), lcase('".$w."' ))) .  ";
                    $not = false;
                } else {
                    $query .= "FILTER (contains(lcase(?obj), lcase('".$w."' ))) .  ";
                }
            }
        }
        
        $query .= "  ?uri <".RC::get('drupalRdfType')."> ?rdfType . ";
        //check the rdf types from the query
        if (isset($data["type"])) {
            $td = explode('+', $data["type"]);
            
            $filterIn = array();
            $filterNot = array();
            $not = false;
            if (count($td) > 0) {
                if(count($td) == 1) {
                    $query .= " ?uri <".RC::get('drupalRdfType')."> <".RC::get('fedoraVocabsNamespace').$td[0]."> . ";
                }else {
                    foreach ($td as $dtype) {
                        
                        
                        if (($dtype == "not") ) {
                            $not = true;
                            continue;
                        }else if($not === true) {
                            $filterNot[] = "<".RC::get('fedoraVocabsNamespace').$dtype.">";
                            $not = false;
                        }else if($dtype == "and")  {
                            continue;
                        }else {
                            $filterIn[] = "<".RC::get('fedoraVocabsNamespace').$dtype.">";                            
                        }
                    }
                    if(count((array)$filterNot) > 0) {
                        foreach($filterNot as $fn) {
                            $query .= ' MINUS { ?uri <'.RC::get("drupalRdfType").'> '.$fn.' . } . ';
                        }
                    }
                    if(count((array)$filterIn) > 0) {
                        $query .= ' FILTER  ( ?rdfType IN ( ';
                        $query .= implode(",", $filterIn);
                        $query .= ' )) .';
                    }
                }
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
            $conditions .= " ?uri <".RC::get('drupalHasAvailableDate')."> ?date . ";
            if (\DateTime::createFromFormat('Y', $maxYear) !== false && \DateTime::createFromFormat('Y', $minYear) !== false) {
                $query .= "FILTER (  xsd:dateTime(?date) <= '".$maxYear."-12-31T00:00:000+01:00'^^xsd:dateTime &&  xsd:dateTime(?date) >= '".$minYear."-01-01T00:00:000+01:00'^^xsd:dateTime)  ";
            } else {
                //if we have a wrong date then we will select the actual date
                $min = date("Y");
                $query .= "FILTER ( (CONCAT(str(substr(?date, 0, 4)))) <= '".$min."' && (CONCAT(str(substr(?date, 0, 4)))) >= '".$min."')  ";
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
                        $conditions .= " ?uri <".RC::get('drupalHasAvailableDate')."> ?date . ";
                        $query .= "FILTER (str(?date) < '".$maxdate->format('Y-m-d')."' && str(?date) > '".$mindate->format('Y-m-d')."')  ";
                    }
                } else {
                    throw new \ErrorException(t("Empty").':'.t("Minimum").' '.t("or").' '.t("Maximum").' '.t("Date"));
                }
            }
        }
        
        $query .= $this->modelFunctions->filterLanguage("uri", RC::get('drupalHasDescription'), "descriptions", $lang, true);
        $query .= ' ?uri  <'.RC::get("drupalRdfType").'> ?acdhType . '
                   . 'FILTER regex(str(?acdhType),"vocabs.acdh","i") .  ';
        $query .= "
        OPTIONAL{ ?uri <".RC::get('drupalHasTitleImage')."> ?hasTitleImage .}                
        OPTIONAL{ ?uri <".RC::get('drupalHasAvailableDate')."> ?availableDate . }";
        $query .= " OPTIONAL {?uri <".RC::get('fedoraAccessRestrictionProp')."> ?accessRestriction . } ";
        $groupby = "";
        if($count === false) {
            $groupby = "GROUP BY ?title ?uri ?label ?pid ?hasTitleImage ?availableDate ?acdhType ?accessRestriction ORDER BY " . $order;
        }
        $query = $prefix.$select." Where { ".$conditions." ".$query." } ".$groupby;
        if ($limit) {
            $query .= " LIMIT ".$limit." ";
            if ($page) {
                $query .= " OFFSET ".$page." ";
            }
        }
        //error_log("fulltext: ");
        //error_log(print_r($query, true));
        return $query;
    }
}
