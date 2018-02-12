<?php

/**
  @file
  Contains \Drupal\oeaw\Controller\FrontendController.
 */

namespace Drupal\oeaw\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Archiver\Zip;
use Drupal\oeaw\OeawStorage;
use Drupal\oeaw\OeawFunctions;
use Drupal\oeaw\OeawCustomSparql;
use Drupal\oeaw\PropertyTableCache;
//ajax
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ChangedCommand;
use Drupal\Core\Ajax\CssCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;

use acdhOeaw\util\RepoConfig as RC;
use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\FedoraResource;
use EasyRdf\Graph;
use EasyRdf\Resource;

use TCPDF;

//autocomplete
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

use GuzzleHttp\Client;


class FrontendController extends ControllerBase  {
    
    private $OeawStorage;
    private $OeawFunctions;
    private $OeawCustomSparql;
    private $PropertyTableCache;
    private $uriFor3DObj;
    
    
    
    public function __construct() {
        $this->OeawStorage = new OeawStorage();
        $this->OeawFunctions = new OeawFunctions();
        $this->OeawCustomSparql = new OeawCustomSparql();
        $this->PropertyTableCache = new PropertyTableCache();
        \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');
    }

    /**
     * 
     * The root Resources list     
     *
     * @param int $limit Amount of resources to get
     * @param int $page nth Page for pagination
     * @param string $order Order resources by, usage: ASC/DESC(?property)
     *
     * @return array
     */
    public function roots_list(string $limit = "10", string $page = "1", string $order = "datedesc" ): array {
        
        drupal_get_messages('error', TRUE);
        // get the root resources
        // sparql result fields - uri, title
        $result = array();
        $datatable = array();
        $res = array();
        $decodeUrl = "";
        $errorMSG = array();
        
        $limit = (int)$limit;
        $page = (int)$page;
        $page = $page-1;
        
        //count all root resource for the pagination
        $countRes = $this->OeawStorage->getRootFromDB(0,0,true);
        $countRes = $countRes[0]["count"];
        if($countRes == 0){
            $errorMSG = drupal_set_message(t('You have no Root resources!'), 'error', FALSE);
        }
        $search = array();
        
        //create data for the pagination
        $pageData = $this->OeawFunctions->createPaginationData($limit, $page, $countRes);
		$pagination = "";
        if ($pageData['totalPages'] > 1) {
            $pagination =  $this->OeawFunctions->createPaginationHTML($page, $pageData['page'], $pageData['totalPages'], $limit);
        }

        //Define offset for pagination
        if ($page > 0) {
            $offsetRoot = $page * $limit;
        } else {
            $offsetRoot = 0;
        }

        $result = $this->OeawStorage->getRootFromDB($limit, $offsetRoot, false, $order);
       
        $uid = \Drupal::currentUser()->id();

        if(count($result) > 0){
            $i = 0;
            foreach($result as $value){
                $res[$i]["title"] = $value['title'];
                $res[$i]["resUri"] = base64_encode($value['uri']);
                
                if(isset($value["description"]) && !empty($value["description"]) ){
                    $res[$i]["description"] = $value["description"];
                }                
                if(count($value["rdfTypes"]) > 0){
                    $types = explode(",", $value["rdfTypes"]);
                    foreach($types as $t){
                        if (strpos($t, 'vocabs.acdh.oeaw.ac.at') !== false) {
                            $res[$i]["rdfType"][] = str_replace(RC::get('fedoraVocabsNamespace'), '', $t);
                        }
                    }
                }
                
                if( isset($value['availableDate']) && !empty($value['availableDate']) ){
                    $time = strtotime($value['availableDate']);
                    $newTime = date('Y-m-d', $time);
                    $res[$i]["availableDate"] = $newTime;
                }
                
                if( isset($value['image']) && !empty($value['image']) ){
                    $res[$i]["image"] = $value['image'];
                }
                if( isset($value['hasTitleImage']) && !empty($value['hasTitleImage']) ){
                    $imageUrl = $this->OeawStorage->getImageByIdentifier($value['hasTitleImage']);
                    if($imageUrl){
                        $res[$i]["image"] = $imageUrl;
                    }
                }
                $i++;
            }
            $decodeUrl = "";            
        } else {
            $errorMSG = drupal_set_message(t('Problem during the root listing'), 'error', FALSE);
        }
        
        //create the datatable values and pass the twig template name what we want to use
        $datatable = array(
            '#userid' => $uid,
            '#errorMSG' => $errorMSG,
            '#attached' => [
                'library' => [
                'oeaw/oeaw-styles', 
                ]
            ]
        );
        
        if(isset($res) && $res !== null && !empty($res)){            
            $header = array_keys($res[0]);
            $datatable['#theme'] = 'oeaw_complex_search_res';
            $datatable['#result'] = $res;
            $datatable['#search'] = $search;
            $datatable['#header'] = $header;
            $datatable['#pagination'] = $pagination;
            //$datatable['#searchedValues'] = $i . ' top-level elements have been found.';
            $datatable['#totalResultAmount'] = $countRes;
            if (empty($pageData['page']) OR $pageData['page'] == 0) {
                $datatable['#currentPage'] = 1;
            } else {
                $datatable['#currentPage'] = $pageData['page'] + 1;
            }
            if (empty($pageData) OR $pageData['totalPages'] == 0) {
                $datatable['#totalPages'] = 1;
            } else {
                $datatable['#totalPages'] = $pageData['totalPages'];
            }

        }        

        return $datatable;
    }
    
    /**
     * 
     * The autocomplete function to the edit and new form
     * 
     * @param \Drupal\oeaw\Controller\request $request
     * @param string $prop1
     * @param string $fieldName
     * @return JsonResponse
    */
    public function autocomplete(request $request, string $prop1, string $fieldName): JsonResponse {
        
        $matches = array();
        $string = $request->query->get('q');
        $matchClass = [];
        //check the user entered char's
        if(strlen($string) < 3) { return new JsonResponse(array()); }
        
        //f.e.: depositor
        $propUri = base64_decode(strtr($prop1, '-_,', '+/='));

        if(empty($propUri)){ return new JsonResponse(array()); }
        
        $fedora = new Fedora(); 
        //get the property resources
        $rangeRes = null;
        
        try {
            //get the resource uri based on the propertURI
            //f.e: http://purl.org/dc/terms/contributor and the res uri will be a fedora uri
            $prop = $fedora->getResourceById($propUri);            
            //get the property metadata
            $propMeta = $prop->getMetadata();
            // check the range property in the res metadata, we will use this in our next query
            $rangeRes = $propMeta->getResource('http://www.w3.org/2000/01/rdf-schema#range');
        }  catch (\RuntimeException $e){
            return new JsonResponse(array());
        }

        if($rangeRes === null){
            return new JsonResponse(array()); // range property is missing - no autocompletion
        }
        
        $matchClass = $this->OeawStorage->checkValueToAutocomplete($string, $rangeRes->getUri());
        
        // if we want additional properties to be searched, we should add them here:
        $match = array(
            'title'  => $fedora->getResourcesByPropertyRegEx('http://purl.org/dc/elements/1.1/title', $string),
            'name'   => $fedora->getResourcesByPropertyRegEx('http://xmlns.com/foaf/0.1/name', $string),
            'acdhId' => $fedora->getResourcesByPropertyRegEx(RC::get('fedoraIdProp'), $string),
        );
        
        $matchValue = array();

        if(count($matchClass) > 0){
            foreach ($matchClass as $i) {
                $matchValue[] = $i;
            }
        }else{
            return new JsonResponse(array()); 
        }

        foreach ($match as $i) {
            foreach ($i as $j) {
                $matchValue[]['res'] = $j->getUri();
            }
        }
        
        $mv = $this->OeawFunctions->arrUniqueToMultiArr($matchValue, "res");
        
        foreach ($mv as $i) {
            
            $acdhId = $fedora->getResourceByUri($i);
            $meta = $acdhId->getMetadata();
            
            $label = empty($meta->label()) ? $acdhId : $meta->label();
            //because of the special characters we need to convert it
            $label = htmlentities($label, ENT_QUOTES, "UTF-8");
                
            $matches[] = ['value' => $i , 'label' => $label];

            if(count($matches) >= 10){
                 break;
            }
        }
        
        $response = new JsonResponse($matches);
        $response->setCharset('utf-8');
        $response->headers->set('charset', 'utf-8');
        $response->headers->set('Content-Type', 'application/json');
        
        return $response;
    }
    
    public function oeaw_error_page(string $errorMSG){
        if (empty($errorMSG)) {
           return drupal_set_message(t('The $errorMSG is missing!'), 'error');
        }
        $errorMSG = base64_decode($errorMSG);
        
        $datatable = array(
            '#theme' => 'oeaw_errorPage',
            '#errorMSG' => $errorMSG,            
            '#attached' => [
                'library' => [
                'oeaw/oeaw-styles', 
                ]
            ]
        );
        
        return $datatable;
    }
    
    
    
    /**
     * 
     * The acdh:query display page with the user defined sparql query
     * 
     * @param string $uri
     * @return array
     */
    public function oeaw_query(string $uri){
        
        if (empty($uri)) {
           return drupal_set_message(t('Resource does not exist!'), 'error');
        }
        
        $uri = base64_decode($uri);
        $data = array();
        $userSparql = array();
        $errorMSG = "";
        $header = array();
        
        $data = $this->OeawStorage->getValueByUriProperty($uri, \Drupal\oeaw\ConnData::$acdhQuery);
        
        if(isset($data)){
            $userSparql = $this->OeawStorage->runUserSparql($data[0]['value']);
            
            if(count($userSparql) > 0){
                $header = $this->OeawFunctions->getKeysFromMultiArray($userSparql);
            }
        }
        
        if(count($userSparql) == 0){
            $errorMSG = "Sparql query has no result";
        }

        $uid = \Drupal::currentUser()->id();
        // decode the uri hash
        
        $datatable = array(
            '#theme' => 'oeaw_query',
            '#result' => $userSparql,
            '#header' => $header,
            '#userid' => $uid,
            '#errorMSG' => $errorMSG,
            '#attached' => [
                'library' => [
                'oeaw/oeaw-styles', 
                ]
            ]
        );
        
        return $datatable;
    }
    
    public function oeaw_new_success(string $uri){
        
        if (empty($uri)) {
           return drupal_set_message(t('Resource does not exist!'), 'error');
        }
        $uid = \Drupal::currentUser()->id();
        // decode the uri hash
        /*$uri = $this->OeawFunctions->createDetailsUrl($uri, 'decode');*/
        $uri = base64_decode($uri);
        
        $datatable = array(
            '#theme' => 'oeaw_success_resource',
            '#result' => $uri,
            '#userid' => $uid,
            '#attached' => [
                'library' => [
                'oeaw/oeaw-styles', 
                ]
            ]
        );
        
        return $datatable;
    }
    
    
    public function oeaw_form_success(string $url){
        
        if (empty($url)) {
           return drupal_set_message(t('The $url is missing!'), 'error');
        }
        $uid = \Drupal::currentUser()->id();
        // decode the uri hash
        
        //$url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]/modules/oeaw/src/pdftmp".$url.'.pdf';
        $url = '/sites/default/files/'.$url.'/'.$url.'.pdf';
        $datatable = array(
            '#theme' => 'oeaw_form_resource',
            '#result' => $url,
            '#userid' => $uid,
            '#attached' => [
                'library' => [
                'oeaw/oeaw-styles', 
                ]
            ]
        );
        
        return $datatable;
    }
    
    /**
     * 
     * The detail view of the Resource with the existing children resources
     * 
     * @param string $uri
     * @param Request $request
     * @param string $limit
     * @param string $page
     * @return array
     */
    public function oeaw_detail(string $uri, Request $request, string $limit = "10", string $page = "1"): array {
        
        drupal_get_messages('error', TRUE);

        //Deduct 1 from the page since the backend works with 0 and the frontend 1 for the initial page
        if ($page > 0) {
            $page = $page-1;
        }

        if (empty($uri)) {
            $msg = base64_encode("Resource does not exist");
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;            
        }
        
        if($limit == "0"){ $limit = "10"; }
        $uri = base64_decode($uri);        
        $hasBinary = "";  
        $inverseData = array();
        
        $childResult = array();
        $rules = array();
        $ACL = array();
        $childrenData = array();
        
        
        $fedora = $this->OeawFunctions->initFedora();
        $uid = \Drupal::currentUser()->id();
       
        $fedoraRes = array();
        
        try{
            $fedoraRes = $fedora->getResourceByUri($uri);
            $rootMeta = $fedoraRes->getMetadata();
        } catch (\acdhOeaw\fedora\exceptions\NotFound $ex){
            $msg = base64_encode("Resource does not exist!");
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        }
        //get the actual resource rules
        $rules = $this->OeawFunctions->getRules($uri, $fedoraRes);
        
        if(count($rules) == 0){
            $msg = base64_encode("The Resource Rules are not reachable!");
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        }
        
        if(count($rootMeta) > 0){
            $results = array();
            
            //get the root table data
            $results = $this->OeawFunctions->createDetailViewTable($rootMeta);
            
            if(count($results) == 0){
                $msg = base64_encode("The resource has no metadata!");
                $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
                $response->send();
                return;
            }
            
            $results['ACL'] = $this->OeawFunctions->checkRules($rules);
            
            //check the acdh:hasIdentifier data to the child view
            $identifiers = array();
            if(count($results['table']['acdh:hasIdentifier']) > 0){
                foreach($results['table']['acdh:hasIdentifier'] as $i){
                    $identifiers[] = $i['uri'];
                }
            }
            
            if(count($identifiers) > 0){
                //set up the necessary properties for the child data generation
                $properties = array();
                $properties = array("limit" => $limit, "page" => $page, "uri" => $uri);
                //get the child view data
                $childArray = array();
                $childArray = $this->OeawFunctions->generateChildViewData($identifiers, $results, $properties);
               
                if(count($childArray) > 0){
                    //pass the specialtype info to the template
                    if(isset($childArray['specialType'])){
                        $extras["childType"] = $childArray['specialType'];
                    }
                    //child table data
                    if(isset($childArray['childResult']) && count($childArray['childResult']) > 0){
                        $childResult = $childArray['childResult'];
                    }
                    //setup pagination infos
                    if(isset($childArray['pagination'])){
                        $results["pagination"] = $childArray['pagination'];
                    }
                }
            }
        } else {
            $msg = base64_encode("The resource has no metadata!");
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;            
        }
        
        /*
        $query = "";
        if(isset($results['query']) && isset($results['queryType'])){
            if($results['queryType'] == "SPARQL"){
                $query = base64_encode($uri);
            }
        }
        */
     
        $dissServices = array();
        //check the Dissemination services
        $dissServices = $this->OeawFunctions->getResourceDissServ($fedoraRes);

        if(count($dissServices) > 0 && $fedoraRes->getId()){
            $extras['dissServ']['services'] = $dissServices;
            $extras['dissServ']['identifier'] = $fedoraRes->getId();
        }
        
        // Pass fedora uri so it can be linked in the template
        $extras["fedoraURI"] = $uri;
        
        if(count($inverseData) > 0){
            $extras['inverseData'] = $inverseData;
        }
        
        //format the hasavailable date
        if(isset($results["table"]["acdh:hasAvailableDate"]) && !empty($results["table"]["acdh:hasAvailableDate"])){
            if($results["table"]["acdh:hasAvailableDate"][0]){
                if (\DateTime::createFromFormat('Y-m-d', $results["table"]["acdh:hasAvailableDate"][0]) !== FALSE) {
                    $time = strtotime($results["table"]["acdh:hasAvailableDate"][0]);
                    $newTime = date('Y-m-d', $time);
                    $results["table"]["acdh:hasAvailableDate"][0] = $newTime;
                }
                //if we dont have a real date just a year
                if (\DateTime::createFromFormat('Y', $results["table"]["acdh:hasAvailableDate"][0]) !== FALSE) {
                    $year = \DateTime::createFromFormat('Y', $results["table"]["acdh:hasAvailableDate"][0]);
                    $results["table"]["acdh:hasAvailableDate"][0] = $year->format('Y');
                }
            }
        }
        
        //generate the NiceUri to the detail View
        $niceUri = "";
        $niceUri = $this->OeawFunctions->generateNiceUri($results);
        if(!empty($niceUri)){
            $extras["niceURI"] = $niceUri;
        }

        //Create data for cite-this widget
        $typesToBeCited = ["Collection", "Project", "Resource", "Publication"];
        if(isset($results["acdh_rdf:type"]["title"]) && !empty($results["acdh_rdf:type"]["title"]) ){
            if (in_array($results["acdh_rdf:type"]["title"], $typesToBeCited)) {
                //pass $rootMeta for rdf object
                $extras["CiteThisWidget"] = $this->OeawFunctions->createCiteThisWidget($results);
            }
        }
                
        //get the tooltip from cache
        $cachedTooltip = $this->PropertyTableCache->getCachedData($results["table"]);
        if(count($cachedTooltip) > 0){
            $extras["tooltip"] = $cachedTooltip;
        }
        
        //if it is a resource then we need to check the 3dContent
        if(isset($results['acdh_rdf:type']) && $results['acdh_rdf:type']['title'] == "Resource"  ){
            if($this->OeawFunctions->check3dData($results['table']) === true){
                $extras['3dData'] = true;
            }
        }
        /*
        if(isset($results['acdh_rdf:type']) && $results['acdh_rdf:type']['title'] == "Collection"  ){
            $this->oeaw_dl_collection(base64_encode($uri));
        }*/
     
        $datatable = array(
            '#theme' => 'oeaw_detail_dt',
            '#result' => $results,
            '#extras' => $extras,
            '#userid' => $uid,
            #'#query' => $query,            
            '#childResult' => $childResult,
            '#attached' => [
                'library' => [
                'oeaw/oeaw-styles', //include our custom library for this response
                ]
            ]
        );
        
        return $datatable;
    }
    
   
    /**
     * 
     * The complex search frontend function
     * 
     * @param string $metavalue
     * @param string $limit
     * @param string $page
     * @return array
    */
    public function oeaw_complexsearch(string $metavalue = "root", string $limit = "10", string $page = "1", string $order = "datedesc" ):array {
        drupal_get_messages('error', TRUE);
       
        if(empty($metavalue)){
            $metavalue = "root";
        }
        
        //If the discover page calls the root resources forward to the root_list method
        if ($metavalue == 'root') {

            //Get the cookies if they are already set
            $limitCookie = $_COOKIE["resultsPerPage"];
            $orderCookie = $_COOKIE["resultsOrder"];
            //If a cookie setting exists and the query is coming without a specific parameter
            if (!empty($limitCookie) && empty($limit)) {
                    $limit = $limitCookie;
            }
            if (!empty($orderCookie) && empty($order)) {
                    $order = $orderCookie;
            }
            if (empty($page)) {
                    $page = "1";
            }
            return $this->roots_list($limit,$page,$order);

        } else {
            
            $res = array();        
            $errorMSG = array();  
            //Deduct 1 from the page since the backend works with 0 and the frontend 1 for the initial page
            $page = (int)$page - 1;
            $limit = (int)$limit;
            $result = array();
            $pagination = "";        
            //get the current page for the pagination        
            $currentPage = $this->OeawFunctions->getCurrentPageForPagination();

            $metavalue = urldecode($metavalue);
            $metavalue = str_replace(' ', '+', $metavalue);

            $searchStr = $this->OeawFunctions->explodeSearchString($metavalue);        
            
            $countSparql = $this->OeawCustomSparql->createFullTextSparql($searchStr, 0, 0, true);
            $count = $this->OeawStorage->runUserSparql($countSparql);
            $total = (int)count($count);
            //create data for the pagination
            $pageData = $this->OeawFunctions->createPaginationData($limit, $page, $total);

            if ($pageData['totalPages'] > 1) {
                $pagination =  $this->OeawFunctions->createPaginationHTML($currentPage, $pageData['page'], $pageData['totalPages'], $limit);
            }
            
            $sparql = $this->OeawCustomSparql->createFullTextSparql($searchStr, $limit, $pageData['end'], false, $order);

            $res = $this->OeawStorage->runUserSparql($sparql);
                        
            if(count($res) > 0){
                $i = 0;
                foreach($res as $r){
                    if( isset($r['uri']) && (!empty($r['uri'])) ){
                        $result[$i]['resUri'] = base64_encode($r['uri']);
                        $result[$i]['title'] = $r['title'];

                        if(isset($r['rdfTypes']) && !empty($r['rdfTypes']) ){
                            $x = 0;
                            $types = explode(",", $r['rdfTypes']);

                            foreach ($types as  $t){
                                if (strpos($t, RC::vocabsNmsp()) !== false) {
                                    $result[$i]['rdfType']['typeName'] = str_replace(RC::vocabsNmsp(), "", $t);
                                }
                            }
                        }
                        if( isset($r['description']) && (!empty($r['description'])) ){
                            $result[$i]['description'] = $r['description'];
                        }
                        if(isset($r['hasTitleImage']) && !empty($r['hasTitleImage'])){
                            $imageUrl = $this->OeawStorage->getImageByIdentifier($r['hasTitleImage']);
                            if($imageUrl){
                                $result[$i]['image'] = $imageUrl;
                            }
                        }
                        
                        if(isset($r["availableDate"]) && !empty($r["availableDate"])){
                            if (\DateTime::createFromFormat('Y-m-d', $r["availableDate"]) !== FALSE) {
                                $time = strtotime($r["availableDate"]);
                                $newTime = date('Y-m-d', $time);
                                $result[$i]["availableDate"] = $newTime;
                            }
                            //if the dateformat is not inserted correctly then we need to fix it...
                            if (\DateTime::createFromFormat('Y', $r["availableDate"]) !== FALSE) {
                                $result[$i]["availableDate"] = \DateTime::createFromFormat('Y', $r["availableDate"]);
                            }
                        }
                        $i++;
                    }
                }
            }

            if (count($result) < 0){
                $errorMSG = drupal_set_message(t('Sorry, we could not find any data matching your searched filters.'), 'error');
            }
            $uid = \Drupal::currentUser()->id();

            $datatable['#theme'] = 'oeaw_complex_search_res';
            $datatable['#userid'] = $uid;
            $datatable['#pagination'] = $pagination;
            $datatable['#errorMSG'] = $errorMSG;
            $datatable['#result'] = $result;
            //$datatable['#searchedValues'] = $total . ' elements containing "' . $metavalue . '" have been found.';
            $datatable['#totalResultAmount'] = $total;

            if (empty($pageData['page']) OR $pageData['page'] == 0) {
                $datatable['#currentPage'] = 1;
            } else {
                $datatable['#currentPage'] = $pageData['page'] + 1;
            }
            if (empty($pageData) OR $pageData['totalPages'] == 0) {
                $datatable['#totalPages'] = 1;
            } else {
                $datatable['#totalPages'] = $pageData['totalPages'];
            }

            return $datatable;

        }
    }
   
    
    
    /**
     * 
     * The multi step FORM to create resources based on the 
     * fedora roots and classes      
     * 
     * @return type
     */
    public function multi_new_resource() {        
        return $form = \Drupal::formBuilder()->getForm('Drupal\oeaw\Form\NewResourceOneForm');
    }
    
    public function oeaw_depagree_base(string $formid = NULL){
        return $form = \Drupal::formBuilder()->getForm('Drupal\oeaw\Form\DepAgreeOneForm');
    }
    
    /**
     * 
     *  The editing form, based on the uri resource
     * 
     * @param string $uri
     * @param Request $request
     * @return type
     */
    public function oeaw_edit(string $uri, Request $request) {
        drupal_get_messages('error', TRUE);
        return $form = \Drupal::formBuilder()->getForm('Drupal\oeaw\Form\EditForm');
    }
    
    /**
     * 
     * User ACL revoke function
     * 
     * @param string $uri
     * @param string $user
     * @param Request $request
     */
    public function oeaw_revoke(string $uri, string $user, Request $request): JsonResponse {
        
        drupal_get_messages('error', TRUE);
        $matches = array();
        $response = array();
        
        $fedora = new Fedora();       
        $fedora->begin();
        $res = $fedora->getResourceByUri($uri);
        $aclObj = $res->getAcl();
        $aclObj->revoke(\acdhOeaw\fedora\acl\WebAclRule::USER, $user, \acdhOeaw\fedora\acl\WebAclRule::READ);
        $aclObj->revoke(\acdhOeaw\fedora\acl\WebAclRule::USER, $user, \acdhOeaw\fedora\acl\WebAclRule::WRITE);        
        $fedora->commit();
        
        $asd = array();
        $asd = $this->OeawFunctions->getRules($uri);
        
//        $this->OeawFunctions->revokeRules($uri, $user);
        
        $matches = array(
            "result" => true,
            "error_msg" => "DONE"
            );
        
        $response = new JsonResponse($matches);
        
        $response->setCharset('utf-8');
        $response->headers->set('charset', 'utf-8');
        $response->headers->set('Content-Type', 'application/json');
        
        return $response;
    }
    
    
    /**
     * 
     * Resource Delete function
     * 
     * @param string $uri
     * @param Request $request
     * @return JsonResponse
     */
    public function oeaw_delete(string $uri, Request $request): JsonResponse {
        drupal_get_messages('error', TRUE);
        $matches = array();
        $response = array();
        
        if(!$uri){
            $matches = array(
                "result" => false,
                "error_msg" => "Resource does not exist!"
                );
        }
        
        $resUri = base64_decode($uri);
        $graph = $this->OeawFunctions->makeGraph($resUri);
        $fedora = new Fedora();
        
        try{
            
            $fedora->begin();
            $res = $fedora->getResourceByUri($resUri);            
            $res->delete();            
            $fedora->commit();
            
            $matches = array(
                "result"=> true, 
                "resourceid" => $uri
                );
            
        } catch (Exception $ex) {
            $fedora->rollback();
            
            $matches = array(
                "result" => false,
                "error_msg" => "Problem during the delete method!"
                );
        }
        
        $response = new JsonResponse($matches);
        
        $response->setCharset('utf-8');
        $response->headers->set('charset', 'utf-8');
        $response->headers->set('Content-Type', 'application/json');
        
        return $response;
        
    }
    
    
    
    /**
     * cache the acdh ontology 
     */
    public function oeaw_cache_onotology(){
    
        $result = array();
        if($this->PropertyTableCache->setCacheData() == true){
            $result = "cache updated succesfully!";
        }else {
            $result = "there is no ontology data to cache!";
        }
        
        $response = new Response();
        $response->setContent(json_encode($result));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
    
    /**
     * 
     * This function is for the oeaw_detail view. to the user can get the inverse table data
     * 
     * @param string $data - the resource url     
     * @return Response
     */
    public function oeaw_inverse_result(string $data){

        $invData = array();
        
        if(!empty($data)){
            $uri = base64_decode($data);
            $res = $this->OeawStorage->getInverseViewDataByURL($uri);
            
            if(count($res) <= 0){                
                $invData["data"] = array();
            }else {
                for ($index = 0; $index <= count($res) - 1; $index++) {
                    if(!empty($res[$index]['inverse']) && !empty($res[$index]['title']) && !empty($res[$index]['insideUri'])){
                        $title = $res[$index]['title'];
                        $insideUri = $res[$index]['insideUri'];
                        $invData["data"][$index] = array($res[$index]['inverse'], "<a href='/browser/oeaw_detail/$insideUri'>$title</a>");
                    }
                }
            }
        }
        
        $response = new Response();
        $response->setContent(json_encode($invData));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
    
    /**
      * 
      * This function is for the oeaw_detail view. It is used for the Organisations view, to get the isMembers
      * 
      * @param string $data - the resource url     
      * @return Response
    */
    public function oeaw_ismember_result(string $data){
         
        $memberData = array();

        if(!empty($data)){
            $uri = base64_decode($data);
            $res = $this->OeawStorage->getIsMembers($uri);
             
            if(count($res) <= 0){                
                $memberData["data"] = array();
            }else {
                for ($index = 0; $index <= count($res) - 1; $index++) {
                    if( !empty($res[$index]['title']) && !empty($res[$index]['uri'])){
                        $title = $res[$index]['title'];
                        $insideUri = base64_encode($res[$index]['uri']);
                        $memberData["data"][$index] = array("<a href='/browser/oeaw_detail/$insideUri'>$title</a>");
                    }
                }
            }
        }

        $response = new Response();
        $response->setContent(json_encode($memberData));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
    
    /**
     * 
     * This function will download the 3d model with a guzzle async request.
     * After the download it will save the file 
     * to the drupal/sites/files/file_name_dir/file_name.extension directory and
     * pass the url to the 3d viewer template
     * 
     * @param string $data -> the fedora url for the 3d content
     * @return array
     */
    public function oeaw_3d_viewer(string $data): array{
        
        if(empty($data)){
             return drupal_set_message(t('Please add a resources!'), 'error', FALSE);
        }
        
        $templateData = array();
        $title = "";
        $templateData["insideUri"] = $data;
        $fdUrl = base64_decode($data);
        //get the filename
        $fdFileName = $this->OeawStorage->getValueByUriProperty($fdUrl, "http://www.ebu.ch/metadata/ontologies/ebucore/ebucore#filename");
        
        $fdFileSize = $this->OeawStorage->getValueByUriProperty($fdUrl, RC::get('fedoraExtentProp'));
        //if we have a filename in the fedora
        if( (count($fdFileName) > 0) && isset($fdFileName[0]["value"]) ){
            //get the title
            $title = $this->OeawStorage->getResourceTitle($fdUrl);
            if(count($title) > 0){
                $templateData["title"] = $title[0]['title'];
            }
            $dir = str_replace(".", "_", $fdFileName[0]["value"]);
            $fileDir = $_SERVER['DOCUMENT_ROOT'].'/sites/default/files/'.$dir.'/'.$fdFileName[0]["value"];
            
            //if the filename is exists then we will not download it again from the server
            if( (file_exists($fileDir)) && (isset($fdFileSize[0]['value']) &&  $fdFileSize[0]['value'] == filesize($fileDir)) ){
                $url = '/sites/default/files/'.$dir.'/'.$fdFileName[0]["value"];
                
                $result =  array(
                        '#theme' => 'oeaw_3d_viewer',
                        '#ObjectUrl' => $url,
                        '#templateData' => $templateData,
                    );
                return $result;
            }
        }
        
        //this is a new 3d model, so we need to download it to the server.
        $client = new \GuzzleHttp\Client(['auth' => [RC::get('fedoraUser'), RC::get('fedoraPswd')], 'verify' => false]);
        
        try{
            $request = new \GuzzleHttp\Psr7\Request('GET', $fdUrl);
            //send async request         
            $promise = $client->sendAsync($request)->then(function ($response) {
            
                if($response->getStatusCode() == 200){
                    //get the filename
                    if(count($response->getHeader('Content-Disposition')) > 0){
                        $txt = explode(";", $response->getHeader('Content-Disposition')[0]);
                        $filename = "";
                        $extension = "";
                        
                        foreach($txt as $t){
                            if (strpos($t, 'filename') !== false) {
                                $filename = str_replace("filename=", "", $t);
                                $filename = str_replace('"', "", $filename);
                                $filename = ltrim($filename);
                                $extension = explode(".", $filename);
                                $extension = end($extension);
                                continue;
                            }
                        }

                        if($extension == "nxs" || $extension == "ply"){

                            if(!empty($filename)){
                                $dir = str_replace(".", "_", $filename);
                                $tmpDir = $_SERVER['DOCUMENT_ROOT'].'/sites/default/files/'.$dir.'/';
                                //if the file dir is not exists then we will create it
                                // and we will download the file
                                if(!file_exists($tmpDir) || !file_exists($tmpDir.'/'.$filename)){
                                    mkdir($tmpDir, 0777);
                                    $file = fopen($tmpDir.'/'.$filename, "w");
                                    fwrite($file, $response->getBody());
                                    fclose($file);
                                }else{
                                    //if the file is not exists
                                    if(!file_exists($tmpDir.'/'.$filename)){
                                        $file = fopen($tmpDir.'/'.$filename, "w");
                                        fwrite($file, $response->getBody());
                                        fclose($file);
                                    }
                                }
                                $url = '/sites/default/files/'.$dir.'/'.$filename;
                                $this->uriFor3DObj['result'] = $url;
                                $this->uriFor3DObj['error'] = "";
                            }
                        }else {
                            $this->uriFor3DObj['error'] = "Wrong file format, it is not NXS or PLY!";
                            $this->uriFor3DObj['result'] = "";
                        }
                    }
                }else{
                    $this->uriFor3DObj['error'] = "There is no file";
                    $this->uriFor3DObj['result'] = "";
                }

            });
            $promise->wait();
            
            $title = $this->OeawStorage->getResourceTitle($fdUrl);
            if(count($title) > 0){
                $templateData["title"] = $title[0]['title'];
            }
            
            
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            $this->uriFor3DObj['error'] = $ex->getMessage();
            
            $result = 
                array(
                    '#theme' => 'oeaw_3d_viewer',                    
                    '#errorMSG' =>  $this->uriFor3DObj['error']
                );
        
            return $result;
            
        }
        
        $result = 
                array(
                    '#theme' => 'oeaw_3d_viewer',
                    '#ObjectUrl' => $this->uriFor3DObj['result'],
                    '#templateData' => $templateData,
                    '#errorMSG' =>  $this->uriFor3DObj['error']
                );
        
        return $result;
    }
    
    /**
     * 
     * The view for the collection download with some basic information
     * 
     * @param string $uri
     * @return string
     */
    public function oeaw_dl_collection_view(string $uri): array{
        $errorMSG = "";
        $resData = array();
        $result = array();
        $resData['dl'] = false;
        if(empty($uri)){
            $errorMSG = "There is no valid URL";
        }else {
            
            $resData = $this->OeawFunctions->genCollectionData($uri);
            $resData['insideUri'] = $uri;
        }
        
        $result = 
            array(
                '#theme' => 'oeaw_dl_collection_tree',
                '#url' => $uri,
                '#resourceData' => $resData,
                '#errorMSG' =>  $errorMSG,
                '#attached' => [
                    'library' => [
                        'oeaw/oeaw-DL_collection', 
                    ]
                ]
            );
         
        return $result;
        
    }
    
    
    /**
     * 
     * Displaying the federated login with shibboleth
     * 
     * @return array
     */
    public function oeaw_shibboleth_login(): array{
        $result = 
            array(
                '#theme' => 'oeaw_shibboleth_login'
            );
         
        return $result;
    }
    
    /**
     * 
     * Displaying the iiif viewer
     * 
     * @param string $uri
     * @return array
     */
    public function oeaw_iiif_viewer(string $uri): array{
        $errorMSG = "";
        $resData = array();
        if(empty($uri)){
            $errorMSG = "There is no valid URL";
        }
        
        //loris url generating fucntion
        $resData = $this->OeawFunctions->generateLorisUrl($uri);
        
        if( count($resData) == 0){
            $errorMSG = "There is no valid Image!";
        }
        
        $result = 
            array(
                '#theme' => 'oeaw_iiif_viewer',
                '#url' => $uri,
                '#templateData' => $resData,
                '#errorMSG' =>  $errorMSG
            );
         
        return $result;
    }
    
    /**
     * 
     * This controller view is for the ajax collection tree view generating
     * 
     * @param string $uri
     * @return Response
     */
    public function oeaw_get_collection_data(string $uri){
        if(empty($uri)){
            $errorMSG = "There is no valid URL";
        }else {
            $resData = $this->OeawFunctions->genCollectionData($uri);
            $resData['insideUri'] = $uri;
        }
        
        //setup the the treeview data
        $result = array();
        //add the main Root element
        $resData['binaries'][] = array("uri" => base64_decode($uri), "uri_dl" => $uri, "title" => $resData['title'], "text" => $resData['title'], "filename" => str_replace(" ", "_", $resData['filename']), "rootTitle" => "");
        $result = $this->OeawFunctions->convertToTree($resData['binaries'], "text", "rootTitle");

        $response = new Response();
        $response->setContent(json_encode($result));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
    
   
    /**
     * 
     * The selected files zip download func
     * 
     * @param string $uri
     * @return array
     * @throws \Exception
     */
    public function oeaw_dl_collection(string $uri){

        $result = array();
        $errorMSG = "";
        $GLOBALS['resTmpDir'] = "";
        $binaries = array();
        $binaries = json_decode($_POST['jsonData'], true);
        
        //the main dir
        $tmpDir = $_SERVER['DOCUMENT_ROOT'].'/sites/default/files/collections/';
        //the collection own dir
        $dateID = date("Ymd_his");
        $tmpDirDate = $tmpDir.$dateID;
        
        //if we have binaries then we continue the process
        if(count($binaries) > 0){
            //if the main directory is not exists
            if(!file_exists($tmpDir)){
                mkdir($tmpDir, 0777);
            }
            //if we have the main directory then create the sub
            if(file_exists($tmpDir)){
                //create the actual dir
                
                if(!file_exists($tmpDirDate)){
                    mkdir($tmpDirDate, 0777);
                    $GLOBALS['resTmpDir'] = $tmpDirDate;
                }
            }
            
            $client = new \GuzzleHttp\Client(['auth' => [RC::get('fedoraUser'), RC::get('fedoraPswd')], 'verify' => false]);
            ini_set('max_execution_time', 1800);
            foreach($binaries as $b){
                try {
                    //if we have filename then save it
                    if(isset($b['filename'])){
                        $filename = ltrim($b['filename']);
                        //remove spaces from the filenames
                        $filename = str_replace(' ', "_", $filename);

                        if(!file_exists($GLOBALS['resTmpDir']) || !file_exists($GLOBALS['resTmpDir'].'/'.$filename)){
                            if (!file_exists($GLOBALS['resTmpDir'])){ mkdir($GLOBALS['resTmpDir'], 0777);}
                            $resource = fopen($GLOBALS['resTmpDir'].'/'.$filename, 'w');
                            $stream = \GuzzleHttp\Psr7\stream_for($resource);
                            $client->request('GET', base64_decode($b['uri']), ['save_to' => $stream]);
                            chmod($GLOBALS['resTmpDir'].'/'.$filename, 0777);
                        }else{
                            //if the file is not exists
                            if(!file_exists($GLOBALS['resTmpDir'].'/'.$filename)){
                                $resource = fopen($GLOBALS['resTmpDir'].'/'.$filename, 'w');
                                $stream = \GuzzleHttp\Psr7\stream_for($resource);
                                $client->request('GET', base64_decode($b['uri']), ['save_to' => $stream]);
                                chmod($GLOBALS['resTmpDir'].'/'.$filename, 0777);
                            }
                        }
                    }

                } catch (\GuzzleHttp\Exception\ClientException $ex) {

                    $errorMSG = "there was a problem during the file downloads";
                }
            }
        }
        
        //if we have files in the directory
        $dirFiles = scandir($tmpDirDate);        
        $hasZip = "";
        
        if(count($dirFiles) > 0){
            chmod($GLOBALS['resTmpDir'], 0777);
            
            fopen($GLOBALS['resTmpDir'].'/collection.zip', "w");
            fclose($GLOBALS['resTmpDir'].'/collection.zip');
            
            $archiveFile = $tmpDirDate.'/collection.zip';
            
            $ziph = new \ZipArchive();
            
            
            if(file_exists($archiveFile))
            {
                try{
                    if($ziph->open($archiveFile, \ZIPARCHIVE::CHECKCONS) !== TRUE)
                    {
                        $errMsg = "Unable to Open $archiveFile";
                        error_log($errMsg);
                    }else{
                        foreach($dirFiles as $d){
                            if($d == "." || $d == ".."){
                                continue;
                            }else {
                                //we will add the files into the zip, 
                                //with a localname to skip the server directory structure
                                if(!$ziph->addFile($tmpDirDate.'/'.$d, $d))
                                {
                                    $errMsg = "error archiving $file in $d";
                                    error_log($errMsg);
                                }
                            }
                        }
                    }
                } catch (Exception $ex) {
                    $errorMSG = $ex->getMessage();
                }    
            }
            
            $ziph->close();
            
            //check the new dir that it is still generating the zip file or not
            $newDir = scandir($tmpDirDate);
                    
            $checkDir = true;
            do {
                $checkDir = $this->OeawFunctions->checkArrayForValue($newDir, "collection.zip."); 
                //delete the files and keep the zip only
                foreach($dirFiles as $file){ 
                    if(is_file($tmpDir.$dateID.'/'.$file)){ 
                        unlink( $tmpDir.$dateID.'/'.$file); 
                    }
                }
                sleep(3);
            } while (false);
            
            $hasZip = RC::get('guiBaseUrl').'/sites/default/files/collections/'.$dateID.'/collection.zip';
        }
        
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode($hasZip ));
        return $response;
       
        
         
    }
    
}