<?php

namespace Drupal\oeaw\Model;

use Drupal\Core\Url;
use Drupal\oeaw\OeawFunctions;
use Drupal\oeaw\ConfigConstants;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\oeaw\Helper\Helper;

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




class OeawStorage implements OeawStorageInterface {

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
        'rdfType' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type',
        'rdfsLabel' => 'http://www.w3.org/2000/01/rdf-schema#label',
        'foafName' => 'http://xmlns.com/foaf/0.1/name',
        'foafImage' => 'http://xmlns.com/foaf/0.1/Image',
        'foafThumbnail' => 'http://xmlns.com/foaf/0.1/thumbnail',
        'rdfsSubClass' => 'http://www.w3.org/2000/01/rdf-schema#subClassOf',
        'rdfsSubPropertyOf' => 'http://www.w3.org/2000/01/rdf-schema#subPropertyOf',
        'owlClass' => 'http://www.w3.org/2002/07/owl#Class',
        'rdfsDomain' => 'http://www.w3.org/2000/01/rdf-schema#domain',
        'dctLabel' => 'http://purl.org/dc/terms/label',
        'owlOnProperty' => 'http://www.w3.org/2002/07/owl#onProperty',
        'owlCardinality' => 'http://www.w3.org/2002/07/owl#cardinality',
        'owlMinCardinality' => 'http://www.w3.org/2002/07/owl#minCardinality',
        'owlMaxCardinality' => 'http://www.w3.org/2002/07/owl#maxCardinality'        
    );
        
    
    private $oeawFunctions;    
    private $fedora;   
    private static $instance;
    //the date formats for the formatting possibilities
    private $dateFormats = array(
        'Y-m-d' => array('YEAR', 'MONTH', 'DAY'), 
        'd-m-Y' => array('DAY', 'MONTH', 'YEAR'), 
        'Y' => array('YEAR')
    );
    
    private function convertFieldDate(string $inputVar, string $outputVar, string $format): string{
        $result = "";
        
        if(!array_key_exists($format, $this->dateFormats)){
            $format = 'd-m-Y';
        }
        
        $count = count($this->dateFormats[$format]);
        $result = ' (CONCAT ( ';
        for ($x = 0; $x <= count($this->dateFormats[$format]) - 1; $x++) {
            //setup the vars
            $result .= 'STR( '.$this->dateFormats[$format][$x].'(?'.$inputVar.'))';
            //setup the 
            if( (count($this->dateFormats[$format]) - 1 > 1) && ( $x < count($this->dateFormats[$format]) - 1  ) ){
                $result .= ', "-", ';
            }
        }
        $result .= ') as ?'.$outputVar.')';
        
        return $result;
    }
    
    
    public function __construct() {
       
        \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');
                
        $this->oeawFunctions = new OeawFunctions();
        $this->fedora = new Fedora();        
        
        //blazegraph bugfix. Add missing namespace
        $blazeGraphNamespaces = \EasyRdf\RdfNamespace::namespaces();
        $localNamespaces = \Drupal\oeaw\ConfigConstants::$prefixesToBlazegraph;
                
        foreach($localNamespaces as $key => $val){
            if(!array_key_exists($val, $blazeGraphNamespaces)){
                \EasyRdf\RdfNamespace::set($key, $val);
            }
        }
        
        if (!self::$instance) {
            self::$instance = $this;
            return self::$instance;
        } else {
            return self::$instance;
        }
    }

    
    /*
     * Get the root elements from fedora
     *
     * @param int $limit Amount of resources to get
     * @param int $offset Offset for further pages
     * @param bool $count Whether to get the count of resources
     * @param string $order Order resources by, usage: ASC/DESC(?property)
     *
     * @return Array     
     */
    public function getRootFromDB(int $limit = 0, int $offset = 0, bool $count = false, string $order = "datedesc" ): array 
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
                $order = "ASC(?availableDate)";
                break;
            case "datedesc":
                $order = "DESC(?availableDate)";
                break;
            default:
                $order = "DESC(?availableDate)";
        }

        if($offset < 0) { $offset = 0; }
        
        $getResult = array();
       
        try {
            $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> ';
            $select = "";
            $orderby = "";
            $groupby = "";
            $query2 ="";
            $limitOffset = "";

            $where = " WHERE { ";

            $where .= "?uri <". RC::titleProp()."> ?title . ";
            $where .= "?uri <".RC::get('drupalRdfType')."> <".RC::get('drupalCollection')."> . ";
            $where .= "?uri <". RC::idProp()."> ?identifiers . ";
            $where .= "OPTIONAL { ?uri <".RC::get('epicPidProp')."> ?pid .  } ";
            $where .= "OPTIONAL { ?uri <".RC::get('drupalHasDescription')."> ?description .  } ";
            $where .= "OPTIONAL {?uri <".RC::get('drupalHasContributor')."> ?contributors . } ";
            $where .= "OPTIONAL {?uri <".RC::get('drupalHasCreatedDate')."> ?creationdate . } ";
            $where .= "OPTIONAL {?uri <".RC::get('drupalHasAvailableDate')."> ?avDate ."
                            . " BIND( (CONCAT(STR(substr(?avDate, 0, 10)))) as ?availableDate) . } ";
            $where .= "OPTIONAL {?uri <".RC::get('drupalHasCreationStartDate')."> ?hasCreationStartDate . } ";
            $where .= "OPTIONAL {?uri <".RC::get('drupalHasCreationEndDate')."> ?hasCreationEndDate . } ";
            $where .= "OPTIONAL {?uri <".RC::get('fedoraRelProp')."> ?isPartOf . } ";
            $where .= "OPTIONAL {?uri <".RC::get('drupalHasTitleImage')."> ?hasTitleImage . } ";

            if($count == false){
                    $where .= "?uri <".RC::get('drupalRdfType')."> ?rdfType .  ";
            }

            $where .=" 
                    filter not exists{ 
                        SELECT  * WHERE {
                            ?uri <https://vocabs.acdh.oeaw.ac.at/schema#isPartOf> ?y .
                        }
                    }
                    ";

            $where .= " } ";

            if($count == false){
                    $select = 'SELECT ?uri ?title ?pid ?availableDate ?isPartOf ?image ?hasTitleImage ?hasCreationStartDate ?hasCreationEndDate '
                                    . '(GROUP_CONCAT(DISTINCT ?rdfType;separator=",") AS ?rdfTypes) (GROUP_CONCAT(DISTINCT ?contributors;separator=",") AS ?contributor) '
                                    . '(GROUP_CONCAT(DISTINCT ?descriptions;separator=",") AS ?description)'
                                    . '(GROUP_CONCAT(DISTINCT ?identifiers;separator=",") AS ?identifier)';

                    $groupby = " GROUP BY ?uri ?title ?pid ?availableDate ?isPartOf ?image ?hasTitleImage ?hasCreationStartDate ?hasCreationEndDate ";
                    $orderby = " ORDER BY ".$order." ";
                    $limitOffset = "LIMIT ".$limit." OFFSET ".$offset." ";
            }else {
                    $select = " SELECT (COUNT(DISTINCT ?uri) as ?count) ";
                    $orderby = ' order by ?uri ';
            }

            $query = $prefix.$select.$where.$groupby.$orderby.$limitOffset;
            
            $result = $this->fedora->runSparql($query);
            if(count($result) > 0){
                $fields = $result->getFields();             
                $getResult = $this->oeawFunctions->createSparqlResult($result, $fields);        
                return $getResult;
            }else {                
                return $getResult;
            }
            
        } catch (Exception $ex) {            
            throw new Exception($ex->getMessage());
        }catch (\InvalidArgumentException $ex){
            throw new \InvalidArgumentException($ex->getMessage());
        }
    }

    /**
     * 
     * If we have hasPid as an URL then we need the acdh Identifier, to we can 
     * work with the resource data
     * 
     * @param string $pid
     * @return array
     */
    public function getACDHIdByPid(string $pid): array 
    {
        $getResult = array();
        
        try {
            $q = new Query();
            $q->addParameter((new HasValue(RC::get('epicPidProp'), $pid ))->setSubVar('?uri'));
            $q->addParameter(new HasTriple('?uri', RC::get('fedoraIdProp'), '?id'));
            $query = $q->getQuery();
            $result = $this->fedora->runSparql($query);
            
            $fields = $result->getFields();             
            $getResult = $this->oeawFunctions->createSparqlResult($result, $fields);        
            return $getResult;
            
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage());
        } catch (\InvalidArgumentException $ex){            
            throw new \InvalidArgumentException($ex->getMessage());
        }
    }
   
    /**
     * 
     * Get the reource title by its acdh:hasIdentifier property
     * 
     * @param string $string
     * @return array
     * 
     */
    public function getTitleByIdentifier(string $string): array
    {
        $getResult = array();
        
        try {
            
            $q = new Query();
            $q->addParameter((new HasValue(RC::idProp(), $string ))->setSubVar('?uri'));
            $q->addParameter(new HasTriple('?uri', RC::titleProp(), '?title'));
            
            $query = $q->getQuery();
            $result = $this->fedora->runSparql($query);
            
            $fields = $result->getFields();             
            $getResult = $this->oeawFunctions->createSparqlResult($result, $fields);        
            return $getResult;
        }  catch (Exception $ex) {
            return array();
        } catch (\InvalidArgumentException $ex){            
            return array();
        }
    }
    
    /**
     * Create the property data for the expert view
     * 
     * @param array $data
     * @return type
     */
    public function getPropDataToExpertTable(array $data): array
    {
        $result = array();

        if(count($data) > 0){
            $where = "";
            $i = 0;
            
            foreach ($data as $key => $value){
                $where .= " { ";
                $where .= "?uri <".RC::get('fedoraIdProp')."> <".$value."> . ";
                $where .= "?uri <".RC::get('fedoraIdProp')."> ?identifier . ";
                $where .= "?uri <".RC::get('drupalRdfsLabel')."> ?title . ";
                $where .= "?uri <".RC::get('drupalRdfsComment')."> ?comment . ";
                $where .= " } ";

                if($i != count($data) - 1){
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

            } catch (Exception $ex) {
                return $result;
            } catch (\GuzzleHttp\Exception\ClientException $ex){
                return $result;
            }
        }
        
        return $result;
    }
    
    /***
     * 
     * 
     */
    public function getResourceTitle(string $uri): array 
    {
        $getResult = array();
        
        try {
            
            $q = new Query();            
            $q->addParameter(new HasTriple($uri, RC::titleProp(), '?title'), true);
            
            $query = $q->getQuery();
            $result = $this->fedora->runSparql($query);
            
            $fields = $result->getFields();             
            $getResult = $this->oeawFunctions->createSparqlResult($result, $fields);        
            return $getResult;
            
        } catch (Exception $ex) {
           return array();            
        }catch (\InvalidArgumentException $ex){
           return array();
        }
    }
    
    /**
     * 
     * Get all property for search
     * 
     * @return array
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
            
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage());
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            throw new \GuzzleHttp\Exception\ClientException($ex->getMessage());
        }
    }

    /**
     * 
     * Get value by the resource uri and property
     * 
     * @param string $uri
     * @param string $property
     * @return array
     * 
     */
    public function getValueByUriProperty(string $uri, string $property): array
    {
        if (empty($uri) || empty($property)) {
            return drupal_set_message(t('Empty values! -->'.__FUNCTION__), 'error');
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
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            return $getResult;
        } 
        catch (\Symfony\Component\Routing\Exception\InvalidParameterException $ex){
            return $getResult;
        } 
        
    }

    /**
     * 
     * Get a value as string with resource uri and property
     * 
     * @param string $uri
     * @param string $property
     * @return string
     * 
     */
    public function getPropertyValueByUri(string $uri, string $property): string
    {
        if (empty($uri) || empty($property)) {
            return drupal_set_message(t('Empty values! -->'.__FUNCTION__), 'error');
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
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            return $getResult;
        } 
        catch (\Symfony\Component\Routing\Exception\InvalidParameterException $ex){
            return $getResult;
        } 
    }

    /**
     * 
     * Get all data by property and value
     * 
     * @param string $property
     * @param string $value
     * @return array
     */
    public function getDataByProp(string $property, string $value, int $limit = 0, int $offset = 0, bool $count = false): array {
        
        if (empty($value) || empty($property)) {
            return drupal_set_message(t('Empty values! -->'.__FUNCTION__), 'error');
        }
       if($offset < 0) { $offset = 0; }
       
        if(!filter_var($property, FILTER_VALIDATE_URL)){
            $property = Helper::createUriFromPrefix($property);
            if($property === false){
                return drupal_set_message(t('Error in function: '.__FUNCTION__), 'error'); 
            }           
        }else if(filter_var($property, FILTER_VALIDATE_URL)){            
            $property = '<'. $property .'>';
        }
        
        if(!filter_var($value, FILTER_VALIDATE_URL)){
            $value = Helper::createUriFromPrefix($value);
            if($value === false){
                return drupal_set_message(t('Error in function: '.__FUNCTION__), 'error'); 
            }
           
        }else if(filter_var($value, FILTER_VALIDATE_URL)){            
            $value = '<'. $value .'>';
        }        

        $getResult = array();

        try {        
                        
            
            $rdfsLabel = self::$sparqlPref["rdfsLabel"];            

            $q = new Query();            
            $q->addParameter((new HasValue($property, $value))->setSubVar('?uri'));
            
            if($count == false){
                //Query parameters for the properties we want to get, true stands for optional
                $q->addParameter((new HasTriple('?uri', RC::titleProp(), '?title')), true);
                $q->addParameter(new HasTriple('?uri', RC::get('drupalHasAuthor'), '?author'), true);           
                $q->addParameter(new HasTriple('?uri', RC::get('drupalHasDescription'), '?descriptions'), true);
                $q->addParameter(new HasTriple('?uri', $rdfsLabel, '?label'), true);
                $q->addParameter(new HasTriple('?uri', RC::get('drupalHasContributor'), '?contributor'), true);            
                $q->addParameter(new HasTriple('?uri', RC::get('drupalHasCreatedDate'), '?creationdate'), true);
                $q->addParameter(new HasTriple('?uri', RC::get('fedoraRelProp'), '?isPartOf'), true);
                $q->addParameter(new HasTriple('?uri', RC::get('drupalRdfType'), '?rdfType'), true);
                $q->addParameter(new HasTriple('?uri', RC::get('drupalHasFirstName'), '?firstName'), true);
                $q->addParameter(new HasTriple('?uri', RC::get('drupalHasLastName'), '?lastName'), true);
                $q->addParameter(new HasTriple('?uri', '<http://fedora.info/definitions/v4/repository#created>', '?fdCreated'), true);
                //Select and aggregate multiple sets of values into a comma seperated string
                $q->setSelect(array('?uri', '?title', '?label', '?creationdate', '?isPartOf', '?firstName', '?lastName', $this->convertFieldDate("fdCreated", "fdCreated", 0), '(GROUP_CONCAT(DISTINCT ?descriptions;separator=",") AS ?description)', '(GROUP_CONCAT(DISTINCT ?author;separator=",") AS ?authors)', '(GROUP_CONCAT(DISTINCT ?contributor;separator=",") AS ?contributors)', '(GROUP_CONCAT(DISTINCT ?rdfType;separator=",") AS ?rdfTypes)'));
                $q->setGroupBy(array('?uri', '?title', '?label', '?creationdate', '?isPartOf', '?firstName', '?lastName', '?fdCreated'));
                //If it's a person order by their name, if not by resource title
                if ($value == RC::get('drupalPerson') ) {
                        $q->setOrderBy(array('?firstName'));
                } else {
                        $q->setOrderBy(array('?title'));
                }
                $q->setLimit($limit);
                $q->setOffset($offset); 
            }else {
                $q->setSelect(array('(COUNT(?uri) as ?count)'));
                $q->setOrderBy(array('?uri'));
            }                       
            
            $query = $q->getQuery();

            $result = $this->fedora->runSparql($query);
            
            $fields = $result->getFields(); 
            $getResult = $this->oeawFunctions->createSparqlResult($result, $fields);

            return $getResult;                
        
        } catch (\Exception $ex) {            
            throw new \Exception($ex->getMessage());
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            throw new \Exception($ex->getMessage());
        } catch (\Symfony\Component\Routing\Exception\InvalidParameterException $ex){
            throw new \Exception($ex->getMessage());
        } 
    }
    
    /* 
     *
     * Get all class data for the new resource adding form.
     *     
     * @return Array
    */
    public function getClass(): array {
        
        $getResult = array();
        
        try {        
            
             $query =
                ' 
                    SELECT DISTINCT ?title ?uri ?id ?origTitle
                    WHERE {
                        ?uri <'.RC::get("drupalRdfType").'> <http://www.w3.org/2002/07/owl#Class> .
                        ?uri <'.RC::get("fedoraTitleProp").'> ?origTitle .
                        ?uri <'.RC::get("fedoraIdProp").'> ?id .
                        BIND(REPLACE(?origTitle, " ", "_", "i") AS ?t2) .
                      	BIND (lcase(?t2) AS ?title) .
                        FILTER regex( str(?id), "vocabs.acdh", "i") .  
                    } 
                    ORDER BY UCASE(str(?title))
            ';
             
            $result = $this->fedora->runSparql($query);
            $fields = $result->getFields(); 
            $getResult = $this->oeawFunctions->createSparqlResult($result, $fields);
            
            return $getResult; 
            
        } catch (Exception $ex) {
            throw new \ErrorException($ex->getMessage());
            
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            throw new \ErrorException($ex->getMessage());
        }  
    }
   
    /* 
     *
     * Get the digital rescources to we can know which is needed a file upload
     *     
     *
     * @return Array     
    */    
    public function getDigitalResources(): array
    {        
        $getResult = array();
        
        try {            
            
            $dcID = RC::idProp();            
            $owlClass = self::$sparqlPref["owlClass"];
         
            $query=
                self::$prefixes . ' 
                    SELECT 
                        ?id ?collection 
                    WHERE {
                            ?class a owl:Class .
                            ?class dct:identifier ?id .
                            OPTIONAL {
                              {
                                {?class rdfs:subClassOf* <'.RC::get('drupalCollection').'>}
                                UNION
                                {?class rdfs:subClassOf* <'.RC::get('drupalDigitalCollection').'>}
                                UNION
                                {?class dct:identifier <'.RC::get('drupalCollection').'>}
                                UNION
                                {?class dct:identifier <'.RC::get('drupalDigitalCollection').'>}
                              }
                              VALUES ?collection {true}
                            }
                        }
            ';
      
            $result = $this->fedora->runSparql($query);
            $fields = $result->getFields(); 
            $getResult = $this->oeawFunctions->createSparqlResult($result, $fields);

            return $getResult;
            
        } catch (Exception $ex) {
           return array();
        } catch (\GuzzleHttp\Exception\ClientException $ex){
           return array();
        }  
    }
    
    /* 
     *
     *  Get the digital rescources Meta data and the cardinality data by ResourceUri
     *
     * @param string $classURI 
     *
     * @return Array
    */
    public function getClassMetaForApi(string $classString, string $lang = "en"): array{
        
        if (empty($classString)) {
            return drupal_set_message(t('Empty values! -->'.__FUNCTION__), 'error');
        }
        
        $lang = strtolower($lang);
        
        $prefix = "prefix owl: <http://www.w3.org/2002/07/owl#> "
                . "prefix skos: <http://www.w3.org/2004/02/skos/core#> ";
                
        $select = "select ?uri ?propID ?propTitle ?range ?subUri ?cardinality ?maxCardinality ?minCardinality ?order ?vocabs "
                . "(GROUP_CONCAT(DISTINCT ?comments;separator=',') AS ?comment) "
                . "(GROUP_CONCAT(DISTINCT ?recommendedClasses;separator=',') AS ?recommendedClass)  "
                . "where { ";
        
        $where = " ?mainURI <".RC::get('fedoraIdProp').">  <".RC::get('fedoraVocabsNamespace').$classString."> . "
                . "?mainURI (rdfs:subClassOf / ^<".RC::get('fedoraIdProp').">)* / rdfs:subClassOf ?class . "
                . "{ ?uri rdfs:domain ?class . "
                . " ?uri skos:altLabel ?propTitle .  "
                . " FILTER regex(lang(?propTitle), '".$lang."','i') . "
                . "} UNION { "
                . " ?mainURI <".RC::get('fedoraIdProp')."> ?mainID ."
                . " ?uri rdfs:domain ?mainID . "
                . " ?uri skos:altLabel ?propTitle ."
                . " FILTER regex(lang(?propTitle), '".$lang."','i') . "
                . " } "
                . "?uri <".RC::get('fedoraIdProp')."> ?propID . ";
        
        $optionals = "	
            OPTIONAL {
                ?uri <".RC::get('fedoraVocabsNamespace')."ordering> ?order .
            }
            OPTIONAL {
                ?uri <".RC::get('fedoraVocabsNamespace')."recommendedClass> ?recommendedClasses .
            }
            OPTIONAL {
                ?uri <".RC::get('fedoraVocabsNamespace')."vocabs> ?vocabs .
            }
            OPTIONAL {
                ?uri rdfs:comment ?comments .
                FILTER regex(lang(?comments), '".$lang."','i') .
            }
            OPTIONAL{ 
                SELECT  * WHERE { 
                    ?uri rdfs:range ?range .
                }
            } ";
        $optionals .= "OPTIONAL {
    	
  		?subUri owl:onProperty ?propID .
                OPTIONAL {
                    ?subUri owl:maxCardinality ?maxCardinality .
                }
                OPTIONAL {
                    ?subUri owl:minxCardinality ?minCardinality .
                }
                OPTIONAL {
                    ?subUri owl:cardinality ?cardinality .
                }
                
           
        }"; 
        $union = " } UNION { ";
        
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
            
            
        } catch (Exception $ex) {
           return array();
        } catch (\GuzzleHttp\Exception\ClientException $ex){
           return array();
        }  
    }
    
    /**
     * 
     * Get the a
     * 
     * @param string $id
     * @return string
     */
    public function getFedoraUrlByIdentifierOrPid(string $id): string {
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
            if(count($data) > 0){
                if(isset($data[0]['pid']) && !empty($data[0]['pid'])){
                    $result = $data[0]['pid'];
                }else if(isset($data[0]['uri']) && !empty($data[0]['uri'])){
                    $result = $data[0]['uri'];
                }
            }
            return $result;
            
            
        } catch (Exception $ex) {            
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            return $result;
        } 
    }
    
    /* 
     *
     * We using it for the NEW/EDIT FORMS
     *  Get the digital rescources Meta data and the cardinality data by ResourceUri
     *
     * @param string $classURI 
     *
     * @return Array
    */
    public function getClassMeta(string $classURI): array{
       
        if (empty($classURI)) {
            return drupal_set_message(t('Empty values! -->'.__FUNCTION__), 'error');
        }
        
        $getResult = array();
        
        $rdfsSubClass = self::$sparqlPref['rdfsSubClass'];
        $rdfsDomain = self::$sparqlPref["rdfsDomain"];
        $owlCardinality = self::$sparqlPref["owlCardinality"];
        $owlMinCardinality = self::$sparqlPref["owlMinCardinality"];
        $owlMaxCardinality = self::$sparqlPref["owlMaxCardinality"];
        $rdfsSubPropertyOf = self::$sparqlPref["rdfsSubPropertyOf"];
        
        
        
        $string = "
            SELECT  
                ?prop ?propID ?propTitle ?cardinality ?minCardinality ?maxCardinality ?range (GROUP_CONCAT(DISTINCT ?comments;separator=',') AS ?comment)
            WHERE 
            {
                {";
        //where the person id a subclassof
        $string .= "        
            <".$classURI."> <".RC::get('fedoraTitleProp')."> ?classTitle .            
            <".$classURI."> (rdfs:subClassOf / ^<https://vocabs.acdh.oeaw.ac.at/schema#hasIdentifier>)* / rdfs:subClassOf ?class . 
        ";
        //get the properties where the person subclass is the domain
        $string .= "
            ?prop <".$rdfsDomain."> ?class .
            ?prop <".RC::idProp()."> ?propID .
            ?prop <".RC::get('fedoraTitleProp')."> ?propTitle .             
        ";
        
        $optionals = "";
        $optionals = "
            OPTIONAL{ 
                SELECT  * WHERE { ?prop <".$owlCardinality."> ?cardinality .}
            }
            OPTIONAL { 
                SELECT  * WHERE { ?prop <".$owlMinCardinality."> ?minCardinality .}
            }
            OPTIONAL {
                SELECT  * WHERE { ?prop <http://www.w3.org/2000/01/rdf-schema#range> ?range .}
            }
            OPTIONAL {
                SELECT  * WHERE { ?prop <".$owlMaxCardinality."> ?maxCardinality .}
            }
            OPTIONAL {
                SELECT  * WHERE { ?prop <http://www.w3.org/2000/01/rdf-schema#comment> ?comments .}
            }
        ";
        
        $string .= $optionals;
        
        $string .="
            } UNION {
                <".$classURI."> <".RC::idProp()."> ?classID .
                ?prop <".$rdfsDomain."> ?classID .
                ?prop <".RC::idProp()."> ?propID .
                ?prop <".RC::get('fedoraTitleProp')."> ?propTitle .
        "; 
        
        $string .= $optionals;
        
        $string .= " } }"
                . "GROUP BY ?prop ?propID ?propTitle ?cardinality ?minCardinality ?maxCardinality ?range
                    ORDER BY ?propID";
        
        try {
            
            $q = new SimpleQuery($string);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            
            $fields = $res->getFields();
            $result = $this->oeawFunctions->createSparqlResult($res, $fields);
            return $result;
            
            
        } catch (Exception $ex) {
           return array();
        } catch (\GuzzleHttp\Exception\ClientException $ex){
           return array();
        }  
    }
    
    /**
     * 
     * Get the image by the identifier
     * 
     * @param string $string - image acdh:hasIdentifier value
     * @return string - the fedora url of the image
     * 
     */
    public function getImageByIdentifier(string $string): string{
        
        $return = "";
        if (empty($string)) {
            return drupal_set_message(t('Empty values! -->'.__FUNCTION__), 'error');
        }
       
        try{            
            $q = new Query();
            $q->setSelect(array('?uri'));
            $q->addParameter((new HasValue(RC::idProp(), $string))->setSubVar('?uri'));
            $q->addParameter((new HasValue(RC::get('drupalRdfType'), RC::get('drupalImage')))->setSubVar('?uri'));
            $query = $q->getQuery();
            $result = $this->fedora->runSparql($query);
            foreach($result as $r){
                if($r->uri){
                    $return = $r->uri->getUri();
                }
            }
            return $return;
        } catch (Exception $ex) {
            return "";
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            return "";
        }  
    }
    
    /*
     * 
     * Get the resource thumbnail image
     * 
     * @param string $value -> the property value 
     * @param string $property -> the property
     * 
     * @return string
     * 
     */
    
    public function getImage(string $value, string $property = null ): string
    {         
        
        if (empty($value)) {
            drupal_set_message(t('Empty values! -->'.__FUNCTION__), 'error');
            return "";
        }
        
        if($property == null){ $property = RC::idProp(); } 
        $foafImage = self::$sparqlPref["foafImage"];
        $res = "";

        try{            
            $q = new Query();
            $q->setSelect(array('?res'));
            $q->addParameter((new HasValue($property, $value)));
           
            $query = $q->getQuery();
            $result = $this->fedora->runSparql($query);

            foreach($result as $r){
                if($r->res){
                    $res = $r->res->getUri();
                }                
            }
            return $res;
        } catch (Exception $ex) {
            return "";
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            return "";
        }  
    }
    
    
    /**
     * 
     * Get the acdh:isMember values by resource URI for the Organisation view.
     * 
     * @param string $uri
     * @param string $limit
     * @param string $offset
     * @param bool $count
     * @return array
     * 
     */
    public function getIsMembers(string $uri): array {
        
        if (empty($uri)) {
            return drupal_set_message(t('Empty values! -->'.__FUNCTION__), 'error');
        }
        
        $result = array();
        $select = "";
        $where = "";
        $limitStr = "";
        $queryStr = "";
        $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> ';
        
            $select = 'SELECT ?uri ?title ';
        
        $where = '
            WHERE {
                <'.$uri.'> <'.RC::get("fedoraIdProp").'> ?id .
                ?uri <'.RC::get('drupalIsMember').'> ?id .
                ?uri <'.RC::get("fedoraTitleProp").'> ?title .
            }
            ';
        
        $groupBy = ' GROUP BY ?uri ?title ORDER BY ASC( fn:lower-case(?title))';
        
        $queryStr = $select.$where.$groupBy.$limitStr;
        
        try {
            $q = new SimpleQuery($queryStr);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            
            $fields = $res->getFields(); 
            $result = $this->oeawFunctions->createSparqlResult($res, $fields);
            
            return $result;

        } catch (Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            return $result;
        }
    }
    
  
    /**
     * 
     * We are using this sparql if we want to get Special children data by the property
     * We have also a similar sparql which is the getSpecialDetailViewData, but there we
     * have some extra filtering, this sparql is the clone of the get ChildrenViewData
     * just with a property
     * 
     * @param string $uri -> defora uri of the actual resource
     * @param string $limit -> pagination limit
     * @param string $offset -> pagination offset
     * @param bool $count -> pagination count
     * @param array $property -> the property from the config.ini what is the "Parent"
     * @return array
     */
    public function getChildResourcesByProperty(string $uri, string $limit, string $offset, bool $count, array $property): array{
        
        if (empty($uri)) {
            return drupal_set_message(t('Empty values! -->'.__FUNCTION__), 'error');
        }
        
        if($offset < 0) { $offset = 0; }
        $result = array();
        $select = "";
        $where = "";
        $limitStr = "";
        $queryStr = "";
        
        $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> ';
        if($count == false){
            $select = 'SELECT ?uri ?title (GROUP_CONCAT(DISTINCT ?type;separator=",") AS ?types) (GROUP_CONCAT(DISTINCT ?descriptions;separator=",") AS ?description)  ';            
            $limitStr = ' LIMIT '.$limit.'
            OFFSET '.$offset.' ';
        }else {
            $select = 'SELECT (COUNT(?uri) as ?count) ';            
        }
        
        $where = '
            WHERE {
                <'.$uri.'>  <'.RC::get("fedoraIdProp").'> ?id  . ';
        foreach($property as $p){
            $where .= ' ?uri <'.$p.'> ?id . ';
        }
                
         $where .= 'OPTIONAL { ?uri <'.RC::get("fedoraTitleProp").'> ?title .} '
                . 'OPTIONAL { ?uri <'.RC::get("drupalHasDescription").'> ?descriptions .} '
                . '?uri  <'.RC::get("drupalRdfType").'> ?type . '
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

        } catch (Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            return $result;
        }
    }
    
    /**
     * 
     * Get the necessary data for the children view
     * 
     * @param string $uri  the root uri
     * @param string $limit the pagination limit
     * @param string $offset
     * @param bool $count
     * @return array
     * 
     * 
     */
    public function getChildrenViewData(array $ids, string $limit, string $offset, bool $count = false): array {
        
        if (count($ids) < 0) { return array(); }
        if($offset < 0) { $offset = 0; }
        $result = array();
        $select = "";
        $where = "";
        $limitStr = "";
        $queryStr = "";
        $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> ';
        if($count == false){
            $select = 'SELECT ?uri ?title ?pid (GROUP_CONCAT(DISTINCT ?descriptions;separator=",") AS ?description) (GROUP_CONCAT(DISTINCT ?type;separator=",") AS ?types) (GROUP_CONCAT(DISTINCT ?identifiers;separator=",") AS ?identifier)  ';            
            $limitStr = ' LIMIT '.$limit.'
            OFFSET '.$offset.' ';
        }else {
            $select = 'SELECT (COUNT(?uri) as ?count) ';            
        }
        
        $where = '
            WHERE {
                ?uri <'.RC::get("fedoraTitleProp").'> ?title .
                OPTIONAL { ?uri <'.RC::get("drupalHasDescription").'> ?descriptions .}
                OPTIONAL { ?uri <'.RC::get("epicPidProp").'> ?pid .} 
                ?uri  <'.RC::get("drupalRdfType").'> ?type .
                ?uri <'.RC::idProp().'> ?identifiers . 
                FILTER regex(str(?type),"vocabs.acdh","i") .
                ?uri <'.RC::get("fedoraRelProp").'>  ?isPartOf .
                FILTER ( 
            ';
        
        $num = count($ids);
        
        for ($i = 0; $i <= $num -1 ; $i++) {
                        
            $where .= '?isPartOf =  <'.$ids[$i].'> ';
            if($i !== ($num - 1)){
                $where .= ' || ';
            }
        }
        $where .= ')';
        $groupBy = ' }  GROUP BY ?uri ?title ?pid ORDER BY ASC( fn:lower-case(?title))';
        
        $queryStr = $select.$where.$groupBy.$limitStr;
        
        try {
            $q = new SimpleQuery($queryStr);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            
            $fields = $res->getFields(); 
            $result = $this->oeawFunctions->createSparqlResult($res, $fields);
            
            return $result;

        } catch (Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            return $result;
        }
        
        
    }
    
    /**
     * 
     * Get the HasMetadata Inverse property by Resource Identifier
     * 
     * 
     * @param string $id
     * @return array
     * 
     * 
     */
    public function getMetaInverseData(string $uri): array{
        
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
            foreach($res as $r){
                foreach($r as $k => $v){                    
                    $result[$i][$k] = $v;
                    if($k == "uri"){
                        $title = $this->oeawFunctions->getTitleByUri($v);
                        $result[$i]["title"] = $title;
                        $result[$i]["insideUri"] = $this->oeawFunctions->detailViewUrlDecodeEncode($v, 1);
                    }
                    if($k == "prop"){                        
                        $shortcut = $this->oeawFunctions->createPrefixesFromString($v);
                        $result[$i]["shortcut"] = $shortcut;
                    }
                }
                $i++;
            }
            
            return $result;

        } catch (Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            return $result;
        }
    }
    
    /**
     * 
     * Create the Inverse table data by URL
     * 
     * @param string $url
     * @return array
     */
    public function getInverseViewDataByURL(string $url): array{
        
        $result = array();
        
        $where .= '<'.$url.'> <'.RC::get("fedoraIdProp").'> ?obj .'
                . '?uri ?prop ?obj .'
                . 'MINUS { ?uri <'.RC::get("fedoraIdProp").'> ?obj  } . MINUS { ?uri <'.RC::get("fedoraRelProp").'> ?obj  } . '
                . '?propUri <'.RC::get("fedoraIdProp").'> ?prop .'
                . '?propUri <'.RC::get("drupalOwlInverseOf").'> ?inverse .';
                
        $select = '
            select DISTINCT ?uri ?prop ?obj ?inverse where { ';
        $end = ' } ';
        
        $string = $select.$where.$end;
        
        try {
            $q = new SimpleQuery($string);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            
            $fields = $res->getFields(); 
            $res = $this->oeawFunctions->createSparqlResult($res, $fields);
            
            $i = 0;
            foreach($res as $r){
                foreach($r as $k => $v){                    
                    $result[$i][$k] = $v;
                    if($k == "uri"){
                        $title = $this->oeawFunctions->getTitleByUri($v);
                        $result[$i]["title"] = $title;
                        $result[$i]["insideUri"] = $this->oeawFunctions->detailViewUrlDecodeEncode($v, 1);
                    }
                    if($k == "prop"){                        
                        $shortcut = $this->oeawFunctions->createPrefixesFromString($v);
                        $result[$i]["shortcut"] = $shortcut;
                    }
                }
                $i++;
            }
            
            return $result;

        } catch (Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            return $result;
        }
    }
    
    
    /**
     * 
     * Create the data for the InverseViews by the Resource Identifier
     * 
     * @param array $data
     * @return array
     */
    public function getInverseViewDataByIdentifier(array $data): array{
        
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
            if($i !== ($num - 1)){
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
            foreach($res as $r){
                foreach($r as $k => $v){                    
                    $result[$i][$k] = $v;
                    if($k == "uri"){
                        $title = $this->oeawFunctions->getTitleByUri($v);
                        $result[$i]["title"] = $title;
                        $result[$i]["insideUri"] = $this->oeawFunctions->detailViewUrlDecodeEncode($v, 1);
                    }
                    if($k == "prop"){                        
                        $shortcut = $this->oeawFunctions->createPrefixesFromString($v);
                        $result[$i]["shortcut"] = $shortcut;
                    }
                }
                $i++;
            }
            
            return $result;

        } catch (Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            return $result;
        }
    }
    
    
    /**
     * 
     * Run users sparql from the resource views
     * 
     * @param string $string
     * @return array
     */
    public function runUserSparql(string $string): array
    {
        $result = array();
        
        try {
            $q = new SimpleQuery($string);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            
            $fields = $res->getFields();
            $result = $this->oeawFunctions->createSparqlResult($res, $fields);
            return $result;

        } catch (Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            return $result;
        } 
    }
    
    /**
     *  Check labels for the autocomplete input fields
     * 
     * @param string $string
     * @param string $property
     * @return array
     */
    public function checkValueToAutocomplete(string $string, string $property): array{
        
        if (empty($string) || empty($property)) {
            return drupal_set_message(t('Empty values! -->'.__FUNCTION__), 'error');
        }
                
        $rdfsLabel = self::$sparqlPref["rdfsLabel"];
        $getResult = array();
        
        //we need to extend the Filter options in the DB class, to we can use the
        // Filter =  value
        try {
           
            $q = new Query();
            $q->setSelect(array('?res'));
            $q->setDistinct(true);
            $q->addParameter((new HasValue(RC::get("drupalRdfType"), $property))->setSubVar('?res'));
            $q->addParameter(new MatchesRegEx(RC::titleProp(), $string), 'i');
            $query = $q->getQuery();
          
            $result = $this->fedora->runSparql($query);
           
            $fields = $result->getFields(); 
            $getResult = $this->oeawFunctions->createSparqlResult($result, $fields);
           
            return $getResult;

        } catch (Exception $ex) {
           return array();
        } catch (\GuzzleHttp\Exception\ClientException $ex){
           return array();
        }
    }
    
    /**
     * 
     * Get the MIME infos
     * 
     * @return array
     */
    public function getMimeTypes(): array{
        
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

        } catch (Exception $ex) {
           return array();
        } catch (\GuzzleHttp\Exception\ClientException $ex){
           return array();
        }  
    }
    
    /**
     * 
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
            if($count == true){
                $select = "SELECT  ?type (COUNT(?type) as ?typeCount) ";
            }else {
                $select = "SELECT  DISTINCT ?type ";
            }
            
            $filter = "FILTER (regex(str(?type), '".RC::vocabsNmsp()."', 'i')) .";
            if($searchBox == true){
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
        } catch (\GuzzleHttp\Exception\ClientException $ex){
           return array();
        }  
    }
    
    /**
     * 
     * Generate the data for the left side complexSearch Year searching function
     * 
     * @return array
     * 
     */
    public function getDateForSearch(): array
    {
        $result = array();
            
        $queryStr = 'SELECT ?year (COUNT(?year) as ?yearCount) WHERE { '
                . '?uri <'.RC::get('fedoraAvailableDateProp').'> ?date . '
                . 'BIND( (CONCAT(STR(substr(?date, 0, 4)))) as ?year) .'
                . ' } '
                . 'GROUP BY ?year '
                . 'ORDER BY DESC(?year) ';
        
        try {
            $q = new SimpleQuery($queryStr);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);

            $fields = $res->getFields(); 
            $result = $this->oeawFunctions->createSparqlResult($res, $fields);

            return $result;

        } catch (Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            return $result;
        }
        
        
        return $result;
    }
    
    
    /*
     * 
     * Get the actual classes for the SideBar block
     * 
     * @return array
     * 
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

        } catch (Exception $ex) {            
            throw new \ErrorException($ex->getMessage());
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            throw new \ErrorException($ex->getMessage());
        }  
    }
    
    
    /**
     * 
     * This func gets the parent title from the DB
     * 
     * @param string $id - the uri id of the parent
     * @return array
     */
    public function getParentTitle(string $id): array
    {
        $result = array();
        
        if($id){
            
            $where = "";
            $where .= " WHERE { ";
            $where .= "?uri <".RC::get('fedoraIdProp')."> <".$id."> . ";
            $where .= "?uri <".RC::titleProp()."> ?title . ";
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
 
             } catch (Exception $ex) {
                return $result;
            } catch (\GuzzleHttp\Exception\ClientException $ex){
                return $result;
            }
        }
        
        return $result;
    }
    
    /**
     * 
     * Get the titles for the detail view property values
     * 
     * @param array $data
     * @param bool $dissemination true: get some extra prop for the dissServ
     * @return array
     */
    public function getTitleByIdentifierArray(array $data, bool $dissemination = false): array
    {
        $result = array();
        if(count($data) > 0){
            $where = "";
            $i = 0;
            $select = "";
            foreach ($data as $key => $value){
                $where .= " { ";
                $where .= "?uri <".RC::get('fedoraIdProp')."> <".$value."> . ";
                //$where .= "?uri <".RC::get('fedoraIdProp')."> ?identifier . ";
                $where .= "?uri <".RC::titleProp()."> ?title . "
                        . "OPTIONAL {"
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
                
                if($dissemination == true){
                    $where .= "OPTIONAL {?uri <".RC::get('fedoraServiceRetFormatProp')."> ?returnType . } ";
                    $where .= "OPTIONAL {?uri <".RC::get('drupalHasDescription')."> ?description . } ";
                    $where .= "FILTER (!regex(str(?identifier),'.at/uuid/','i')) .";
                }
                
                $where .= " } ";
                
                if($i != count($data) - 1){
                    $where .= " UNION ";
                }
                $i++;
            }
            
            if($dissemination == true){
                $select = 'SELECT DISTINCT ?title ?identifier ?uri ?uuid ?pid ?vocabs ?returnType ?description WHERE { ';
            }else{
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
 
             } catch (Exception $ex) {
                return $result;
            } catch (\GuzzleHttp\Exception\ClientException $ex){
                return $result;
            }
        }
        
        return $result;
    }
    
    /**
     * 
     * Create the Sparql Query for the special ACDH rdf:type "children views"
     * 
     * @param string $uri - the resource fedora uri
     * @param string $limit 
     * @param string $offset
     * @param bool $count - we need a count sparql or not
     * @param array $property - the properties array
     * @return array
     */
    public function getSpecialDetailViewData(string $uri, string $limit, string $offset, bool $count = false, array $property): array 
    {
        if($offset < 0) { $offset = 0; }
        $result = array();
        $select = "";
        $where = "";
        $limitStr = "";
        $queryStr = "";
        $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> ';
        if($count == false){
            $select = 'SELECT ?uri ?title ?pid (GROUP_CONCAT(DISTINCT ?identifiers;separator=",") AS ?identifier) (GROUP_CONCAT(DISTINCT ?descriptions;separator=",") AS ?description) (GROUP_CONCAT(DISTINCT ?type;separator=",") AS ?types) ';
            $limitStr = ' LIMIT '.$limit.'
            OFFSET '.$offset.' ';
        }else {
            $select = 'SELECT (COUNT(?uri) as ?count) ';
        }
        
        $where = '
            WHERE {
                ?mainUri <'.RC::get("fedoraIdProp").'> <'.$uri.'> . '
                . '?mainUri <'.RC::get("fedoraIdProp").'> ?id . '
                . 'OPTIONAL { ?mainUri <'.RC::get("epicPidProp").'> ?pid . } . '
                . '?uri ?prop ?id . '
                . 'FILTER( ?prop IN ( ';
        for ($x = 0; $x < count($property); $x++) {
            $where .='<'.$property[$x].'>';
            if($x +1 < count($property)){
                $where .= ', ';
            }
        } 
        
        $where .= ' )) . '
                . ' OPTIONAL { ?uri <'.RC::get("fedoraTitleProp").'> ?title .} 
                OPTIONAL { ?uri <'.RC::get("drupalHasDescription").'> ?descriptions .} 
                ?uri  <'.RC::get("drupalRdfType").'> ?type . 
                FILTER regex(str(?type),"vocabs.acdh","i") .
                ?uri <'.RC::get("fedoraIdProp").'> ?identifiers .
            }
            ';
        
        $groupBy = ' GROUP BY ?uri ?title ?pid ORDER BY ASC( fn:lower-case(?title))';
        
        $queryStr = $prefix.$select.$where.$groupBy.$limitStr;
        
        try {
            $q = new SimpleQuery($queryStr);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            
            $fields = $res->getFields(); 
            $result = $this->oeawFunctions->createSparqlResult($res, $fields);
            
            return $result;

        } catch (Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            return $result;
        }
    }
    
    /**
     * 
     * Create the children data for the detail views
     *  
     * @param string $uri -> resource URI
     * @param string $limit -> limit for pagination
     * @param string $offset -> offset for pagination
     * @param bool $count -> true = count the values
     * @param string $property -> the Prop which we need for get the data f.e. https://vocabs.acdh.oeaw.ac.at/schema#hasRelatedCollection
     * @return array
     */
    public function getSpecialChildrenViewData(string $uri, string $limit, string $offset, bool $count = false, array $property): array 
    {
        if($offset < 0) { $offset = 0; }
        $result = array();
        $select = "";
        $where = "";
        $limitStr = "";
        $queryStr = "";
        $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> ';
        
        if($count == false){
            $select = 'SELECT ?uri ?title (GROUP_CONCAT(DISTINCT ?type;separator=",") AS ?types) (GROUP_CONCAT(DISTINCT ?descriptions;separator=",") AS ?description) ';            
            $limitStr = ' LIMIT '.$limit.'
            OFFSET '.$offset.' ';
        }else {
            $select = 'SELECT (COUNT(?uri) as ?count) ';            
        }
        
        $where = '
            WHERE {
                <'.$uri.'> ?prop ?obj .
                FILTER( ?prop IN ( ';
        
        for ($x = 0; $x < count($property); $x++) {
            $where .='<'.$property[$x].'>';
            if($x +1 < count($property)){
                $where .= ', ';
            }
        } 
        
        $where .='  )) . '
                . '?uri <'.RC::get('fedoraIdProp').'> ?obj .    
                OPTIONAL { ?uri <'.RC::get("fedoraTitleProp").'> ?title .}
                OPTIONAL { ?uri <'.RC::get("drupalHasDescription").'> ?descriptions .}
                ?uri  <'.RC::get("drupalRdfType").'> ?type .
                FILTER regex(str(?type),"vocabs.acdh","i") .
            ';
        $groupBy = ' }  GROUP BY ?uri ?title ORDER BY ASC( fn:lower-case(?title))';
        
        echo $queryStr = $select.$where.$groupBy.$limitStr;
        
        try {
            $q = new SimpleQuery($queryStr);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            
            $fields = $res->getFields(); 
            $result = $this->oeawFunctions->createSparqlResult($res, $fields);
            
            return $result;

        } catch (Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            return $result;
        }
    }
    
    
    /**
     * 
     * This sparql will create an array with the ontology for the caching
     * 
     * @return array
     */
    public function getOntologyForCache(string $lang = "en"): array{
        
        $lang = strtolower($lang);
        $result = array();
        
        $select = 'prefix skos: <http://www.w3.org/2004/02/skos/core#> '
                . 'SELECT ?title ?id ?comment WHERE { ';
        $where = "?uri <".RC::get('drupalRdfType')."> ?type ."
                . "FILTER( ?type IN ( <http://www.w3.org/2002/07/owl#DatatypeProperty>, <http://www.w3.org/2002/07/owl#ObjectProperty>)) . "
                . "?uri <".RC::get('fedoraIdProp')."> ?id . "
                . " OPTIONAL {
                        ?uri skos:altLabel ?titleLang .   	
                        FILTER regex(lang(?titleLang),'".$lang."','i') .
                    }
                    OPTIONAL {
                        ?uri skos:altLabel ?titleEN .   	
                        FILTER regex(lang(?titleEN),'en','i') .
                    } 
                    BIND( IF( !bound(?titleLang) , ?titleEN, ?titleLang) as ?title ) .  
                    "
                 . "OPTIONAL {
                        ?uri rdfs:comment ?commentsLang .   	
                        FILTER regex(lang(?commentsLang),'".$lang."','i') .
                    }
                    OPTIONAL {
                        ?uri rdfs:comment ?commentsEN .   	
                        FILTER regex(lang(?commentsEN), 'en','i') .
                    }
                    BIND( IF( !bound(?commentsLang) , ?commentsEN, ?commentsLang) as ?comment ) .  "
                . "}";
       
        $queryStr = $select.$where;

        try {
            $q = new SimpleQuery($queryStr);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);

            $fields = $res->getFields(); 
            $result = $this->oeawFunctions->createSparqlResult($res, $fields);

            return $result;

         } catch (Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            return $result;
        }
        
        
        return $result;
    }
 
    

} 