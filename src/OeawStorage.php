<?php

namespace Drupal\oeaw;

use Drupal\Core\Url;
use Drupal\oeaw\OeawFunctions;
use Drupal\oeaw\ConnData;
use Drupal\Core\Form\ConfigFormBase;

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
//use zozlak\util\Config;
use acdhOeaw\util\RepoConfig as RC;


class OeawStorage {

    private static $prefixes = 'PREFIX dct: <http://purl.org/dc/terms/> '
            . 'PREFIX ebucore: <http://www.ebu.ch/metadata/ontologies/ebucore/ebucore#> '
            . 'PREFIX premis: <http://www.loc.gov/premis/rdf/v1#> '
            . 'PREFIX acdh: <http://vocabs.acdh.oeaw.ac.at/#> '
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
        'owlClass' => 'http://www.w3.org/2002/07/owl#Class',
        'rdfsDomain' => 'http://www.w3.org/2000/01/rdf-schema#domain',
        'dctLabel' => 'http://purl.org/dc/terms/label',
        'owlOnProperty' => 'http://www.w3.org/2002/07/owl#onProperty',
        'owlCardinality' => 'http://www.w3.org/2002/07/owl#cardinality',
        'owlMinCardinality' => 'http://www.w3.org/2002/07/owl#minCardinality',
        'owlMaxCardinality' => 'http://www.w3.org/2002/07/owl#maxCardinality'        
    );
        
    
    private $OeawFunctions;    
    private $fedora;    
    
    public function __construct() {
        \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');
                
        $this->OeawFunctions = new OeawFunctions();
        $this->fedora = new Fedora();        
        
        //blazegraph bugfix. Add missing namespace
        $blazeGraphNamespaces = \EasyRdf\RdfNamespace::namespaces();
        $localNamespaces =  \Drupal\oeaw\ConnData::$prefixesToBlazegraph;
                
        foreach($localNamespaces as $key => $val){
            if(!array_key_exists($val, $blazeGraphNamespaces)){
                \EasyRdf\RdfNamespace::set($key, $val);
            }
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
    public function getRootFromDB(int $limit = 0, int $offset = 0, bool $count = false, string $order = "titleasc" ): array {

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

        if($offset < 0) { $offset = 0; }
        
        $getResult = array();
        
        try {
            $q = new Query();
            $q->addParameter(new HasTriple('?uri', RC::titleProp(), '?title'));
            $q->addParameter((new HasValue(RC::get("drupalRdfType"), RC::get('drupalCollection') ))->setSubVar('?uri'));
            $q->addParameter(new HasTriple('?uri', RC::get('drupalHasDescription'), '?description'), true);
            $q->addParameter(new HasTriple('?uri', RC::get('drupalHasContributor'), '?contributors'), true);
            $q->addParameter(new HasTriple('?uri', RC::get('drupalHasCreatedDate'), '?creationdate'), true);
            $q->addParameter(new HasTriple('?uri', RC::get('drupalHasAvailableDate'), '?availableDate'), true);
            $q->addParameter(new HasTriple('?uri', RC::get('drupalHasCreationStartDate'), '?hasCreationStartDate'), true);
            $q->addParameter(new HasTriple('?uri', RC::get('drupalHasCreationEndDate'), '?hasCreationEndDate'), true);
            $q->addParameter(new HasTriple('?uri', RC::get('fedoraRelProp'), '?isPartOf'), true);
            $q->addParameter(new HasTriple('?uri', RC::get('drupalHasTitleImage'), '?hasTitleImage'), true);
            if($count == false){
                $q->addParameter(new HasTriple('?uri', RC::get('drupalRdfType'), '?rdfType'));    
            }

            $q2 = new Query();
            $q2->addParameter(new HasTriple('?uri', RC::get('fedoraRelProp'), '?y'));
            $q2->setJoinClause('filter not exists');
            $q->addSubquery($q2);    
      
            if($count == false){
                $q->setSelect(array('?uri', '?title', '?description', '?availableDate', '?isPartOf', '?image', '?hasTitleImage', '?hasCreationStartDate', '?hasCreationEndDate', '(GROUP_CONCAT(DISTINCT ?rdfType;separator=",") AS ?rdfTypes)', '(GROUP_CONCAT(DISTINCT ?contributors;separator=",") AS ?contributor)'));
                $q->setOrderBy(array($order));
                $q->setGroupBy(array('?uri', '?title', '?description', '?availableDate', '?isPartOf', '?image', '?hasTitleImage', '?hasCreationStartDate', '?hasCreationEndDate'));
                $q->setLimit($limit);
                $q->setOffset($offset);
            }else {
                $q->setSelect(array('(COUNT(DISTINCT ?uri) as ?count)'));
                
                $q->setOrderBy(array('?uri'));
            }
            
            $query = $q->getQuery();

            $result = $this->fedora->runSparql($query);
            if(count($result) > 0){
                $fields = $result->getFields();             
                $getResult = $this->OeawFunctions->createSparqlResult($result, $fields);        
                return $getResult;
            }else {                
                return $getResult;
            }
            
        } catch (Exception $ex) {            
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;            
        }catch (\InvalidArgumentException $ex){            
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
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
    public function getTitleByIdentifier(string $string): array{
        $getResult = array();
        
        try {
            
            $q = new Query();
            $q->addParameter((new HasValue(RC::idProp(), $string ))->setSubVar('?uri'));
            $q->addParameter(new HasTriple('?uri', RC::titleProp(), '?title'));
            
            $query = $q->getQuery();
            $result = $this->fedora->runSparql($query);
            
            $fields = $result->getFields();             
            $getResult = $this->OeawFunctions->createSparqlResult($result, $fields);        
            return $getResult;
            
        } catch (Exception $ex) {            
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;            
        }catch (\InvalidArgumentException $ex){            
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        }
    }
    
    /**
     * Create the 
     * 
     * @param array $data
     * @return type
     */
    public function getPropDataToExpertTable(array $data){
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
                $result = $this->OeawFunctions->createSparqlResult($res, $fields);
            
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
    public function getResourceTitle(string $uri): array {
         $getResult = array();
        
        try {
            
            $q = new Query();            
            $q->addParameter(new HasTriple($uri, RC::titleProp(), '?title'), true);            
            $q->addParameter(new HasTriple($uri, RC::get('drupalHasContributor'), '?contributor'), true);
            $q->addParameter(new HasTriple($uri, \Drupal\oeaw\ConnData::$hasFirstName, '?firstName'), true);
            $q->addParameter(new HasTriple($uri, \Drupal\oeaw\ConnData::$hasLastName, '?lastName'), true);
            
            $query = $q->getQuery();
            $result = $this->fedora->runSparql($query);
            
            $fields = $result->getFields();             
            $getResult = $this->OeawFunctions->createSparqlResult($result, $fields);        
            return $getResult;
            
        } catch (Exception $ex) {            
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;            
        }catch (\InvalidArgumentException $ex){            
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        }
    }
    
    /**
     * 
     * Get all property for search
     * 
     * @return array
     */
    public function getAllPropertyForSearch():array {
        
        $getResult = array();
        
        try {
            
            $q = new Query();
            $q->addParameter(new HasTriple('?s', '?p', '?o'));    
            $q->setDistinct(true);            
            $q->setSelect(array('?p'));
        
            $query= $q->getQuery();
            
            $result = $this->fedora->runSparql($query);
            
            $fields = $result->getFields(); 

            $getResult = $this->OeawFunctions->createSparqlResult($result, $fields);

            return $getResult;                
            
        } catch (Exception $ex) {            
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
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
    public function getValueByUriProperty(string $uri, string $property): array{
        
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
            $getResult = $this->OeawFunctions->createSparqlResult($result, $fields);
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
    public function getPropertyValueByUri(string $uri, string $property): string{
        
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
            $getResult = $this->OeawFunctions->createSparqlResult($result, $fields);

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
            $property = $this->OeawFunctions->createUriFromPrefix($property);
            if($property === false){
                return drupal_set_message(t('Error in function: '.__FUNCTION__), 'error'); 
            }           
        }else if(filter_var($property, FILTER_VALIDATE_URL)){            
            $property = '<'. $property .'>';
        }
        
        if(!filter_var($value, FILTER_VALIDATE_URL)){
            $value = $this->OeawFunctions->createUriFromPrefix($value);
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
                $q->addParameter(new HasTriple('?uri', RC::get('drupalAuthor'), '?author'), true);           
                $q->addParameter(new HasTriple('?uri', RC::get('drupalHasDescription'), '?description'), true);
                $q->addParameter(new HasTriple('?uri', $rdfsLabel, '?label'), true);
                $q->addParameter(new HasTriple('?uri', RC::get('drupalHasContributor'), '?contributor'), true);            
                $q->addParameter(new HasTriple('?uri', RC::get('drupalHasCreatedDate'), '?creationdate'), true);
                $q->addParameter(new HasTriple('?uri', RC::get('fedoraRelProp'), '?isPartOf'), true);
                $q->addParameter(new HasTriple('?uri', RC::get('drupalRdfType'), '?rdfType'), true);
                $q->addParameter(new HasTriple('?uri', \Drupal\oeaw\ConnData::$hasFirstName, '?firstName'), true);
                $q->addParameter(new HasTriple('?uri', \Drupal\oeaw\ConnData::$hasLastName, '?lastName'), true);
                //Select and aggregate multiple sets of values into a comma seperated string
                $q->setSelect(array('?uri', '?title', '?description', '?label', '?creationdate', '?isPartOf', '?firstName', '?lastName', '(GROUP_CONCAT(DISTINCT ?author;separator=",") AS ?authors)', '(GROUP_CONCAT(DISTINCT ?contributor;separator=",") AS ?contributors)', '(GROUP_CONCAT(DISTINCT ?rdfType;separator=",") AS ?rdfTypes)'));
                $q->setGroupBy(array('?uri', '?title', '?description', '?label', '?creationdate', '?isPartOf', '?firstName', '?lastName'));
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
            $getResult = $this->OeawFunctions->createSparqlResult($result, $fields);

            return $getResult;                
        
        } catch (\Exception $ex) {            
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        } 
        catch (\Symfony\Component\Routing\Exception\InvalidParameterException $ex){
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
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
            
            $rdfsLabel = self::$sparqlPref["rdfsLabel"];
            $owlClass = self::$sparqlPref["owlClass"];
           
            $q = new Query();
            $q->addParameter((new HasValue(RC::get('drupalRdfType'), $owlClass))->setSubVar('?uri'));
            $q->addParameter(new HasTriple('?uri', $rdfsLabel, '?title'));
            $q->setSelect(array('?uri', '?title'));
            $q->setOrderBy(array('UCASE(str(?title))'));
            $query = $q->getQuery();
            
            $result = $this->fedora->runSparql($query);                        
            $fields = $result->getFields(); 
            $getResult = $this->OeawFunctions->createSparqlResult($result, $fields);

            return $getResult; 
            
        } catch (Exception $ex) {            
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
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
            $getResult = $this->OeawFunctions->createSparqlResult($result, $fields);

            return $getResult;
            
        } catch (Exception $ex) {            
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
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
    public function getClassMeta(string $classURI): array{
       
        if (empty($classURI)) {
            return drupal_set_message(t('Empty values! -->'.__FUNCTION__), 'error');
        }
        
        $getResult = array();
        
        try {            
                        
            $idProp = RC::idProp();
            $rdfsSubClass = self::$sparqlPref['rdfsSubClass'];
            $rdfsDomain = self::$sparqlPref["rdfsDomain"];
            $owlCardinality = self::$sparqlPref["owlCardinality"];
            $owlMinCardinality = self::$sparqlPref["owlMinCardinality"];
            $owlMaxCardinality = self::$sparqlPref["owlMaxCardinality"];
            
            $q = new Query();            
            $q->setSelect(array('?id', '?cardinality', '?minCardinality', '?maxCardinality' ));            
            
            $q3 = new Query();
            $q3->addParameter(new HasTriple($classURI, array( $idProp, '/', '^', $rdfsDomain, ), '?property'));
            
            $q2 = new Query();
            
            $q4 = new Query();
            $q4->addParameter(new HasTriple($classURI, array( $rdfsSubClass, '/', '(', '^', $idProp, '/', $rdfsSubClass,')', '*', '/', '^', $rdfsDomain, ), '?property'));
            $q4->setJoinClause('union');
            
            $q5 = new Query();
            $q5->addParameter(new HasTriple('?property', $idProp, '?id'));
            
            
            $q6_1 = new Query();
            $q6_1->addParameter(new HasTriple('?property', $owlCardinality, '?cardinality'));
            $q6_1->setJoinClause('optional');
            
            $q6_2 = new Query();
            $q6_2->addParameter(new HasTriple('?property', $owlMinCardinality, '?minCardinality'));
            $q6_2->setJoinClause('optional');
            
            $q6_3 = new Query();
            $q6_3->addParameter(new HasTriple('?property', $owlMaxCardinality, '?maxCardinality'));
            $q6_3->setJoinClause('optional');
            
            
            $q2->addSubquery($q3);
            $q2->addSubquery($q4);
            $q->addSubquery($q2);
            $q->addSubquery($q5);            
            $q->addSubquery($q6_1);
            $q->addSubquery($q6_2);
            $q->addSubquery($q6_3);
            
            $q->setOrderBy(array('?id'));
            $query = $q->getQuery();
      
            $result = $this->fedora->runSparql($query);
            $fields = $result->getFields(); 
            $getResult = $this->OeawFunctions->createSparqlResult($result, $fields);
            
            return $getResult;    
            
            
        } catch (Exception $ex) {            
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
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
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return $return;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return $return;
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
            return drupal_set_message(t('Empty values! -->'.__FUNCTION__), 'error');
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
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        }  
    }
    
    /*
     * 
     * Search function
     * 
     * @param string $value -> the property value 
     * @param string $property -> the property
     * 
     * @return array
     * 
     */    
    public function searchForData(string $value, string $property): array{
        
        if (empty($value) || empty($property)) {
            return drupal_set_message(t('Empty values! -->'.__FUNCTION__), 'error');
        }
                
        
        $rdfsLabel = self::$sparqlPref["rdfsLabel"];
        
        $foafThumbnail = self::$sparqlPref["foafThumbnail"];
        $foafImage = self::$sparqlPref["foafImage"];
        $getResult = array();
        
        //we need to extend the Filter options in the DB class, to we can use the
        // Filter =  value
        try {
           
            $q = new Query();            
            $q->setSelect(array('?res', '?property', '?value', '?title', '?label', '?thumb', '?image'));
            $q->setDistinct(true);     
            //$q->addParameter(new HasTriple('?uri', $property, '?value'));            
            $q->addParameter(new MatchesRegEx($property, $value), 'i');
            
            $q2 = new Query();
            $q2->addParameter((new HasTriple('?res', RC::titleProp(), '?title')));
            $q2->setJoinClause('optional');
            $q->addSubquery($q2);

            $q3 = new Query();
            $q3->addParameter((new HasTriple('?res', $rdfsLabel, '?label')));
            $q3->setJoinClause('optional');
            $q->addSubquery($q3);
            
            $q4 = new Query();
            $q4->addParameter((new HasTriple('?res', $foafThumbnail, '?thumb')));
            $q4->setJoinClause('optional');
            $q->addSubquery($q4);
            
            $q5 = new Query();
            $q5->addParameter((new HasTriple('?res', RC::get('drupalRdfType'), '?type')));
            $q5->setJoinClause('optional');
            $q->addSubquery($q5);
            
            $q6 = new Query();
            $q6->addParameter((new HasValue('?image', $foafImage ))->setSubVar('?res'));
            //$q6->addParameter((new HasTriple('?res', '?image', $foafImage)));
            $q6->setJoinClause('optional');
            $q->addSubquery($q6);
            
            $query = $q->getQuery();
         
            $result = $this->fedora->runSparql($query);
           
            $fields = $result->getFields(); 
            $getResult = $this->OeawFunctions->createSparqlResult($result, $fields);
           
            return $getResult;

        } catch (Exception $ex) {            
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        }        
    }
    
    /**
     * 
     * Create the Sparql Query for the Person contributed view
     * 
     * @param string $uri - the main resource fedora uri
     * @param string $limit - limit for paging
     * @param string $offset - offset for paging
     * @param bool $count - count query or normal
     * @return array
     */
    public function getSpecialDetailViewData(string $uri, string $limit, string $offset, bool $count = false, string $property): array {
        
        if($offset < 0) { $offset = 0; }
        $result = array();
        $select = "";
        $where = "";
        $limitStr = "";
        $queryStr = "";
        $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> ';
        if($count == false){
            $select = 'SELECT ?uri ?title ?description (GROUP_CONCAT(DISTINCT ?type;separator=",") AS ?types) ';
            $limitStr = ' LIMIT '.$limit.'
            OFFSET '.$offset.' ';
        }else {
            $select = 'SELECT (COUNT(?uri) as ?count) ';
        }
        
        $where = '
            WHERE {
                <'.$uri.'> <'.RC::get("fedoraIdProp").'> ?obj .
                ?uri <'.$property.'> ?obj
                OPTIONAL { ?uri <'.RC::get("fedoraTitleProp").'> ?title .}
                OPTIONAL { ?uri <'.RC::get("drupalHasDescription").'> ?description .}
                ?uri  <'.RC::get("drupalRdfType").'> ?type .
                FILTER regex(str(?type),"vocabs.acdh","i") .
            }
            ';
        
        $groupBy = ' GROUP BY ?uri ?title ?description ORDER BY ASC( fn:lower-case(?title))';
        
        $queryStr = $select.$where.$groupBy.$limitStr;
        
        try {
            $q = new SimpleQuery($queryStr);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            
            $fields = $res->getFields(); 
            $result = $this->OeawFunctions->createSparqlResult($res, $fields);
            
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
            $select = 'SELECT ?uri ?title ?description (GROUP_CONCAT(DISTINCT ?type;separator=",") AS ?types) ';            
            $limitStr = ' LIMIT '.$limit.'
            OFFSET '.$offset.' ';
        }else {
            $select = 'SELECT (COUNT(?uri) as ?count) ';            
        }
        
        $where = '
            WHERE {
                ?uri <'.RC::get("fedoraTitleProp").'> ?title .
                OPTIONAL { ?uri <'.RC::get("drupalHasDescription").'> ?description .}
                ?uri  <'.RC::get("drupalRdfType").'> ?type .
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
        $groupBy = ' }  GROUP BY ?uri ?title ?description ORDER BY ASC( fn:lower-case(?title))';
        
        $queryStr = $select.$where.$groupBy.$limitStr;
        
        try {
            $q = new SimpleQuery($queryStr);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            
            $fields = $res->getFields(); 
            $result = $this->OeawFunctions->createSparqlResult($res, $fields);
            
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
            $res = $this->OeawFunctions->createSparqlResult($res, $fields);
            
            $i = 0;
            foreach($res as $r){
                foreach($r as $k => $v){                    
                    $result[$i][$k] = $v;
                    if($k == "uri"){
                        $title = $this->OeawFunctions->getTitleByUri($v);
                        $result[$i]["title"] = $title;
                        $result[$i]["insideUri"] = base64_encode($v);
                    }
                    if($k == "prop"){                        
                        $shortcut = $this->OeawFunctions->createPrefixesFromString($v);
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
            $res = $this->OeawFunctions->createSparqlResult($res, $fields);
            
            $i = 0;
            foreach($res as $r){
                foreach($r as $k => $v){                    
                    $result[$i][$k] = $v;
                    if($k == "uri"){
                        $title = $this->OeawFunctions->getTitleByUri($v);
                        $result[$i]["title"] = $title;
                        $result[$i]["insideUri"] = base64_encode($v);
                    }
                    if($k == "prop"){                        
                        $shortcut = $this->OeawFunctions->createPrefixesFromString($v);
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
            $res = $this->OeawFunctions->createSparqlResult($res, $fields);
            
            $i = 0;
            foreach($res as $r){
                foreach($r as $k => $v){                    
                    $result[$i][$k] = $v;
                    if($k == "uri"){
                        $title = $this->OeawFunctions->getTitleByUri($v);
                        $result[$i]["title"] = $title;
                        $result[$i]["insideUri"] = base64_encode($v);
                    }
                    if($k == "prop"){                        
                        $shortcut = $this->OeawFunctions->createPrefixesFromString($v);
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
    public function runUserSparql(string $string): array{
        
        $result = array();
        
        try {
            $q = new SimpleQuery($string);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            
            $fields = $res->getFields();
            $result = $this->OeawFunctions->createSparqlResult($res, $fields);
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
            $getResult = $this->OeawFunctions->createSparqlResult($result, $fields);
           
            return $getResult;

        } catch (Exception $ex) {            
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        }        
    }
    
    public function getMimeTypes(){
        
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
            $getResult = $this->OeawFunctions->createSparqlResult($result, $fields);
            
            return $getResult;

        } catch (Exception $ex) {            
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
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
            $getResult = $this->OeawFunctions->createSparqlResult($result, $fields);
            
            return $getResult;

        } catch (Exception $ex) {            
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        }  
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
            $getResult = $this->OeawFunctions->createSparqlResult($result, $fields);

            return $getResult;

        } catch (Exception $ex) {            
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        }  
    }
    
    
    
    /**
     * 
     * Get the titles for the detail view property values
     * 
     * @param array $data : Array with the identifiers
     * @return array : results array with the identifiers and the titles
     * 
    */
    public function getTitlyByIdentifierArray(array $data): array{
        
        $result = array();
        if(count($data) > 0){
            $where = "";
            $i = 0;
            
            foreach ($data as $key => $value){
                $where .= " { ";
                $where .= "?uri <".RC::get('fedoraIdProp')."> <".$value."> . ";
                $where .= "?uri <".RC::get('fedoraIdProp')."> ?identifier . ";
                $where .= "?uri <".RC::titleProp()."> ?title . ";
                $where .= " } ";
                
                if($i != count($data) - 1){
                    $where .= " UNION ";
                }
                $i++;
            }   
            $select = 'SELECT DISTINCT ?title ?identifier ?uri WHERE { ';
            $queryStr = $select.$where." } ";
            
            try {
                $q = new SimpleQuery($queryStr);
                $query = $q->getQuery();
                $res = $this->fedora->runSparql($query);
            
                $fields = $res->getFields(); 
                $result = $this->OeawFunctions->createSparqlResult($res, $fields);
             
                return $result;
 
             } catch (Exception $ex) {
                return $result;
            } catch (\GuzzleHttp\Exception\ClientException $ex){
                return $result;
            }
        }
        
        return $result;
    }
 
    

} 