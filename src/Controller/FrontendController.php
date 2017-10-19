<?php

/**
  @file
  Contains \Drupal\oeaw\Controller\FrontendController.
 */

namespace Drupal\oeaw\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\oeaw\OeawStorage;
use Drupal\oeaw\OeawFunctions;
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


class FrontendController extends ControllerBase  {
    
    private $OeawStorage;
    private $OeawFunctions;
    
    public function __construct() {
        $this->OeawStorage = new OeawStorage();
        $this->OeawFunctions = new OeawFunctions();
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
    public function roots_list(string $limit = "10", string $page = "1", string $order = "titleasc" ): array {
        
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
        //make the pagination data
        //$search = $this->OeawFunctions->makePaginatonData($offset, $limit, (int)$countRes);
        /*
        if($page >= $countRes){
            $page = $countRes - 1;
            if($page < 0){ $page = 0; }
        }*/
        
        //get the current page for the pagination        
        //$currentPage = $this->OeawFunctions->getCurrentPageForPagination();

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
        $specialType = "child";
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
        
        if(count($rootMeta) > 0){
            
            $rules = $this->OeawFunctions->getRules($uri, $fedoraRes);
            
             if(count($rules) <= 0){
                $msg = base64_encode("The Resource Rules are not reachable!");
                $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
                $response->send();
                return;
            }
        
            $ACL = $this->OeawFunctions->checkRules($rules);
            $results['ACL'] = $ACL;
            
            $results = array();
            //get the root table data
            $results = $this->OeawFunctions->createDetailViewTable($rootMeta);
            
            if(count($results) == 0){                
                $msg = base64_encode("The resource has no metadata!");
                $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
                $response->send();
                return;            
            }  

            //check the acdh:hasIdentifier data to the child view
            $identifiers = array();
            if(count($results['table']['acdh:hasIdentifier']) > 0){
                foreach($results['table']['acdh:hasIdentifier'] as $i){
                    $identifiers[] = $i['uri'];
                }
            }

            if(count($identifiers) > 0){
                
                $currentPage = $this->OeawFunctions->getCurrentPageForPagination();
                //we checks if the acdh:Person is available then we will get the Person Detail view data
                if(isset($results['table']['rdf:type'])){
                    foreach($results['table']['rdf:type'] as $rt){
                        if((isset($rt['uri'])) && 
                                (strpos($rt['uri'], RC::get('drupalPerson')) !== false)){
                            $specialType = "person";
                            $countData = $this->OeawStorage->getSpecialDetailViewData($uri, $limit, $page, true, RC::get('drupalHasContributor'));
                        }
                        //is it a concept or not
                        else if((isset($rt['uri'])) && 
                                ( (strpos($rt['uri'], RC::get('drupalConcept')) !== false) 
                                || 
                                (strpos($rt['uri'], RC::get('drupalSkosConcept')) !== false) ) 
                            ){
                            $specialType = "concept";
                            $countData = $this->OeawStorage->getSpecialDetailViewData($uri, $limit, $page, true, RC::get('drupalSkosNarrower'));
                        }
                        else if( isset($rt['uri']) &&  (strpos($rt['uri'], RC::get('drupalProject') ) !== false)) {
                            $specialType = "project";
                            $countData = $this->OeawStorage->getSpecialDetailViewData($uri, $limit, $page, true, RC::get('drupalRelatedProject'));
                        }
                        else if( isset($rt['uri']) &&  (strpos($rt['uri'], RC::get('drupalInstitute')) !== false)) {
                            $specialType = "institute";
                            $countData = $this->OeawStorage->getSpecialDetailViewData($uri, $limit, $page, true, RC::get('drupalHasMember'));
                        }else if( isset($rt['uri']) &&  (strpos($rt['uri'], RC::get('fedoraOrganisationClass')) !== false) ){
                            $specialType = "organisation";
                            $countData = $this->OeawStorage->getSpecialDetailViewData($uri, $limit, $page, true, RC::get('drupalHasContributor'));
                        }else if( isset($rt['uri']) &&  (strpos($rt['uri'], RC::get('drupalPlace')) !== false) ){
                            $specialType = "place";
                        }else {
                            $countData = $this->OeawStorage->getChildrenViewData($identifiers, $limit, $page, true);   
                        }
                    }
                }
                
                $total = (int)count($countData);
                
                if($limit == "0") { $pagelimit = "10"; } else { $pagelimit = $limit; }
                
                //create data for the pagination                
                $pageData = $this->OeawFunctions->createPaginationData($pagelimit, (int)$page, $total);
                              
                if ($pageData['totalPages'] > 1) {
                    $results['pagination'] =  $this->OeawFunctions->createPaginationHTML($currentPage, $pageData['page'], $pageData['totalPages'], $pagelimit);
                }
                
                switch ($specialType) {
                    case "person":
                        $childrenData = $this->OeawStorage->getSpecialDetailViewData($uri, $pagelimit, $pageData['end'], false, RC::get('drupalHasContributor'));
                        $results['personData'] = $this->OeawFunctions->createCustomDetailViewTemplateData($results, "person");
                        break;
                    case "concept":
                        $childrenData = $this->OeawStorage->getSpecialDetailViewData($uri, $pagelimit, $pageData['end'], false, RC::get('drupalSkosNarrower'));                        
                        break;
                    case "project":
                        $childrenData = $this->OeawStorage->getSpecialDetailViewData($uri, $pagelimit, $pageData['end'], false, RC::get('drupalRelatedProject'));
                        $results['projectData'] = $this->OeawFunctions->createCustomDetailViewTemplateData($results, "project");
                        break;
                    case "institute":
                        $childrenData = $this->OeawStorage->getSpecialDetailViewData($uri, $pagelimit, $pageData['end'], false, RC::get('drupalHasMember'));
                        break;
                    case "organisation":
                        $results['organisationData'] = $this->OeawFunctions->createCustomDetailViewTemplateData($results, "organisation");
                        $childrenData = $this->OeawStorage->getSpecialDetailViewData($uri, $pagelimit, $pageData['end'], false, RC::get('drupalHasContributor'));
                        break;
                    case "place":
                        $results['placeData'] = $this->OeawFunctions->createCustomDetailViewTemplateData($results, "place");
                        break;
                    default:
                        $childrenData = $this->OeawStorage->getChildrenViewData($identifiers, $pagelimit, $pageData['end']);
                }       
                
                if(count($childrenData) > 0){
                    $childResult = $this->OeawFunctions->createChildrenViewData($childrenData);
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
     
        $dissServices =array();
        //check the Dissemination services
        $dissServices = $this->OeawFunctions->getResourceDissServ($fedoraRes);
        if(count($dissServices) > 0){
            $extras['dissServ'] = $dissServices;
        }
        
        // Pass fedora uri so it can be linked in the template
        $extras["fedoraURI"] = $uri;
        $extras["childType"] = $specialType;
        if(count($inverseData) > 0){
            $extras['inverseData'] = $inverseData;
        }

        if(isset($results["table"]["acdh:hasAvailableDate"]) && !empty($results["table"]["acdh:hasAvailableDate"])){
            $time = strtotime($results["table"]["acdh:hasAvailableDate"][0]);
            $newTime = date('Y-m-d', $time);
            $results["table"]["acdh:hasAvailableDate"][0] = $newTime;
        }

        /* Get hasPid & create copy link
         * Order of desired URIs:
         * PID > id.acdh > id.acdh/uuid > long gui url
         */
        if (isset($results["table"]["acdh:hasPid"])) {
            if (isset($results["table"]["acdh:hasPid"][0]['uri'])) {
                $extras["niceURI"] = $results["table"]["acdh:hasPid"][0]['uri'];
            }
        }
        if (!isset($extras["niceURI"])) {
            if (isset($results["table"]["acdh:hasIdentifier"]) && !empty($results["table"]["acdh:hasIdentifier"]) ){
                $acdhURIs = $results["table"]["acdh:hasIdentifier"];
                //Only one value under acdh:hasIdentifier
                if (isset($acdhURIs["uri"])) {
                    //id.acdh/uuid
                    if (strpos($acdhURIs["uri"], 'id.acdh.oeaw.ac.at/uuid') !== false) {
                        $extras["niceURI"] = $acdhURIs["uri"];
                    }
                    //id.acdh
                    if (!isset($extras["niceURI"]) && strpos($acdhURIs["uri"], 'id.acdh.oeaw.ac.at') !== false) {
                        $extras["niceURI"] = $acdhURIs["uri"];
                    }
                }
                //Multiple values under acdh:hasIdentifier
                else {
                    foreach ($acdhURIs as $key => $acdhURI) {
                        if (strpos($acdhURI["uri"], 'id.acdh.oeaw.ac.at/uuid') !== false) {
                            $acdhURIuuid = $acdhURI["uri"];
                        } else if (strpos($acdhURI["uri"], 'id.acdh.oeaw.ac.at') !== false) {
                            $acdhURIidacdh = $acdhURI["uri"];
                        }
                    }
                    if (isset($acdhURIidacdh)) {
                        $extras["niceURI"] = $acdhURIidacdh;
                    } else if (isset($acdhURIuuid)) {
                        $extras["niceURI"] = $acdhURIuuid;
                    }
                }
            }
        }

        //Create data for cite-this widget
        $typesToBeCited = ["Collection", "Project", "Resource", "Publication"];
        if(isset($results["acdh_rdf:type"]["title"]) && !empty($results["acdh_rdf:type"]["title"]) ){
            if (in_array($results["acdh_rdf:type"]["title"], $typesToBeCited)) {
                //pass $rootMeta for rdf object
                $extras["CiteThisWidget"] = $this->OeawFunctions->createCiteThisWidget($results);
            }
        }

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
    public function oeaw_complexsearch(string $metavalue = "root", string $limit = "10", string $page = "1", string $order = "titleasc" ):array {
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

            $countSparql = $this->OeawFunctions->createFullTextSparql($searchStr, 0, 0, true);
            $count = $this->OeawStorage->runUserSparql($countSparql);
            $total = (int)count($count);
            //create data for the pagination
            $pageData = $this->OeawFunctions->createPaginationData($limit, $page, $total);

            if ($pageData['totalPages'] > 1) {
                $pagination =  $this->OeawFunctions->createPaginationHTML($currentPage, $pageData['page'], $pageData['totalPages'], $limit);
            }
            
            $sparql = $this->OeawFunctions->createFullTextSparql($searchStr, $limit, $pageData['end'], false, $order);

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
				            $time = strtotime($r["availableDate"]);
				            $newTime = date('Y-m-d', $time);
				            $result[$i]["availableDate"] = $newTime;
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
    
   
    
    
}