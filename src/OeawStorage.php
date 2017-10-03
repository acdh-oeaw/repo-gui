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
use acdhOeaw\util\RepoConfig as RC;;


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
		        $order = "ASC(?title)";
		        break;
		    case "titledesc":
		        $order = "DESC(?title)";
		        break;
		    case "dateasc":
		        $order = "ASC(?creationdate)";
		        break;
		    case "datedesc":
		        $order = "DESC(?creationdate)";
		        break;
		    default:
		        $order = "ASC(?title)";
		}

        if($offset < 0) { $offset = 0; }
        
        $getResult = array();
        
        try {
            $q = new Query();
            $q->addParameter(new HasTriple('?uri', RC::titleProp(), '?title'));
            $q->addParameter((new HasValue(self::$sparqlPref["rdfType"], 'https://vocabs.acdh.oeaw.ac.at/#Collection' ))->setSubVar('?uri'));
            $q->addParameter(new HasTriple('?uri', \Drupal\oeaw\ConnData::$description, '?description'), true);
            $q->addParameter(new HasTriple('?uri', \Drupal\oeaw\ConnData::$contributor, '?contributor'), true);
            $q->addParameter(new HasTriple('?uri', \Drupal\oeaw\ConnData::$acdhHasCreatedDate, '?creationdate'), true);
            $q->addParameter(new HasTriple('?uri', RC::get('fedoraRelProp'), '?isPartOf'), true);
            $q->addParameter(new HasTriple('?uri', \Drupal\oeaw\ConnData::$imageThumbnail, '?image'), true);
            $q->addParameter(new HasTriple('?uri', \Drupal\oeaw\ConnData::$acdhImage, '?hasTitleImage'), true);
            $q->addParameter(new HasTriple('?uri', self::$sparqlPref["rdfType"], '?rdfType'));
            
            $q2 = new Query();
            $q2->addParameter(new HasTriple('?uri', RC::get('fedoraRelProp'), '?y'));
            $q2->setJoinClause('filter not exists');
            $q->addSubquery($q2);    
      
            if($count == false){
                $q->setSelect(array('?uri', '?title', '?description', '?contributor', '?creationdate', '?isPartOf', '?image', '?hasTitleImage', '(GROUP_CONCAT(DISTINCT ?rdfType;separator=",") AS ?rdfTypes)'));
                $q->setOrderBy(array($order));
                $q->setGroupBy(array('?uri', '?title', '?description', '?contributor', '?creationdate', '?isPartOf', '?image', '?hasTitleImage'));
                $q->setLimit($limit);
                $q->setOffset($offset); 
            }else {
                $q->setSelect(array('(COUNT(?uri) as ?count)'));
                
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
                $where .= "?uri <".\Drupal\oeaw\ConnData::$rdfsLabel."> ?title . ";
                $where .= "?uri <".\Drupal\oeaw\ConnData::$rdfsComment."> ?comment . ";
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
            $q->addParameter(new HasTriple($uri, \Drupal\oeaw\ConnData::$contributor, '?contributor'), true);
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
                        
            $foafName = self::$sparqlPref["foafName"];
            $rdfsLabel = self::$sparqlPref["rdfsLabel"];            

            $q = new Query();            
            $q->addParameter((new HasValue($property, $value))->setSubVar('?uri'));
            
            if($count == false){
                //Query parameters for the properties we want to get, true stands for optional
                $q->addParameter((new HasTriple('?uri', RC::titleProp(), '?title')), true);
                $q->addParameter(new HasTriple('?uri', \Drupal\oeaw\ConnData::$author, '?author'), true);           
                $q->addParameter(new HasTriple('?uri', \Drupal\oeaw\ConnData::$description, '?description'), true);
                $q->addParameter(new HasTriple('?uri', $rdfsLabel, '?label'), true);
                $q->addParameter(new HasTriple('?uri', $foafName, '?name'), true);         
                $q->addParameter(new HasTriple('?uri', \Drupal\oeaw\ConnData::$contributor, '?contributor'), true);            
                $q->addParameter(new HasTriple('?uri', \Drupal\oeaw\ConnData::$creationdate, '?creationdate'), true);
                $q->addParameter(new HasTriple('?uri', RC::get('fedoraRelProp'), '?isPartOf'), true);
                $q->addParameter(new HasTriple('?uri', \Drupal\oeaw\ConnData::$rdfType, '?rdfType'), true);
                $q->addParameter(new HasTriple('?uri', \Drupal\oeaw\ConnData::$hasFirstName, '?firstName'), true);
                $q->addParameter(new HasTriple('?uri', \Drupal\oeaw\ConnData::$hasLastName, '?lastName'), true);
                //Select and aggregate multiple sets of values into a comma seperated string
                $q->setSelect(array('?uri', '?title', '?description', '?label', '?name', '?creationdate', '?isPartOf', '?firstName', '?lastName', '(GROUP_CONCAT(DISTINCT ?author;separator=",") AS ?authors)', '(GROUP_CONCAT(DISTINCT ?contributor;separator=",") AS ?contributors)', '(GROUP_CONCAT(DISTINCT ?rdfType;separator=",") AS ?rdfTypes)'));
                $q->setGroupBy(array('?uri', '?title', '?description', '?label', '?name', '?creationdate', '?isPartOf', '?firstName', '?lastName'));
                //If it's a person order by their name, if not by resource title
                if ($value == \Drupal\oeaw\ConnData::$person) {
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
            $rdfType = self::$sparqlPref["rdfType"];
            $rdfsLabel = self::$sparqlPref["rdfsLabel"];
            $owlClass = self::$sparqlPref["owlClass"];
           
            $q = new Query();
            $q->addParameter((new HasValue($rdfType, $owlClass))->setSubVar('?uri'));
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
            $rdfType = self::$sparqlPref["rdfType"];
            $dcID = RC::idProp();            
            $owlClass = self::$sparqlPref["owlClass"];
            
            $q = new Query();            
            $q->setSelect(array('?id', '?collection'));            
            
            $q->addParameter((new HasValue($rdfType, $owlClass))->setSubVar('?class'));
            $q->addParameter(new HasTriple('?class', $dcID, '?id'));
            
            $q2 = new Query();
            $q2->setJoinClause('optional');            
            
            $q->addSubquery($q2);
            
            /*
            
            $q3 = new Query();            
            $q3->addParameter((new HasValue($rdfsSubClass, 'https://vocabs.acdh.oeaw.ac.at/#Collection'))->setSubVar('?class'));            
            $q2->addSubquery($q3);
            
            $q4 = new Query();
            $q4->setJoinClause('union');
            $q4->addParameter((new HasValue($rdfsSubClass, 'https://vocabs.acdh.oeaw.ac.at/#DigitalCollection'))->setSubVar('?class'));
            $q2->addSubquery($q4);
            
            $q5 = new Query();
            $q5->setJoinClause('union');
            $q5->addParameter((new HasValue($dcID, 'https://vocabs.acdh.oeaw.ac.at/#Collection'))->setSubVar('?class'));
            $q2->addSubquery($q5);
            
            
            $q6 = new Query();
            $q6->setJoinClause('union');
            $q6->addParameter((new HasValue($dcID, 'https://vocabs.acdh.oeaw.ac.at/#DigitalCollection'))->setSubVar('?class'));
            $q2->addSubquery($q6);
            //VALUES ?collection {true}
            $q2->addParameter((new HasValue('?collection' '{true}'))->setSubVar('VALUES'));;
            $query = $q->getQuery();
            
             * 
             * 
        */

            $query=
                self::$prefixes . ' 
                    SELECT 
                        ?id ?collection 
                    WHERE {
                            ?class a owl:Class .
                            ?class dct:identifier ?id .
                            OPTIONAL {
                              {
                                {?class rdfs:subClassOf* <https://vocabs.acdh.oeaw.ac.at/#Collection>}
                                UNION
                                {?class rdfs:subClassOf* <https://vocabs.acdh.oeaw.ac.at/#DigitalCollection>}
                                UNION
                                {?class dct:identifier <https://vocabs.acdh.oeaw.ac.at/#Collection>}
                                UNION
                                {?class dct:identifier <https://vocabs.acdh.oeaw.ac.at/#DigitalCollection>}
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
         
            //HasTriple('?class', array('(', 'rdfs:subClassOf', '/', '^', 'dct:identifier', ')', '*'), 'acdh:DigitalCollection')

/*
            $query = self::$prefixes . ' 
                    SELECT 
                        ?id ?label 
                    WHERE {
                        {
                            { <' . $classURI . '> dct:identifier / ^rdfs:domain ?property . }
                            UNION
                            { <' . $classURI . '> rdfs:subClassOf / (^dct:identifier / rdfs:subClassOf)* / ^rdfs:domain ?property . }
                        }
                        ?property dct:identifier ?id
                        OPTIONAL {
                            ?property dct:label ?label .
                        }
                    } Order BY (?id)           
                ';*/
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
            $q->addParameter((new HasValue(\Drupal\oeaw\ConnData::$rdfType, \Drupal\oeaw\ConnData::$image))->setSubVar('?uri'));
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
        $rdfType = self::$sparqlPref["rdfType"];
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
        $rdfType = self::$sparqlPref["rdfType"];
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
            $q5->addParameter((new HasTriple('?res', $rdfType, '?type')));
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
     * Create the Sparql Query for the Concept skos:narrower view
     * 
     * @param string $uri - the main resource fedora uri
     * @param string $limit - limit for paging
     * @param string $offset - offset for paging
     * @param bool $count - count query or normal
     * @return array
     */
    public function getConceptViewData(string $uri, string $limit, string $offset, bool $count = false): array {
        
        if($offset < 0) { $offset = 0; }
        $result = array();
        $select = "";
        $where = "";
        $limitStr = "";
        $queryStr = "";
        $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> ';
        
        if($count == false){
            $select = 'SELECT ?uri ?title ?description ?identifier (GROUP_CONCAT(DISTINCT ?type;separator=",") AS ?types) ';
            $limitStr = ' LIMIT '.$limit.'
            OFFSET '.$offset.' ';
        }else {
            $select = 'SELECT (COUNT(?uri) as ?count) ';
        }
        
        $where = '
            WHERE {
                <'.$uri.'> <'.\Drupal\oeaw\ConnData::$skosNarrower.'> ?identifier .
                ?uri <'.RC::get("fedoraIdProp").'> ?identifier .                
                ?uri <'.RC::get("fedoraTitleProp").'> ?title .
                OPTIONAL { ?uri <'.\Drupal\oeaw\ConnData::$description.'> ?description .}
                ?uri  <'.\Drupal\oeaw\ConnData::$rdfType.'> ?type .
                FILTER regex(str(?type),"vocabs.acdh","i") .
            }
            ';
        
        $groupBy = ' GROUP BY ?title ?uri ?description ?identifier ORDER BY ASC( fn:lower-case(?title))';
        
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
     * Create the Sparql Query for the Person contributed view
     * 
     * @param string $uri - the main resource fedora uri
     * @param string $limit - limit for paging
     * @param string $offset - offset for paging
     * @param bool $count - count query or normal
     * @return array
     */
    public function getPersonViewData(string $uri, string $limit, string $offset, bool $count = false): array {
        
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
                ?uri <'.\Drupal\oeaw\ConnData::$contributor.'> ?obj
                OPTIONAL { ?uri <'.RC::get("fedoraTitleProp").'> ?title .}
                OPTIONAL { ?uri <'.\Drupal\oeaw\ConnData::$description.'> ?description .}
                ?uri  <'.\Drupal\oeaw\ConnData::$rdfType.'> ?type .
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
                OPTIONAL { ?uri <'.\Drupal\oeaw\ConnData::$description.'> ?description .}
                ?uri  <'.\Drupal\oeaw\ConnData::$rdfType.'> ?type .
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
     * Create the Inverse table data by URL
     * 
     * @param string $url
     * @return array
     */
    public function getInverseViewDataByURL(string $url): array{
        
        $result = array();
        
        $where .= '<'.$url.'> <'.RC::get("fedoraIdProp").'> ?obj .'
                . '?uri ?prop ?obj .'
                . 'MINUS { ?uri <'.RC::get("fedoraIdProp").'> ?obj  } . MINUS { ?uri <'.RC::get("fedoraRelProp").'> ?obj  } ';
                
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
            $q->addParameter((new HasValue('http://www.w3.org/1999/02/22-rdf-syntax-ns#type', $property))->setSubVar('?res'));
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
        $rdfType = self::$sparqlPref["rdfType"];
        $getResult = array();
        
        try {
            $q = new Query();
            $q->addParameter(new HasTriple('?uri', \Drupal\oeaw\ConnData::$hasDissService, '?dissId'));
            $q->addParameter(new HasTriple('?dissuri', RC::idProp(), '?dissId'));
            $q->addParameter(new HasTriple('?dissuri', \Drupal\oeaw\ConnData::$providesMime, '?mime'));
            
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
     * Get the acdh rdf types
     * 
     * @return array
     */
    public function getACDHTypes(bool $count = false) :array
    {        
        $rdfType = self::$sparqlPref["rdfType"];        
        $getResult = array();
        
        try {            
            if($count == true){
                $select = "SELECT  ?type (COUNT(?type) as ?typeCount) ";
            }else {
                $select = "SELECT  DISTINCT ?type ";
            }
            $queryStr = "
                WHERE {
                    ?uri <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> ?type .
                    FILTER (regex(str(?type), 'https://vocabs.acdh.oeaw.ac.at/#', 'i'))
                }
                GROUP BY ?type
                ORDER BY ?uri
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
        $rdfType = self::$sparqlPref["rdfType"];        
        $getResult = array();
        
        try {            
            $q = new Query();
            $q->addParameter(new HasTriple('?uri', $rdfType, '?type'));
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

} 