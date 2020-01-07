<?php

namespace Drupal\oeaw\Model;

use Drupal\oeaw\Model\OeawStorage;
use Drupal\oeaw\Helper\ModelFunctions as MF;
use acdhOeaw\util\RepoConfig as RC;

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
        $this->modelFunctions = new MF();
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
