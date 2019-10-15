<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Drupal\oeaw\Model;

use acdhOeaw\util\RepoConfig as RC;
use Drupal\oeaw\Helper\ModelFunctions;

/**
 * Description of RootViewModel
 *
 * @author nczirjak
 */
class RootViewModel {
    
    private $modelFunctions;
    private $fedora;
    
    public function __construct($fedora) {
        \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');
        $this->modelFunctions = new ModelFunctions();
        $this->fedora = $fedora;
    }
    
     /**
     * Get the root elements from fedora
     *
     * @param int $limit
     * @param int $offset
     * @param bool $count
     * @param string $order
     * @param string $lang
     * @return array
     * @throws \Exception
     * @throws \InvalidArgumentException
     */
    public function getRootFromDB(int $limit = 0, int $offset = 0, bool $count = false, string $order = "datedesc", string $lang = ""): array
    {
        //Let's process the order argument
        switch ($order) {
            case "titleasc":
                $order = "ASC( fn:lower-case(str(?title)))";
                break;
            case "titledesc":
                $order = "DESC( fn:lower-case(str(?title)))";
                break;
            case "dateasc":
                $order = "ASC(?avDate)";
                break;
            case "datedesc":
                $order = "DESC(?avDate)";
                break;
            default:
                $order = "DESC(?avDate)";
        }

        if ($offset < 0) {
            $offset = 0;
        }
        
        $getResult = array();
        $lang = strtolower($lang);
        
        try {
            $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> ';
            $select = "";
            $orderby = "";
            $groupby = "";
            $limitOffset = "";

            $where = " WHERE { ";
            
            $where .= $this->modelFunctions->filterLanguage("uri", RC::titleProp(), "title", $lang);
            $where .= "?uri <".RC::get('drupalRdfType')."> <".RC::get('drupalCollection')."> . ";
            
            $where .= "?uri <". RC::idProp()."> ?identifiers . ";
            $where .= "OPTIONAL { ?uri <".RC::get('epicPidProp')."> ?pid .  } ";
            $where .= $this->modelFunctions->filterLanguage("uri", RC::get('drupalHasDescription'), "descriptions", $lang, true);
            $where .= "OPTIONAL {?uri <".RC::get('drupalHasContributor')."> ?contributors . } ";
            $where .= "OPTIONAL {?uri <".RC::get('drupalHasAuthor')."> ?authors . } ";
            $where .= "OPTIONAL {?uri <".RC::get('drupalHasCreatedDate')."> ?creationdate . } ";
            $where .= "OPTIONAL {?uri <".RC::get('drupalHasAvailableDate')."> ?avDate . }";
            //. " BIND( (CONCAT(STR(substr(?avDate, 0, 10)))) as ?availableDate) . } ";
            $where .= "OPTIONAL {?uri <".RC::get('drupalHasCreationStartDate')."> ?hasCreationStartDate . } ";
            $where .= "OPTIONAL {?uri <".RC::get('drupalHasCreationEndDate')."> ?hasCreationEndDate . } ";
            $where .= "OPTIONAL {?uri <".RC::get('fedoraRelProp')."> ?isPartOf . } ";
            $where .= "OPTIONAL {?uri <".RC::get('fedoraAccessRestrictionProp')."> ?accessRestriction . } ";
            $where .= "OPTIONAL {?uri <".RC::get('drupalHasTitleImage')."> ?hasTitleImage . } ";

            if ($count == false) {
                $where .= "?uri <".RC::get('drupalRdfType')."> ?rdfType .  ";
                $where .= '?uri  <'.RC::get("drupalRdfType").'> ?acdhType . '
                   . 'FILTER regex(str(?acdhType),"vocabs.acdh","i") . ';
            }

            $where .=" 
                    filter not exists{ 
                        SELECT  * WHERE {
                            ?uri <https://vocabs.acdh.oeaw.ac.at/schema#isPartOf> ?y .
                        }
                    }
                    ";

            $where .= " } ";

            if ($count == false) {
                $select = 'SELECT ?uri ?title ?pid ';
                $select .= $this->modelFunctions->convertFieldDate("avDate", "availableDate", 0);
                $select .= ' ?isPartOf ?image ?hasTitleImage ?avDate';
                $select .= $this->modelFunctions->convertFieldDate("hasCreationStartDate", "hasCreationStartDate", 0);
                $select .= $this->modelFunctions->convertFieldDate("hasCreationEndDate", "hasCreationEndDate", 0);
                $select .= ' ?accessRestriction ?acdhType '
                                    . '(GROUP_CONCAT(DISTINCT ?rdfType;separator=",") AS ?rdfTypes) (GROUP_CONCAT(DISTINCT ?contributors;separator=",") AS ?contributor) '
                                    . ' (GROUP_CONCAT(DISTINCT ?authors;separator=",") AS ?author) (GROUP_CONCAT(DISTINCT ?descriptions;separator=",") AS ?description)'
                                    . '(GROUP_CONCAT(DISTINCT ?identifiers;separator=",") AS ?identifier)';

                $groupby = " GROUP BY ?uri ?title ?pid ?availableDate ?isPartOf ?image ?hasTitleImage ?hasCreationStartDate ?hasCreationEndDate ?accessRestriction ?acdhType ?avDate ";
                $orderby = " ORDER BY ".$order." ";
                $limitOffset = "LIMIT ".$limit." OFFSET ".$offset." ";
            } else {
                $select = " SELECT (COUNT(DISTINCT ?uri) as ?count) ";
                $orderby = ' order by ?uri ';
            }

            $query = $prefix.$select.$where.$groupby.$orderby.$limitOffset;
            $result = $this->fedora->runSparql($query);
            if (count($result) > 0) {
                $fields = $result->getFields();
                $getResult = $this->modelFunctions->createSparqlResult($result, $fields);
                return $getResult;
            } else {
                return $getResult;
            }
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        } catch (\InvalidArgumentException $ex) {
            throw new \InvalidArgumentException($ex->getMessage());
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            throw new \Exception($ex->getMessage());
        }
    }
    
}
