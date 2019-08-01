<?php

namespace Drupal\oeaw\Model;

use Drupal\oeaw\OeawFunctions;
use Drupal\oeaw\Helper\ModelFunctions as MF;
use Drupal\oeaw\ConfigConstants;
use Drupal\oeaw\Helper\HelperFunctions as HF;

use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\FedoraResource;

use acdhOeaw\fedora\metadataQuery\HasProperty;
use acdhOeaw\fedora\metadataQuery\HasTriple;
use acdhOeaw\fedora\metadataQuery\HasValue;
use acdhOeaw\fedora\metadataQuery\MatchesRegEx;

use acdhOeaw\fedora\metadataQuery\Query;
use acdhOeaw\fedora\metadataQuery\QueryParameter;
use acdhOeaw\fedora\metadataQuery\SimpleQuery;

use Symfony\Component\HttpFoundation\RedirectResponse;

use acdhOeaw\util\SparqlEndpoint;
use acdhOeaw\util\RepoConfig as RC;

class OeawStorage implements OeawStorageInterface
{
    private static $prefixes = 'PREFIX dct: <http://purl.org/dc/terms/> '
            . 'PREFIX ebucore: <http://www.ebu.ch/metadata/ontologies/ebucore/ebucore#> '
            . 'PREFIX premis: <http://www.loc.gov/premis/rdf/v1#> '
            . 'PREFIX acdh: <https://vocabs.acdh.oeaw.ac.at/schema#> '
            . 'PREFIX fedora: <http://fedora.info/definitions/v4/repository#> '
            . 'PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#> '
            . 'PREFIX owl: <http://www.w3.org/2002/07/owl#>'
            . 'PREFIX dc: <http://purl.org/dc/elements/1.1/>'
            . 'PREFIX foaf: <http://xmlns.com/foaf/0.1/>';
    
    private static $sparqlPref = array(
        'foafImage' => 'http://xmlns.com/foaf/0.1/Image',
        'rdfsDomain' => 'http://www.w3.org/2000/01/rdf-schema#domain'
    );
        
    
    private $oeawFunctions;
    private $modelFunctions;
    private $fedora;
    private static $instance;
    private $siteLang;
    
    /**
     * Set up the necessary properties, variables
     * @return type
     */
    public function __construct($cfg = null)
    {
        \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');
                
        $this->oeawFunctions = new OeawFunctions();
        $this->modelFunctions = new MF();
        $this->fedora = new Fedora();
        
        //blazegraph bugfix. Add missing namespace
        $blazeGraphNamespaces = \EasyRdf\RdfNamespace::namespaces();
        $localNamespaces = \Drupal\oeaw\ConfigConstants::$prefixesToBlazegraph;
                
        foreach ($localNamespaces as $key => $val) {
            if (!array_key_exists($val, $blazeGraphNamespaces)) {
                \EasyRdf\RdfNamespace::set($key, $val);
            }
        }
        
        if (!self::$instance) {
            self::$instance = $this;
            return self::$instance;
        } else {
            return self::$instance;
        }
        $this->getSiteLang();
    }
    
    /**
     * If the request is not coming from the API, we need to use the site language
     *
     */
    private function getSiteLang()
    {
        (isset($GLOBALS['language']) && !empty($GLOBALS['language'])) ? $this->siteLang = $GLOBALS['language'] : $this->siteLang = "en";
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
        (empty($lang)) ? $lang = strtolower($this->siteLang) : $lang = strtolower($lang);
        
        try {
            $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> ';
            $select = "";
            $orderby = "";
            $groupby = "";
            $query2 ="";
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
                $getResult = $this->oeawFunctions->createSparqlResult($result, $fields);
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

    /**
     * If we have hasPid as an URL then we need the acdh Identifier, to we can
     * work with the resource data
     *
     * @param string $pid
     * @return array
     * @throws Exception
     * @throws \InvalidArgumentException
     */
    public function getACDHIdByPid(string $pid): array
    {
        $getResult = array();
        
        try {
            $q = new Query();
            $q->addParameter((new HasValue(RC::get('epicPidProp'), $pid))->setSubVar('?uri'));
            $q->addParameter(new HasTriple('?uri', RC::get('fedoraIdProp'), '?id'));
            $query = $q->getQuery();
            $result = $this->fedora->runSparql($query);
            
            $fields = $result->getFields();
            $getResult = $this->oeawFunctions->createSparqlResult($result, $fields);
            return $getResult;
        } catch (\Exception $ex) {
            throw new Exception($ex->getMessage());
        } catch (\InvalidArgumentException $ex) {
            throw new \InvalidArgumentException($ex->getMessage());
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            throw new Exception($ex->getMessage());
        }
    }
    
    /**
     * Use the special identifiers to get the acdh uuid
     *
     * @param string $identifier
     * @return array
     * @throws Exception
     * @throws \InvalidArgumentException
     */
    public function getUUIDBySpecialIdentifier(string $identifier): array
    {
        $getResult = array();
        $identifier = (strpos($identifier, 'https://') !== false) ? str_replace('https://', "", $identifier) : str_replace('http://', "", $identifier);
        
        try {
            $select = " SELECT ?id WHERE { ";
            
            #$where = " ?uri <".RC::get('fedoraIdProp')."> <".$identifier."> . ";
            $where = "?uri <".RC::get('fedoraIdProp')."> ?sid . ";
            $where .= ' FILTER (regex(str(?sid),"'.$identifier.'","i")) .';
            $where .= "?uri <".RC::get('fedoraIdProp')."> ?id . ";
            $where .= ' FILTER (regex(str(?id),"id.acdh.oeaw.ac.at/uuid/","i")) .';
            $where .= " } ";
            
            $query = $select.$where;
            $result = $this->fedora->runSparql($query);
            
            $fields = $result->getFields();
            $getResult = $this->oeawFunctions->createSparqlResult($result, $fields);
            return $getResult;
        } catch (\Exception $ex) {
            throw new Exception($ex->getMessage());
        } catch (\InvalidArgumentException $ex) {
            throw new \InvalidArgumentException($ex->getMessage());
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            throw new Exception($ex->getMessage());
        }
    }
    
    public function getTypeByIdentifier(string $identifier, string $lang = ""): array
    {
        $getResult = array();
        (empty($lang)) ? $lang = strtolower($this->siteLang) : $lang = strtolower($lang);
        
        try {
            $select = " SELECT ?uri ?type WHERE { ";
            
            $where = " ?uri <".RC::get('fedoraIdProp')."> <".$identifier."> . ";
            $where .= "?uri <".RC::get('drupalRdfType')."> ?type . ";
            $where .= ' FILTER (regex(str(?type),"vocabs.acdh.oeaw.ac.at","i")) .';
            $where .= " } ";
            
            $query = $select.$where;
            
            $result = $this->fedora->runSparql($query);
            $fields = $result->getFields();
            $getResult = $this->oeawFunctions->createSparqlResult($result, $fields);
            
            return $getResult;
        } catch (\Exception $ex) {
            return array();
        } catch (\InvalidArgumentException $ex) {
            return array();
        }
    }
   
    /**
     * Get the reource title by its acdh:hasIdentifier property
     *
     * @param string $string
     * @param string $lang
     * @return array
     */
    public function getTitleByIdentifier(string $string, string $lang = ""): array
    {
        $getResult = array();
        (empty($lang)) ? $lang = strtolower($this->siteLang) : $lang = strtolower($lang);
        
        try {
            $select = " SELECT * WHERE { ";
            
            $where = " ?uri <".RC::get('fedoraIdProp')."> <".$string."> . ";
            $where .= $this->modelFunctions->filterLanguage("uri", RC::get('fedoraTitleProp'), "title", $lang, false);
            $where .= " } ";
            
            $query = $select.$where;
            $result = $this->fedora->runSparql($query);
            $fields = $result->getFields();
            $getResult = $this->oeawFunctions->createSparqlResult($result, $fields);
            return $getResult;
        } catch (\Exception $ex) {
            return array();
        } catch (\InvalidArgumentException $ex) {
            return array();
        }
    }
    
    /**
     * Get the actual resource last modified date for the cache
     *
     * @param string $uuid
     * @return string
     */
    public function getResourceModifyDateById(string $uuid): string
    {
        $getResult = array();
                
        try {
            $select = " SELECT ?modify";
                        
            $where  = " WHERE { ";
            $where .= " ?uri <".RC::get('fedoraIdProp')."> <".$uuid."> . ";
            $where .= " ?uri <".RC::get('oaiDateProp')."> ?modify . ";
            $where .= " } ";
            
            $query = $select.$where;
            $result = $this->fedora->runSparql($query);
            $fields = $result->getFields();
            $getResult = $this->oeawFunctions->createSparqlResult($result, $fields);
            $date = strtotime($getResult[0]['modify']);
            return date('Ymd_his', $date);
        } catch (\Exception $ex) {
            return "";
        } catch (\InvalidArgumentException $ex) {
            return "";
        }
    }
    
    /**
     * Create the property data for the expert view
     *
     * @param array $data
     * @param string $lang
     * @return array
     */
    public function getPropDataToExpertTable(array $data, string $lang = ""): array
    {
        $result = array();
        (empty($lang)) ? $lang = strtolower($this->siteLang) : $lang = strtolower($lang);
        if (count($data) > 0) {
            $where = "";
            $i = 0;
            
            foreach ($data as $key => $value) {
                $where .= " { ";
                $where .= "?uri <".RC::get('fedoraIdProp')."> <".$value."> . ";
                $where .= "?uri <".RC::get('fedoraIdProp')."> ?identifier . ";
                $where .= $this->modelFunctions->filterLanguage("uri", RC::get('drupalRdfsLabel'), "title", $lang, false);
                $where .= $this->modelFunctions->filterLanguage("uri", RC::get('drupalRdfsComment'), "comment", $lang, false);
                $where .= " } ";

                if ($i != count($data) - 1) {
                    $where .= " UNION ";
                }
                $i++;
            }
            $select = 'SELECT DISTINCT ?title ?uri ?comment ?identifier WHERE { ';
            $queryStr = $select.$where." } ";
            
            try {
                $q = new SimpleQuery($queryStr);
                $query = $q->getQuery();
                $res = $this->fedora->runSparql($query);
            
                $fields = $res->getFields();
                $result = $this->oeawFunctions->createSparqlResult($res, $fields);
            
                return $result;
            } catch (\Exception $ex) {
                return $result;
            } catch (\GuzzleHttp\Exception\ClientException $ex) {
                return $result;
            }
        }
        
        return $result;
    }
    
    /**
     * get the resource title by language
     *
     * @param string $uri
     * @param string $lang
     * @return array
     */
    public function getResourceTitle(string $uri, string $lang = ""): array
    {
        $getResult = array();
        (empty($lang)) ? $lang = strtolower($this->siteLang) : $lang = strtolower($lang);
        
        try {
            $q = new Query();
            $q->addParameter(new HasTriple($uri, RC::titleProp(), '?title'), true);
            $query = $q->getQuery();
            $result = $this->fedora->runSparql($query);
            
            $fields = $result->getFields();
            $getResult = $this->oeawFunctions->createSparqlResult($result, $fields);
            return $getResult;
        } catch (\Exception $ex) {
            return array();
        } catch (\InvalidArgumentException $ex) {
            return array();
        }
    }
    
    /**
     * Get all property for search
     *
     * @return array
     * @throws Exception
     * @throws \GuzzleHttp\Exception\ClientException
     */
    public function getAllPropertyForSearch():array
    {
        $getResult = array();
        
        try {
            $q = new Query();
            $q->addParameter(new HasTriple('?s', '?p', '?o'));
            $q->setDistinct(true);
            $q->setSelect(array('?p'));
        
            $query= $q->getQuery();
            $result = $this->fedora->runSparql($query);
            $fields = $result->getFields();
            $getResult = $this->oeawFunctions->createSparqlResult($result, $fields);

            return $getResult;
        } catch (\Exception $ex) {
            throw new Exception($ex->getMessage());
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            throw new \GuzzleHttp\Exception\ClientException($ex->getMessage());
        }
    }

    /**
     * Get value by the resource uri and property
     *
     * @param string $uri
     * @param string $property
     * @return array
     */
    public function getValueByUriProperty(string $uri, string $property): array
    {
        if (empty($uri) || empty($property)) {
            return drupal_set_message(
                t('Empty').' '.t('Values').' -->'.__FUNCTION__,
                'error'
                );
        }
        
        $getResult = array();
        try {
            $q = new Query();
            $q->addParameter((new HasTriple($uri, $property, '?value')));
            $q->setJoinClause('optional');
            $query = $q->getQuery();
            
            $result = $this->fedora->runSparql($query);
            
            $fields = $result->getFields();
            $getResult = $this->oeawFunctions->createSparqlResult($result, $fields);
            return $getResult;
        } catch (\Exception $ex) {
            return $getResult;
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return $getResult;
        } catch (\Symfony\Component\Routing\Exception\InvalidParameterException $ex) {
            return $getResult;
        }
    }

    /**
     * Get a value as string with resource uri and property
     *
     * @param string $uri
     * @param string $property
     * @return string
     */
    public function getPropertyValueByUri(string $uri, string $property): string
    {
        if (empty($uri) || empty($property)) {
            return drupal_set_message(
                t('Empty').' '.t('Values').' -->'.__FUNCTION__,
                'error'
            );
        }
        
        $getResult = array();

        try {
            $q = new Query();
            $q->addParameter((new HasValue(RC::get('fedoraIdProp'), $uri))->setSubVar('?uri'));
            $q->addParameter(new HasTriple('?uri', $property, '?value'));
             
            $query = $q->getQuery();

            $result = $this->fedora->runSparql($query);
            
            $fields = $result->getFields();
            $getResult = $this->oeawFunctions->createSparqlResult($result, $fields);

            return $getResult[0]["value"];
        } catch (\Exception $ex) {
            return $getResult;
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return $getResult;
        } catch (\Symfony\Component\Routing\Exception\InvalidParameterException $ex) {
            return $getResult;
        }
    }

    /**
     * Get all data by property and value
     *
     * @param string $property
     * @param string $value
     * @param int $limit
     * @param int $offset
     * @param bool $count
     * @param type $lang
     * @return array
     * @throws \Exception
     */
    public function getDataByProp(string $property, string $value, int $limit = 0, int $offset = 0, bool $count = false, $lang = ""): array
    {
        if (empty($value) || empty($property)) {
            return drupal_set_message(
                t('Empty').' '.t('Values').' -->'.__FUNCTION__,
                'error'
            );
        }
        (empty($lang)) ? $lang = strtolower($this->siteLang) : $lang = strtolower($lang);
        if ($offset < 0) {
            $offset = 0;
        }
       
        if (!filter_var($property, FILTER_VALIDATE_URL)) {
            $property = HF::createUriFromPrefix($property);
            if ($property === false) {
                return drupal_set_message(
                    t('Error').':'.__FUNCTION__,
                    'error'
                );
            }
        } elseif (filter_var($property, FILTER_VALIDATE_URL)) {
            $property = '<'. $property .'>';
        }
        
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $value = HF::createUriFromPrefix($value);
            if ($value === false) {
                return drupal_set_message(t('Error').':'.__FUNCTION__, 'error');
            }
        } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
            $value = '<'. $value .'>';
        }

        $getResult = array();

        try {
            $where = "?uri ".$property." ".$value." . ";
            
            if ($count == false) {
                $select  = 'SELECT  
                            ?uri ?title ?label ?creationdate ?isPartOf ?firstName ?lastName  
                            (CONCAT ( STR( DAY(?fdCreated)), "-", STR( MONTH(?fdCreated)), "-", STR( YEAR(?fdCreated))) as ?fdCreated) 
                            (GROUP_CONCAT(DISTINCT ?descriptions;separator=",") AS ?description) 
                            (GROUP_CONCAT(DISTINCT ?author;separator=",") AS ?authors) 
                            (GROUP_CONCAT(DISTINCT ?contributor;separator=",") AS ?contributors) 
                            (GROUP_CONCAT(DISTINCT ?rdfType;separator=",") AS ?rdfTypes) 
                        WHERE { ';
            
                $where .= $this->modelFunctions->filterLanguage("uri", RC::get('fedoraTitleProp'), "title", $lang, true);
                $where .= " OPTIONAL { ?uri <".RC::get('drupalHasAuthor')."> ?author . } ";
                $where .= $this->modelFunctions->filterLanguage("uri", RC::get('drupalHasDescription'), "descriptions", $lang, true);
                $where .= " OPTIONAL { ?uri <".RC::get('drupalRdfsLabel')."> ?label . } ";
                $where .= " OPTIONAL { ?uri <".RC::get('drupalHasContributor')."> ?contributor . } ";
                $where .= " OPTIONAL { ?uri <".RC::get('drupalHasCreatedDate')."> ?creationdate . } ";
                $where .= " OPTIONAL { ?uri <".RC::get('fedoraRelProp')."> ?isPartOf . } ";
                $where .= " OPTIONAL { ?uri <".RC::get('drupalRdfType')."> ?rdfType . } ";
                $where .= " OPTIONAL { ?uri <".RC::get('drupalHasFirstName')."> ?firstName . } ";
                $where .= " OPTIONAL { ?uri <".RC::get('drupalHasLastName')."> ?lastName . } ";
                $where .= " OPTIONAL { ?uri <http://fedora.info/definitions/v4/repository#created> ?fdCreated . } ";
                $where .= " OPTIONAL { ?uri <".RC::get('drupalHasCreatedDate')."> ?creationdate . } ";
                $where .= " } ";
                $groupby = "GROUP BY ?uri ?title ?label ?creationdate ?isPartOf ?firstName ?lastName ?fdCreated ";
                if ($limit == 0) {
                    $limit = 10;
                }
                $limit = "LIMIT $limit ";
                $offset = "OFFSET $offset ";
                if ($value == RC::get('drupalPerson')) {
                    $orderby = "ORDER BY ?firstName ";
                } else {
                    $orderby = "ORDER BY ?title ";
                }
                
                $query = $select.$where.$groupby.$orderby.$limit.$offset;
            } else {
                $select = "SELECT COUNT(?uri) as ?count) WHERE { ";
                $orderby = "ORDER BY ?uri ";
                $query = $select.$where.$groupby.$orderby;
            }
            

            $result = $this->fedora->runSparql($query);
            $fields = $result->getFields();
            $getResult = $this->oeawFunctions->createSparqlResult($result, $fields);

            return $getResult;
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            throw new \Exception($ex->getMessage());
        } catch (\Symfony\Component\Routing\Exception\InvalidParameterException $ex) {
            throw new \Exception($ex->getMessage());
        }
    }
    
    /**
     * Get the digital rescources to we can know which is needed a file upload
     *
     * @return array
     */
    public function getDigitalResources(): array
    {
        $getResult = array();
        
        try {
            $select = "SELECT  ?id ?collection  WHERE { ";
            
            $where = " ?class a owl:Class . ";
            $where .= " OPTIONAL {
                              { ";
            $where .= " {?class rdfs:subClassOf* <'.RC::get('drupalCollection').'>} ";
            $where .= " UNION ";
            $where .= " {?class rdfs:subClassOf* <'.RC::get('drupalDigitalCollection').'>} ";
            $where .= " UNION ";
            $where .= " {?class dct:identifier <'.RC::get('drupalCollection').'>} ";
            $where .= " {?class dct:identifier <'.RC::get('drupalDigitalCollection').'>} ";
            $where .= " } ";
            $where .= " VALUES ?collection {true}
                            }
                        } ";
            
            $query = self::$prefixes . $select.$where;
            
            $result = $this->fedora->runSparql($query);
            $fields = $result->getFields();
            $getResult = $this->oeawFunctions->createSparqlResult($result, $fields);

            return $getResult;
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
            $result = $this->oeawFunctions->createSparqlResult($res, $fields);
            return $result;
        } catch (\Exception $ex) {
            return array();
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return array();
        }
    }
    
    /**
     * Get the fedora url by the id or pid
     *
     * type: false = uri, true = pid
     *
     * @param string $id
     * @param bool $type
     * @return string
     */
    public function getFedoraUrlByIdentifierOrPid(string $id, bool $type = false): string
    {
        $result = "";
        
        $string = "select ?uri ?pid where { "
                . "?uri <".RC::get('fedoraIdProp')."> <".$id."> . "
                . "OPTIONAL {"
                . " ?uri <".RC::get('epicPidProp')."> ?pid ."
                . " } "
                . "}";
        
        try {
            $q = new SimpleQuery($string);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            
            $fields = $res->getFields();
            $data = $this->oeawFunctions->createSparqlResult($res, $fields);
            if (count($data) > 0) {
                if ($type === true && (isset($data[0]['pid']) && !empty($data[0]['pid']))) {
                    $result = $data[0]['pid'];
                } elseif (isset($data[0]['uri']) && !empty($data[0]['uri'])) {
                    $result = $data[0]['uri'];
                }
            }
            return $result;
        } catch (\Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return $result;
        }
    }
  
    
    /**
     * We using it for the NEW/EDIT FORMS
     *  Get the digital rescources Meta data and the cardinality data by ResourceUri
     *
     * @param string $classURI
     * @param string $lang
     * @return array
     */
    public function getClassMeta(string $classURI, string $lang = ""): array
    {
        if (empty($classURI)) {
            return drupal_set_message(
                t('Empty').' '.t('Values').' -->'.__FUNCTION__,
                'error'
            );
        }
        (empty($lang)) ? $lang = strtolower($this->siteLang) : $lang = strtolower($lang);
        
        $rdfsDomain = self::$sparqlPref["rdfsDomain"];
        
        $select = "
            SELECT  
                ?prop ?propID ?propTitle ?cardinality ?minCardinality ?maxCardinality ?range (GROUP_CONCAT(DISTINCT ?comments;separator=',') AS ?comment)
            WHERE 
            {
                {";
        
        //where the person id a subclassof
        $where = "  <".$classURI."> <".RC::get('fedoraTitleProp')."> ?classTitle . ";
        $where .= " <".$classURI."> (rdfs:subClassOf / ^<https://vocabs.acdh.oeaw.ac.at/schema#hasIdentifier>)* / rdfs:subClassOf ?class . ";
        
        $where .= " ?prop <".$rdfsDomain."> ?class . ";
        $where .= " ?prop <".RC::idProp()."> ?propID . ";
        $where .= $this->modelFunctions->filterLanguage("prop", RC::get('fedoraTitleProp'), "propTitle", $lang, false);
        $optionals = "";
        $optionals = "
            OPTIONAL{ 
                SELECT  * WHERE { ?prop <".RC::get('drupalOwlCardinality')."> ?cardinality .}
            }";
        $optionals .="
            OPTIONAL { 
                SELECT  * WHERE { ?prop <".RC::get('drupalOwlMinCardinality')."> ?minCardinality .}
            }";
        $optionals .="
            OPTIONAL {
                SELECT  * WHERE { ?prop <http://www.w3.org/2000/01/rdf-schema#range> ?range .}
            }";
        $optionals .="
            OPTIONAL {
                SELECT  * WHERE { ?prop <".RC::get('drupalOwlMaxCardinality')."> ?maxCardinality .}
            }";
        
        $optionals .= $this->modelFunctions->filterLanguage("prop", RC::get('drupalRdfsComment'), "comments", $lang, true);
        $where .= $optionals;
        
        $where .="
            } UNION { ";
        $where .=" <".$classURI."> <".RC::idProp()."> ?classID . ";
        $where .= " ?prop <".$rdfsDomain."> ?classID . ";
        $where .= " ?prop <".RC::idProp()."> ?propID .";
        $where .= $this->modelFunctions->filterLanguage("prop", RC::get('fedoraTitleProp'), "propTitle", $lang, false);
        $where .= $optionals;
        
        $where .= " } }";
        $groupby =" GROUP BY ?prop ?propID ?propTitle ?cardinality ?minCardinality ?maxCardinality ?range ";
        $orderby =" ORDER BY ?propID ";
        
        $query = $select.$where.$groupby.$orderby;
        try {
            $q = new SimpleQuery($query);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            
            $fields = $res->getFields();
            $result = $this->oeawFunctions->createSparqlResult($res, $fields);
            return $result;
        } catch (\Exception $ex) {
            return array();
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return array();
        }
    }
    
    /**
     * Get the image by the identifier
     *
     * @param string $string - image acdh:hasIdentifier value
     * @return string - the fedora url of the image
     */
    public function getImageByIdentifier(string $string): string
    {
        $return = "";
        if (empty($string)) {
            return drupal_set_message(
                t('Empty').' '.t('Values').' -->'.__FUNCTION__,
                'error'
            );
        }
       
        try {
            $q = new Query();
            $q->setSelect(array('?uri'));
            $q->addParameter((new HasValue(RC::idProp(), $string))->setSubVar('?uri'));
            $q->addParameter((new HasValue(RC::get('drupalRdfType'), RC::get('drupalImage')))->setSubVar('?uri'));
            $query = $q->getQuery();
            $result = $this->fedora->runSparql($query);
            foreach ($result as $r) {
                if ($r->uri) {
                    $return = $r->uri->getUri();
                }
            }
            return $return;
        } catch (\Exception $ex) {
            return "";
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return "";
        }
    }
    
    /**
     * Get the resource thumbnail image
     *
     * @param string $value -> the property value
     * @param string $property -> the property
     * @return string
     */
    public function getImage(string $value, string $property = null): string
    {
        if (empty($value)) {
            drupal_set_message(
                t('Empty').' '.t('Values').' -->'.__FUNCTION__,
                'error'
            );
            return "";
        }
        
        if ($property == null) {
            $property = RC::idProp();
        }
        $foafImage = self::$sparqlPref["foafImage"];
        $res = "";

        try {
            $q = new Query();
            $q->setSelect(array('?res'));
            $q->addParameter((new HasValue($property, $value)));
           
            $query = $q->getQuery();
            $result = $this->fedora->runSparql($query);

            foreach ($result as $r) {
                if ($r->res) {
                    $res = $r->res->getUri();
                }
            }
            return $res;
        } catch (\Exception $ex) {
            return "";
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return "";
        }
    }
    
    /**
     * Get the acdh:isMember values by resource URI for the Organisation view.
     *
     * @param string $uri
     * @param string $lang
     * @return array
     */
    public function getIsMembers(string $uri, string $lang = ""): array
    {
        if (empty($uri)) {
            return drupal_set_message(
                t('Empty').' '.t('Values').' -->'.__FUNCTION__,
                'error'
            );
        }
        (empty($lang)) ? $lang = strtolower($this->siteLang) : $lang = strtolower($lang);
        
        $result = array();
        $select = "";
        $where = "";
        $queryStr = "";
        
        $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> ';
        
        $select = 'SELECT ?uri ?title  ?childId ?childUUID ?externalId WHERE { ';
        $where = '<'.$uri.'> <'.RC::get("fedoraIdProp").'> ?id . ';
        $where .= '?uri <'.RC::get('drupalIsMember').'> ?id . ';
        //$where .= '?uri <'.RC::get('fedoraTitleProp').'> ?title . ';
        $where .= $this->modelFunctions->filterLanguage("uri", RC::get('fedoraTitleProp'), "title", $lang, false);
        $where .= ' OPTIONAL { '
                . ' ?uri  <'.RC::get("fedoraIdProp").'> ?childId . '
                . ' FILTER regex(str(?childId),"id.acdh.oeaw.ac.at","i") . '
                . ' FILTER (!regex(str(?childId),"id.acdh.oeaw.ac.at/uuid","i")) .'
                . ' } ';
        
        $where .= ' OPTIONAL { '
                . ' ?uri  <'.RC::get("fedoraIdProp").'> ?childUUID . '
                . ' FILTER regex(str(?childUUID),"id.acdh.oeaw.ac.at/uuid","i") . '
                . ' } ';
        
        $where .= ' OPTIONAL { '
                . ' ?uri  <'.RC::get("fedoraIdProp").'> ?externalId . '
                . ' FILTER (!regex(str(?externalId),"id.acdh.oeaw.ac.at","i")) .'
                . ' } ';
        
        $where .= ' } ';
        
        $groupBy = ' GROUP BY ?uri ?title ?childId ?childUUID ?externalId  ORDER BY ASC( fn:lower-case(?title)) ';
        
        $queryStr = $prefix.$select.$where.$groupBy;
        

        try {
            $q = new SimpleQuery($queryStr);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            
            $fields = $res->getFields();
            $result = $this->oeawFunctions->createSparqlResult($res, $fields);
            return $result;
        } catch (\Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return $result;
        }
    }
        
    /**
     * We are using this sparql if we want to get Special children data by the property
     * We have also a similar sparql which is the getSpecialDetailViewData, but there we
     * have some extra filtering, this sparql is the clone of the get ChildrenViewData
     * just with a property
     *
     * @param string $uri
     * @param string $limit
     * @param string $offset
     * @param bool $count
     * @param array $property -> the property from the config.ini what is the "Parent"
     * @param string $lang
     * @return array
     */
    public function getChildResourcesByProperty(string $uri, string $limit, string $offset, bool $count, array $property, string $lang = ""): array
    {
        if (empty($uri)) {
            return drupal_set_message(
                t('Empty').' '.t('Values').' -->'.__FUNCTION__,
                'error'
            );
        }
        (empty($lang)) ? $lang = strtolower($this->siteLang) : $lang = strtolower($lang);
        if ($offset < 0) {
            $offset = 0;
        }
        $result = array();
        $select = "";
        $where = "";
        $limitStr = "";
        $queryStr = "";
        
        $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> ';
        if ($count == false) {
            $select = 'SELECT ?uri ?title (GROUP_CONCAT(DISTINCT ?type;separator=",") AS ?types) (GROUP_CONCAT(DISTINCT ?descriptions;separator=",") AS ?description)  ';
            $limitStr = ' LIMIT '.$limit.'
            OFFSET '.$offset.' ';
        } else {
            $select = 'SELECT (COUNT(?uri) as ?count) ';
        }
        
        $where = '
            WHERE {
                <'.$uri.'>  <'.RC::get("fedoraIdProp").'> ?id  . ';
        foreach ($property as $p) {
            $where .= ' ?uri <'.$p.'> ?id . ';
        }
        
        $where .= $this->modelFunctions->filterLanguage("uri", RC::get('fedoraTitleProp'), "title", $lang, true);
        $where .= $this->modelFunctions->filterLanguage("uri", RC::get('drupalHasDescription'), "descriptions", $lang, true);
       
        $where .= '?uri  <'.RC::get("drupalRdfType").'> ?type . '
                . 'FILTER regex(str(?type),"vocabs.acdh","i") . '
                . '} '
                . 'GROUP BY ?uri ?title ORDER BY ASC( fn:lower-case(?title)) ';
        
        $queryStr = $select.$where.$limitStr;
        
        try {
            $q = new SimpleQuery($queryStr);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            
            $fields = $res->getFields();
            $result = $this->oeawFunctions->createSparqlResult($res, $fields);
            return $result;
        } catch (\Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return $result;
        }
    }
    
    /**
     * Get the necessary data for the children view
     *
     * @param array $ids
     * @param string $limit
     * @param string $offset
     * @param bool $count
     * @param string $lang
     * @return array
     */
    public function getChildrenViewData(array $ids, string $limit, string $offset, bool $count = false, string $lang = "", string $order = "asc"): array
    {
        if (count($ids) < 0) {
            return array();
        }
        if ($offset < 0) {
            $offset = 0;
        }
        (empty($lang)) ? $lang = strtolower($this->siteLang) : $lang = strtolower($lang);
        $result = array();
        $select = "";
        $where = "";
        $limitStr = "";
        $queryStr = "";
        $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> ';
        if ($count == false) {
            $select = 'SELECT ?uri ?title ?pid ?accessRestriction '
                    . '(GROUP_CONCAT(DISTINCT ?descriptions;separator=",") AS ?description) (GROUP_CONCAT(DISTINCT ?type;separator=",") AS ?types) (GROUP_CONCAT(DISTINCT ?identifiers;separator=",") AS ?identifier)  ';
            $limitStr = ' LIMIT '.$limit.'
            OFFSET '.$offset.' ';
        } else {
            $select = 'SELECT (COUNT(?uri) as ?count) ';
        }
        
        $where = '
            WHERE { ';
        $where .= $this->modelFunctions->filterLanguage("uri", RC::get('fedoraTitleProp'), "title", $lang, false);
        $where .= $this->modelFunctions->filterLanguage("uri", RC::get('drupalHasDescription'), "descriptions", $lang, true);
        $where .= '
                OPTIONAL { ?uri <'.RC::get("epicPidProp").'> ?pid .} ';
        $where .= ' OPTIONAL { ?uri <'.RC::get("fedoraAccessRestrictionProp").'> ?accessRestriction . } ';
        $where .= ' ?uri  <'.RC::get("drupalRdfType").'> ?type .
                ?uri <'.RC::idProp().'> ?identifiers . 
                FILTER regex(str(?type),"vocabs.acdh","i") .
                ?uri <'.RC::get("fedoraRelProp").'>  ?isPartOf .
                FILTER ( 
            ';
        
        $num = count($ids);
        
        for ($i = 0; $i <= $num -1 ; $i++) {
            $where .= '?isPartOf =  <'.$ids[$i].'> ';
            if ($i !== ($num - 1)) {
                $where .= ' || ';
            }
        }
        $where .= ')';
        $groupBy = ' }  GROUP BY ?uri ?title ?pid ?accessRestriction ';
        $groupBy .= ' ORDER BY '.$order.' ( fn:lower-case(?title)) ';
        
        $queryStr = $select.$where.$groupBy.$limitStr;
        
        try {
            $q = new SimpleQuery($queryStr);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            
            $fields = $res->getFields();
            $result = $this->oeawFunctions->createSparqlResult($res, $fields);
            return $result;
        } catch (\Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return $result;
        }
    }
    
    /**
     * Get the HasMetadata Inverse property by Resource Identifier
     *
     * @param string $id
     * @return array
     */
    public function getMetaInverseData(string $uri): array
    {
        $result = array();
        
        $where = "";
        $where .= '<'.$uri.'> <'.RC::get("fedoraIdProp").'> ?id .'
                . '?uri <'.RC::get("drupalHasMetadata").'> ?id .'
                . '?invUri <'.RC::get("fedoraIdProp").'> <'.RC::get("drupalHasMetadata").'> .'
                . '?invUri <'.RC::get("drupalOwlInverseOf").'> ?inverse .';
        
        $select = '
            select ?uri ?inverse where { ';
        $end = ' } ';
        
        $string = $select.$where.$end;
        
        try {
            $q = new SimpleQuery($string);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            
            $fields = $res->getFields();
            $res = $this->oeawFunctions->createSparqlResult($res, $fields);
            
            $i = 0;
            foreach ($res as $r) {
                foreach ($r as $k => $v) {
                    $result[$i][$k] = $v;
                    if ($k == "uri") {
                        $title = $this->oeawFunctions->getTitleByUri($v);
                        $result[$i]["title"] = $title;
                        $result[$i]["insideUri"] = $this->oeawFunctions->detailViewUrlDecodeEncode($v, 1);
                    }
                    if ($k == "prop") {
                        $shortcut = $this->oeawFunctions->createPrefixesFromString($v);
                        $result[$i]["shortcut"] = $shortcut;
                    }
                }
                $i++;
            }
            return $result;
        } catch (\Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return $result;
        }
    }
    
    /**
     * Create the Inverse table data by URL
     *
     * @param string $url
     * @return array
     */
    public function getInverseViewDataByURL(string $url): array
    {
        $result = array();
        
        $where = ' <'.$url.'> <'.RC::get("fedoraIdProp").'> ?obj . ';
        $where .=  ' ?uri ?prop ?obj . ';
        $where .= ' MINUS { ?uri <'.RC::get("fedoraIdProp").'> ?obj  } . ';
        $where .= ' MINUS { ?uri <'.RC::get("fedoraRelProp").'> ?obj  } . ';
        $where .= ' ?propUri <'.RC::get("fedoraIdProp").'> ?prop . ';
        $where .= ' ?propUri <'.RC::get("drupalOwlInverseOf").'> ?inverse . ';
        $where .= ' OPTIONAL { '
                . ' ?uri  <'.RC::get("fedoraIdProp").'> ?childId . '
                . ' FILTER regex(str(?childId),"id.acdh.oeaw.ac.at","i") . '
                . ' FILTER (!regex(str(?childId),"id.acdh.oeaw.ac.at/uuid","i")) .'
                . ' } ';

        $where .= ' OPTIONAL { '
                . ' ?uri  <'.RC::get("fedoraIdProp").'> ?childUUID . '
                . ' FILTER regex(str(?childUUID),"id.acdh.oeaw.ac.at/uuid","i") . '
                . ' } ';

        $where .= ' OPTIONAL { '
                . ' ?uri  <'.RC::get("fedoraIdProp").'> ?externalId . '
                . ' FILTER (!regex(str(?externalId),"id.acdh.oeaw.ac.at","i")) .'
                . ' } ';
                
        $select = '
            select DISTINCT ?uri ?prop ?obj ?inverse ?childId ?childUUID ?externalId  where { ';
        $end = ' } ';
        
        $groupBy = ' GROUP BY ?uri ?prop ?obj ?inverse ?childId ?childUUID ?externalId  ORDER BY ASC( fn:lower-case(?uri)) ';
        
        $string = $select.$where.$end.$groupBy;
        
        try {
            $q = new SimpleQuery($string);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            
            $fields = $res->getFields();
            $res = $this->oeawFunctions->createSparqlResult($res, $fields);
            
            $i = 0;
            foreach ($res as $r) {
                foreach ($r as $k => $v) {
                    $result[$i][$k] = $v;
                    if ($k == "uri") {
                        $title = $this->oeawFunctions->getTitleByUri($v);
                        $result[$i]["title"] = $title;
                        $result[$i]["insideUri"] = $this->oeawFunctions->detailViewUrlDecodeEncode($v, 1);
                    }
                    if ($k == "prop") {
                        $shortcut = $this->oeawFunctions->createPrefixesFromString($v);
                        $result[$i]["shortcut"] = $shortcut;
                    }
                }
                $i++;
            }
            return $result;
        } catch (\Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return $result;
        }
    }
    
    
    /**
     * Create the data for the InverseViews by the Resource Identifier
     *
     * @param array $data
     * @return array
     */
    public function getInverseViewDataByIdentifier(array $data): array
    {
        $result = array();
        $num = count($data);
        $string = "";
        $where = "?uri ?prop ?obj . ";
        
        for ($i = 0; $i <= $num -1 ; $i++) {
            $where .= '{
                select * where 
                {
                    ?uri ?prop <'.$data[$i].'> 
                }   
            }
            ';
            if ($i !== ($num - 1)) {
                $where .= 'UNION';
            }
        }
        
        $where .= 'MINUS { ?uri <'.RC::get("fedoraIdProp").'> ?obj . }';
        $select = '
            select DISTINCT ?uri ?prop ?obj where { ';
        $end = ' } ';
        
        $string = $select.$where.$end;
        
        try {
            $q = new SimpleQuery($string);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            
            $fields = $res->getFields();
            $res = $this->oeawFunctions->createSparqlResult($res, $fields);
            
            $i = 0;
            foreach ($res as $r) {
                foreach ($r as $k => $v) {
                    $result[$i][$k] = $v;
                    if ($k == "uri") {
                        $title = $this->oeawFunctions->getTitleByUri($v);
                        $result[$i]["title"] = $title;
                        $result[$i]["insideUri"] = $this->oeawFunctions->detailViewUrlDecodeEncode($v, 1);
                    }
                    if ($k == "prop") {
                        $shortcut = $this->oeawFunctions->createPrefixesFromString($v);
                        $result[$i]["shortcut"] = $shortcut;
                    }
                }
                $i++;
            }
            return $result;
        } catch (\Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return $result;
        }
    }
    
    
    /**
     * Run users sparql from the resource views
     * @param string $string
     * @param bool $multilang
     * @return array
     */
    public function runUserSparql(string $string, bool $multilang = false): array
    {
        $result = array();
        
        try {
            $q = new SimpleQuery($string);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            $fields = $res->getFields();
            return $result = $this->oeawFunctions->createSparqlResult($res, $fields, $multilang);
        } catch (\Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return $result;
        }
    }
        
    
    /**
     * Get the MIME infos
     *
     * @return array
     */
    public function getMimeTypes(): array
    {
        $getResult = array();
        
        try {
            $q = new Query();
            $q->addParameter(new HasTriple('?uri', RC::get('fedoraHasServiceProp'), '?dissId'));
            $q->addParameter(new HasTriple('?dissuri', RC::idProp(), '?dissId'));
            $q->addParameter(new HasTriple('?dissuri', RC::get('drupalProvidesMime'), '?mime'));
            
            $q->setSelect(array('?mime', '(COUNT(?mime) as ?mimeCount)'));
            $q->setOrderBy(array('?mime'));
            $q->setGroupBy(array('?mime'));
            
            $query = $q->getQuery();

            $result = $this->fedora->runSparql($query);
            $fields = $result->getFields();
            $getResult = $this->oeawFunctions->createSparqlResult($result, $fields);
            
            return $getResult;
        } catch (\Exception $ex) {
            return array();
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return array();
        }
    }
    
    /**
     * Function Return the ACDH vocabs Namespace Types with a count
     *
     * @param bool $count -> we want only count or not
     * @param bool $searchBox -> the complex searchbox has to skip some values
     * @return array
     */
    public function getACDHTypes(bool $count = false, bool $searchBox = false) :array
    {
        $getResult = array();
        
        try {
            if ($count == true) {
                $select = "SELECT  ?type (COUNT(?type) as ?typeCount) ";
            } else {
                $select = "SELECT  DISTINCT ?type ";
            }
            
            $filter = "FILTER (regex(str(?type), '".RC::vocabsNmsp()."', 'i')) .";
            if ($searchBox == true) {
                $filter .= "FILTER (!regex(str(?type), '".RC::get('drupalImage')."', 'i')) ."
                        . "FILTER (!regex(str(?type), '".RC::get('fedoraServiceClass')."', 'i')) .";
            }
            
            $queryStr = "
                WHERE {
                    ?uri <".RC::get('drupalRdfType')."> ?type .
                    $filter
                }
                GROUP BY ?type
                ORDER BY ?type
                ";
            
            $queryStr = $select.$queryStr;
            $q = new SimpleQuery($queryStr);
            $query = $q->getQuery();
            $result = $this->fedora->runSparql($query);
            $fields = $result->getFields();
            $getResult = $this->oeawFunctions->createSparqlResult($result, $fields);
            
            return $getResult;
        } catch (\Exception $ex) {
            return array();
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return array();
        }
    }
    
    /**
     * Generate the data for the left side complexSearch Year searching function
     *
     * @return array
     */
    public function getDateForSearch(): array
    {
        $result = array();
            
        $queryStr = 'SELECT ?year (COUNT(?year) as ?yearCount) WHERE { '
                . ' ?uri <'.RC::get('fedoraAvailableDateProp').'> ?date . '
                . ' values ?duration { "P0DT1H0M0.000S"^^xsd:duration } . '
                . ' BIND( (?date + ?duration) as ?end) . '
                . ' BIND( (CONCAT(STR(substr(?end, 0, 4)))) as ?year) . '
                . ' } '
                . ' GROUP BY ?year '
                . ' ORDER BY DESC(?year) ';
        
        try {
            $q = new SimpleQuery($queryStr);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);

            $fields = $res->getFields();
            $result = $this->oeawFunctions->createSparqlResult($res, $fields);

            return $result;
        } catch (\Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return $result;
        }
        return $result;
    }
    
    /**
     * Get the actual classes for the SideBar block
     *
     * @return array
     * @throws \ErrorException
     */
    public function getClassesForSideBar():array
    {
        $getResult = array();
        
        try {
            $q = new Query();
            $q->addParameter(new HasTriple('?uri', RC::get("drupalRdfType"), '?type'));
            $q->setSelect(array('?type', '(COUNT(?type) as ?typeCount)'));
            $q->setOrderBy(array('?uri'));
            $q->setGroupBy(array('?type'));
            $query = $q->getQuery();
            
            $result = $this->fedora->runSparql($query);
            $fields = $result->getFields();
            $getResult = $this->oeawFunctions->createSparqlResult($result, $fields);

            return $getResult;
        } catch (\Exception $ex) {
            throw new \ErrorException($ex->getMessage());
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            throw new \ErrorException($ex->getMessage());
        }
    }
    
    /**
     * This func gets the parent title from the DB
     *
     * @param string $id
     * @param string $lang
     * @return array
     */
    public function getParentTitle(string $id, string $lang = ""): array
    {
        $result = array();
        (empty($lang)) ? $lang = strtolower($this->siteLang) : $lang = strtolower($lang);
        if ($id) {
            $where = "";
            $where .= " WHERE { ";
            $where .= "?uri <".RC::get('fedoraIdProp')."> <".$id."> . ";
            $where .= $this->modelFunctions->filterLanguage("uri", RC::get('fedoraTitleProp'), "title", $lang, false);
            //$where .= "?uri <".RC::titleProp()."> ?title . ";
            $where .= " } ";
            $select = 'SELECT ?title   ';
            $queryStr = $select.$where;
            
            try {
                $q = new SimpleQuery($queryStr);
                $query = $q->getQuery();
                $res = $this->fedora->runSparql($query);
            
                $fields = $res->getFields();
                $result = $this->oeawFunctions->createSparqlResult($res, $fields);
             
                return $result;
            } catch (\Exception $ex) {
                return $result;
            } catch (\GuzzleHttp\Exception\ClientException $ex) {
                return $result;
            }
        }
        
        return $result;
    }
    
    /**
     * Get the titles for the detail view property values
     *
     * @param array $data
     * @param bool $dissemination
     * @param string $lang
     * @return array
     */
    public function getTitleByIdentifierArray(array $data, bool $dissemination = false, string $lang = ""): array
    {
        $result = array();
        if (count($data) > 0) {
            (empty($lang)) ? $lang = strtolower($this->siteLang) : $lang = strtolower($lang);
            $where = "";
            
            $where .= " { ";
            $where .= "?uri <".RC::get('fedoraIdProp')."> ?id . ";
            $where .= "FILTER (?id IN ( ";
            for ($x = 0; $x <= count($data) - 1; $x++) {
                $where .= "<".$data[$x].">";
                if ($x != count($data) - 1) {
                    $where .=",";
                }
            }
            $where .= " ) ) . ";
            $where .= $this->modelFunctions->filterLanguage("uri", RC::get('fedoraTitleProp'), "title", $lang, false);
            $where .=" OPTIONAL {"
                    . " ?uri <".RC::get('fedoraIdProp')."> ?identifier . "
                    . " FILTER (regex(str(?identifier),'id.acdh.oeaw.ac.at/','i')) . "
                    . " FILTER (!regex(str(?identifier),'.at/uuid/','i')) . "
                    . "}"
                    . "OPTIONAL { "
                    . " ?uri <".RC::get('fedoraIdProp')."> ?uuid .  "
                    . " FILTER (regex(str(?uuid),'id.acdh.oeaw.ac.at/uuid/','i')) . "
                    . " } "
                    . " OPTIONAL {"
                    . " ?uri <".RC::get('fedoraIdProp')."> ?vocabs . "
                    . " FILTER (regex(str(?vocabs),'vocabs.acdh.oeaw.ac.at/','i')) . "
                    . " } "
                    . " OPTIONAL { "
                    . " ?uri <".RC::get('epicPidProp')."> ?pid . "
                    . " } ";

            if ($dissemination == true) {
                $where .= "OPTIONAL {?uri <".RC::get('fedoraServiceRetFormatProp')."> ?returnType . } ";
                $where .= $this->modelFunctions->filterLanguage("uri", RC::get('drupalHasDescription'), "description", $lang, true);
                //$where .= "OPTIONAL {?uri <".RC::get('drupalHasDescription')."> ?description . } ";
                $where .= "FILTER (!regex(str(?identifier),'.at/uuid/','i')) .";
            }
            
            $where .= " } ";
            
            $select = "";
            if ($dissemination == true) {
                $select = 'SELECT DISTINCT ?title ?identifier ?uri ?uuid ?pid ?vocabs ?returnType ?description WHERE { ';
            } else {
                $select = 'SELECT DISTINCT ?title ?identifier ?uri ?uuid ?pid ?vocabs WHERE { ';
            }
            
            $queryStr = $select.$where." } ORDER BY ?title";
            try {
                $q = new SimpleQuery($queryStr);
                $query = $q->getQuery();
                $res = $this->fedora->runSparql($query);
                $fields = $res->getFields();
                $result = $this->oeawFunctions->createSparqlResult($res, $fields);
                return $result;
            } catch (\Exception $ex) {
                return $result;
            } catch (\GuzzleHttp\Exception\ClientException $ex) {
                return $result;
            }
        }
        
        return $result;
    }
    
    /**
     * Get title and some basic info about the resource by identifier
     *
     * @param string $data
     * @param bool $dissemination
     * @param string $lang
     * @return array
     */
    public function getTitleAndBasicInfoByIdentifier(string $data, bool $dissemination = false, string $lang = ""): array
    {
        $result = array();
        (empty($lang)) ? $lang = strtolower($this->siteLang) : $lang = strtolower($lang);
        $where = "";
        $select = "";

        $where .= " { ";
        $where .= "?uri <".RC::get('fedoraIdProp')."> <".$data."> . ";
        $where .= $this->modelFunctions->filterLanguage("uri", RC::get('fedoraTitleProp'), "title", $lang, false);
        $where .=" OPTIONAL {"
                . " ?uri <".RC::get('fedoraIdProp')."> ?identifier . "
                . " FILTER (regex(str(?identifier),'id.acdh.oeaw.ac.at/','i')) . "
                . " FILTER (!regex(str(?identifier),'.at/uuid/','i')) . "
                . "}"
                . "OPTIONAL { "
                . " ?uri <".RC::get('fedoraIdProp')."> ?uuid .  "
                . " FILTER (regex(str(?uuid),'id.acdh.oeaw.ac.at/uuid/','i')) . "
                . " } "
                . " OPTIONAL {"
                . " ?uri <".RC::get('fedoraIdProp')."> ?vocabs . "
                . " FILTER (regex(str(?vocabs),'vocabs.acdh.oeaw.ac.at/','i')) . "
                . " } "
                . " OPTIONAL { "
                . " ?uri <".RC::get('epicPidProp')."> ?pid . "
                . " } ";

        if ($dissemination == true) {
            $where .= "OPTIONAL {?uri <".RC::get('fedoraServiceRetFormatProp')."> ?returnType . } ";
            $where .= $this->modelFunctions->filterLanguage("uri", RC::get('drupalHasDescription'), "description", $lang, true);
            $where .= "FILTER (!regex(str(?identifier),'.at/uuid/','i')) .";
        }

        $where .= " } ";
                
        if ($dissemination == true) {
            $select = 'SELECT DISTINCT ?title ?identifier ?uri ?uuid ?pid ?vocabs ?returnType ?description WHERE { ';
        } else {
            $select = 'SELECT DISTINCT ?title ?identifier ?uri ?uuid ?pid ?vocabs WHERE { ';
        }

        $queryStr = $select.$where." } ORDER BY ?title";

        try {
            $q = new SimpleQuery($queryStr);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            $fields = $res->getFields();
            $result = $this->oeawFunctions->createSparqlResult($res, $fields);
            return $result;
        } catch (\Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return $result;
        }
        
        
        return $result;
    }
    
    /**
     * Get title and some basic info about the resources by identifier
     *
     * @param array $data
     * @param bool $dissemination
     * @param string $lang
     * @return array
     */
    public function getTitleAndBasicInfoByIdentifierArray(array $data, bool $dissemination = false, string $lang = ""): array
    {
        $result = array();
        (empty($lang)) ? $lang = strtolower($this->siteLang) : $lang = strtolower($lang);
        $where = "";
        $select = "";
        
        if (count($data) == 0) {
            return array();
        }
        $where .= " { ";
        $where .= "?uri <".RC::get('fedoraIdProp')."> ?identifier . ";
        $where .= "FILTER (?identifier IN ( ";
        $lastId = end($data);
        foreach ($data as $d) {
            $where .= "<".$d.">";
            if ($d != $lastId) {
                $where .=",";
            }
        }
        $where .= " ) ) . ";
        $where .= $this->modelFunctions->filterLanguage("uri", RC::get('fedoraTitleProp'), "title", $lang, false);
        $where .=" OPTIONAL {"
                . " ?uri <".RC::get('fedoraIdProp')."> ?identifier . "
                . " FILTER (regex(str(?identifier),'id.acdh.oeaw.ac.at/','i')) . "
                . " FILTER (!regex(str(?identifier),'.at/uuid/','i')) . "
                . "}"
                . "OPTIONAL { "
                . " ?uri <".RC::get('fedoraIdProp')."> ?uuid .  "
                . " FILTER (regex(str(?uuid),'id.acdh.oeaw.ac.at/uuid/','i')) . "
                . " } "
                . " OPTIONAL {"
                . " ?uri <".RC::get('fedoraIdProp')."> ?vocabs . "
                . " FILTER (regex(str(?vocabs),'vocabs.acdh.oeaw.ac.at/','i')) . "
                . " } "
                . " OPTIONAL { "
                . " ?uri <".RC::get('epicPidProp')."> ?pid . "
                . " } ";

        if ($dissemination == true) {
            $where .= "OPTIONAL {?uri <".RC::get('fedoraServiceRetFormatProp')."> ?returnType . } ";
            $where .= $this->modelFunctions->filterLanguage("uri", RC::get('drupalHasDescription'), "description", $lang, true);
            $where .= "FILTER (!regex(str(?identifier),'.at/uuid/','i')) .";
        }

        $where .= " } ";
                
        if ($dissemination == true) {
            $select = 'SELECT DISTINCT ?title ?identifier ?uri ?uuid ?pid ?vocabs ?returnType ?description WHERE { ';
        } else {
            $select = 'SELECT DISTINCT ?title ?identifier ?uri ?uuid ?pid ?vocabs WHERE { ';
        }

        $queryStr = $select.$where." } ORDER BY ?title";
       
        try {
            $q = new SimpleQuery($queryStr);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            $fields = $res->getFields();
            $result = $this->oeawFunctions->createSparqlResult($res, $fields);
            return $result;
        } catch (\Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return $result;
        }
        
        
        return $result;
    }
    
    /**
     * Create the Sparql Query for the special ACDH rdf:type "children views"
     *
     * @param string $uri
     * @param string $limit
     * @param string $offset
     * @param bool $count
     * @param array $property
     * @param string $lang
     * @return array
     */
    public function getSpecialDetailViewData(string $uri, string $limit, string $offset, bool $count = false, array $property, string $lang = "", string $orderby = 'asc'): array
    {
        if ($offset < 0) {
            $offset = 0;
        }
        (empty($lang)) ? $lang = strtolower($this->siteLang) : $lang = strtolower($lang);
        $result = array();
        $select = "";
        $where = "";
        $limitStr = "";
        $queryStr = "";
        $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> ';
        if ($count == false) {
            $select = 'SELECT ?uri ?title ?pid ?accessRestriction '
                    . '(GROUP_CONCAT(DISTINCT ?identifiers;separator=",") AS ?identifier) (GROUP_CONCAT(DISTINCT ?descriptions;separator=",") AS ?description) (GROUP_CONCAT(DISTINCT ?type;separator=",") AS ?types) ';
            $limitStr = ' LIMIT '.$limit.'
            OFFSET '.$offset.' ';
        } else {
            $select = 'SELECT (COUNT(?uri) as ?count) ';
        }
        
        $where = '
            WHERE {
                ?mainUri <'.RC::get("fedoraIdProp").'> <'.$uri.'> . ';
        $where .= "OPTIONAL {?uri <".RC::get('fedoraAccessRestrictionProp')."> ?accessRestriction . } ";
        $where .= '?mainUri <'.RC::get("fedoraIdProp").'> ?id . '
                . 'OPTIONAL { ?mainUri <'.RC::get("epicPidProp").'> ?pid . } . '
                . '?uri ?prop ?id . '
                . 'FILTER( ?prop IN ( ';
        for ($x = 0; $x < count($property); $x++) {
            $where .='<'.$property[$x].'>';
            if ($x +1 < count($property)) {
                $where .= ', ';
            }
        }
        
        $where .= ' )) . ';
        $where .= $this->modelFunctions->filterLanguage("uri", RC::get('fedoraTitleProp'), "title", $lang, true);
        $where .= $this->modelFunctions->filterLanguage("uri", RC::get('drupalHasDescription'), "descriptions", $lang, true);
        $where .= '?uri  <'.RC::get("drupalRdfType").'> ?type . 
                FILTER regex(str(?type),"vocabs.acdh","i") . ';
        $where .= '?uri <'.RC::get("fedoraIdProp").'> ?identifiers .
            }
            ';
        
        $groupBy = ' GROUP BY ?uri ?title ?pid ?accessRestriction ';
        $groupBy .= ' ORDER BY '.$orderby.' ( fn:lower-case(?title))';
        
        $queryStr = $prefix.$select.$where.$groupBy.$limitStr;
        
        try {
            $q = new SimpleQuery($queryStr);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            
            $fields = $res->getFields();
            $result = $this->oeawFunctions->createSparqlResult($res, $fields);
            
            return $result;
        } catch (\Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return $result;
        }
    }
    
    /**
     * Create the children data for the detail views
     *
     * @param string $uri -> resource URI
     * @param string $limit -> limit for pagination
     * @param string $offset -> offset for pagination
     * @param bool $count -> true = count the values
     * @param string $property -> the Prop which we need for get the data f.e. https://vocabs.acdh.oeaw.ac.at/schema#hasRelatedCollection
     * @param string $lang
     * @return array
     */
    public function getSpecialChildrenViewData(string $uri, string $limit, string $offset, bool $count = false, array $property, string $lang = ""): array
    {
        if ($offset < 0) {
            $offset = 0;
        }
        (empty($lang)) ? $lang = strtolower($this->siteLang) : $lang = strtolower($lang);
        $result = array();
        $select = "";
        $where = "";
        $limitStr = "";
        $queryStr = "";
        $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> ';
        
        if ($count == false) {
            $select = 'SELECT ?uri ?title (GROUP_CONCAT(DISTINCT ?type;separator=",") AS ?types) (GROUP_CONCAT(DISTINCT ?descriptions;separator=",") AS ?description) ';
            $limitStr = ' LIMIT '.$limit.'
            OFFSET '.$offset.' ';
        } else {
            $select = 'SELECT (COUNT(?uri) as ?count) ';
        }
        
        $where = '
            WHERE {
                <'.$uri.'> ?prop ?obj .
                FILTER( ?prop IN ( ';
        
        for ($x = 0; $x < count($property); $x++) {
            $where .='<'.$property[$x].'>';
            if ($x +1 < count($property)) {
                $where .= ', ';
            }
        }
        
        $where .='  )) . ';
        $where .= '?uri <'.RC::get('fedoraIdProp').'> ?obj .    ';
        $where .= $this->modelFunctions->filterLanguage("uri", RC::get('fedoraTitleProp'), "title", $lang, true);
        $where .= $this->modelFunctions->filterLanguage("uri", RC::get('drupalHasDescription'), "descriptions", $lang, true);
        $where .= '?uri  <'.RC::get("drupalRdfType").'> ?type . 
                FILTER regex(str(?type),"vocabs.acdh","i") . ';
        $groupBy = ' }  GROUP BY ?uri ?title ORDER BY ASC( fn:lower-case(?title))';
        
        $queryStr = $select.$where.$groupBy.$limitStr;
        
        try {
            $q = new SimpleQuery($queryStr);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            
            $fields = $res->getFields();
            $result = $this->oeawFunctions->createSparqlResult($res, $fields);
            
            return $result;
        } catch (\Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return $result;
        }
    }
    
    /**
     * This sparql will create an array with the ontology for the caching
     *
     * @param string $lang
     * @return array
     */
    public function getOntologyForCache(string $lang = ""): array
    {
        (empty($lang)) ? $lang = strtolower($this->siteLang) : $lang = strtolower($lang);
        $result = array();
        
        $select = 'prefix skos: <http://www.w3.org/2004/02/skos/core#> '
                . 'SELECT ?title ?id ?comment WHERE { ';
        $where = "?uri <".RC::get('drupalRdfType')."> ?type ."
                . "FILTER( ?type IN ( <http://www.w3.org/2002/07/owl#DatatypeProperty>, <http://www.w3.org/2002/07/owl#ObjectProperty>)) . "
                . "?uri <".RC::get('fedoraIdProp')."> ?id . ";
        $where .= $this->modelFunctions->filterLanguage("uri", "http://www.w3.org/2004/02/skos/core#altLabel", "title", $lang, true);
        $where .= $this->modelFunctions->filterLanguage("uri", RC::get('drupalRdfsComment'), "comment", $lang, true);
        $where .= " } ";
        $groupby = " GROUP BY ?id ?title ?comment ";
        $orderby = " ORDER BY ?title ";
        $queryStr = $select.$where.$groupby.$orderby;
        
        try {
            $q = new SimpleQuery($queryStr);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);

            $fields = $res->getFields();
            $result = $this->oeawFunctions->createSparqlResult($res, $fields);

            return $result;
        } catch (\Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return $result;
        }
        return $result;
    }
    
    /**
     * Get the root elements of a resource, based on the ispartof
     *
     * @param string $identifier
     * @param string $lang
     * @return array
     */
    public function createBreadcrumbData(string $identifier, string $lang = ""): array
    {
        (empty($lang)) ? $lang = strtolower($this->siteLang) : $lang = strtolower($lang);
        
        $result = array();
        $select = 'SELECT ?roots ?mainIspartOf ?rootId ?rootTitle ?rootsRoot WHERE { ';
        $where = " ?uri <".RC::get('fedoraIdProp')."> <".$identifier."> . ";
        $where .= " ?uri <".RC::get('fedoraRelProp')."> ?mainIspartOf . ";
        $where .= " ?uri (<".RC::get('fedoraRelProp')."> / ^<".RC::get('fedoraIdProp').">)* / <".RC::get('fedoraRelProp')."> ?rootId .  ";
        $where .= " ?roots <".RC::get('fedoraIdProp')."> ?rootId . ";
        $where .= $this->modelFunctions->filterLanguage("roots", RC::get('fedoraTitleProp'), "rootTitle", $lang, true);
        $where .= " OPTIONAL { ";
        $where .= " ?roots <".RC::get('fedoraRelProp')."> ?rootsRoot . ";
        $where .= " } ";
        $where .= " } ";
         
        $queryStr = $select.$where;
        
        try {
            $q = new SimpleQuery($queryStr);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);

            $fields = $res->getFields();
            $result = $this->oeawFunctions->createSparqlResult($res, $fields);
            $result = $this->oeawFunctions->formatBreadcrumbData($result);
            
            return $result;
        } catch (\Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return $result;
        }
        return $result;
    }
    
    /**
     * Get the last modify date
     *
     * @return string
     */
    public function getFDLastModifDate(): string
    {
        $result = array();
        $select = 'SELECT ?time WHERE { ';
        $where = " ?uri <".RC::get('doorkeeperModTimeProp')."> ?time . ";
        $where .= " } ";
         
        $queryStr = $select.$where;
        
        try {
            $q = new SimpleQuery($queryStr);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);

            $fields = $res->getFields();
            $result = $this->oeawFunctions->createSparqlResult($res, $fields);
            return $result[0]["time"];
        } catch (\Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return $result;
        }
        return $result;
    }
}
