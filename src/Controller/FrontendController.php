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
    public function roots_list(string $limit = "10", string $page = "0", string $order = "ASC(?title)" ): array {
        
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
        //count all root resource for the pagination
        $countRes = $this->OeawStorage->getRootFromDB(0,0,true);
        $countRes = $countRes[0]["count"];
                
        if($countRes == 0){
            $errorMSG = drupal_set_message(t('You have no Root resources!'), 'error', FALSE);
        }
        $search = array();
        //make the pagination data
        //$search = $this->OeawFunctions->makePaginatonData($offset, $limit, (int)$countRes);
        if($page >= $countRes){
            $page = $countRes - 1;
            if($page < 0){ $page = 0; }
        }
        
        $result = $this->OeawStorage->getRootFromDB($limit, $page, false, $order);

        $uid = \Drupal::currentUser()->id();
        
        if(count($result) > 0){
            $i = 0;
            foreach($result as $value){
                $res[$i]["title"] = $value['title'];
                $res[$i]["resUri"] = base64_encode($value['uri']);
                if($value["description"]){
                    $res[$i]["description"] = $value["description"];
                }                
                $res[$i]["rdfType"] = array("Collection");
                if($value['creationdate']){
                    $time = strtotime($value['creationdate']);
                    $newTime = date('Y-m-d', $time);
                    $res[$i]["createdDate"] = $newTime;
                }
                
                if($value['image']){
                    $res[$i]["image"] = $value['image'];
                }
                if($value['hasTitleImage']){
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
            $datatable['#searchedValues'] = $i . ' top-level elements have been found.';
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
        
    /**
     * 
     * Here the oeaw module menu is generating with the available menupoints
     * 
     * @return array
    */
    public function oeaw_menu(): array {
        
        drupal_get_messages('error', TRUE);
        $table = array();
        $header = array('id' => t('MENU'));
        $rows = array();
        
        $uid = \Drupal::currentUser()->id();
            
        $link = Link::fromTextAndUrl('List All Root Resource', Url::fromRoute('oeaw_roots'));
        $rows[0] = array('data' => array($link));

        $link1 = Link::fromTextAndUrl('Search by Meta data And URI', Url::fromRoute('oeaw_search'));
        $rows[1] = array('data' => array($link1));
        
        //if the user is anonymus then we hide the add resource menu
        if($uid !== 0){
            $link2 = Link::fromTextAndUrl('Add New Resource', Url::fromRoute('oeaw_multi_new_resource'));
            $rows[2] = array('data' => array($link2));
        }
        
        $link3 = Link::fromTextAndUrl('Deposition Agreement', Url::fromRoute('oeaw_depagree_base'));
        $rows[3] = array('data' => array($link3));
        
        $table = array(
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $rows,
            '#attributes' => array(
                'id' => 'oeaw-table',
            ),
        );
        return $table;
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
           return drupal_set_message(t('The uri is missing!'), 'error');
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
           return drupal_set_message(t('The uri is missing!'), 'error');
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
    public function oeaw_detail(string $uri, Request $request, string $limit = "10", string $page = "0"): array {
        drupal_get_messages('error', TRUE);
        
        if (empty($uri)) {
            $msg = base64_encode("The URI is missing");
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
        
        $fedora = $this->OeawFunctions->initFedora();
        $uid = \Drupal::currentUser()->id();        
        
        $rules = $this->OeawFunctions->getRules($uri, $fedora);
        
        if(count($rules) <= 0){
            $msg = base64_encode("The Resource Rules are not reachable!");
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        }
        
        $ACL = $this->OeawFunctions->checkRules($rules);
        $results['ACL'] = $ACL;
        
        $rootGraph = $this->OeawFunctions->makeGraph($uri);
        $rootMeta =  $this->OeawFunctions->makeMetaData($uri);

        if(count($rootMeta) > 0){
            $results = array();
            //get the root table data
            $results = $this->OeawFunctions->createDetailViewTable($rootMeta);
            $extras["CiteThisWidget"] = $this->OeawFunctions->createCiteThisWidget($results);
            
            if(empty($results)){                
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
                                (strpos($rt['uri'], \Drupal\oeaw\ConnData::$acdhPerson) !== false)){
                            $specialType = "person";
                        }
                        //is it a concept or not
                        if((isset($rt['uri'])) && 
                                ( (strpos($rt['uri'], \Drupal\oeaw\ConnData::$acdhConcept) !== false) 
                                || 
                                (strpos($rt['uri'], \Drupal\oeaw\ConnData::$skosConcept) !== false) ) 
                            ){
                            $specialType = "concept";
                        }
                        if((isset($rt['uri'])) && 
                                (strpos($rt['uri'], \Drupal\oeaw\ConnData::$acdhProject) !== false)){
                            $specialType = "project";
                        }
                        if((isset($rt['uri'])) && 
                                (strpos($rt['uri'], \Drupal\oeaw\ConnData::$acdhInstitute) !== false)){
                            $specialType = "institute";
                        }
                    }
                }

                if($specialType == "person"){
                    $countData = $this->OeawStorage->getPersonViewData($uri, $limit, $page, true);
                }elseif($specialType == "concept"){
                    $countData = $this->OeawStorage->getConceptViewData($uri, $limit, $page, true);
                }elseif($specialType == "project"){
                    //$countData = $this->OeawStorage->getConceptViewData($uri, $limit, $page, true);
                    $countData = $this->OeawStorage->getChildrenViewData($identifiers, $limit, $page, true);
                }elseif($specialType == "institute"){
                    //$countData = $this->OeawStorage->getConceptViewData($uri, $limit, $page, true);
                    $countData = $this->OeawStorage->getChildrenViewData($identifiers, $limit, $page, true);   
                }else {
                    $countData = $this->OeawStorage->getChildrenViewData($identifiers, $limit, $page, true);   
                }
                
                $total = (int)count($countData);
                if($limit == "0") { $pagelimit = "10"; } else { $pagelimit = $limit; }
                
                //create data for the pagination                
                $pageData = $this->OeawFunctions->createPaginationData($pagelimit, $page, $total);
                $pagination = "";                
                if ($pageData['totalPages'] > 1) {
                    $results['pagination'] =  $this->OeawFunctions->createPaginationHTML($currentPage, $pageData['page'], $pageData['totalPages'], $pagelimit);
                }
                
                //if we have acdh has identifier then we will check the children data too
                 if($specialType == "person"){
                    $childrenData = $this->OeawStorage->getPersonViewData($uri, $pagelimit, $pageData['end']);
                }elseif($specialType == "concept"){
                    $childrenData = $this->OeawStorage->getConceptViewData($uri, $pagelimit, $pageData['end']);
                }else {
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
        $dissServices = $this->OeawFunctions->getResourceDissServ($uri);
        if(count($dissServices) > 0){
            $extras['dissServ'] = $dissServices;
        }
        
        // Pass fedora uri so it can be linked in the template
        $extras["fedoraURI"] = $uri;
        $extras["personChild"] = $specialType;
        if(count($inverseData) > 0){
            $extras['inverseData'] = $inverseData;
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
     * The searching page FORM
     * 
     * @return type
     */
    public function oeaw_search() {    
        drupal_get_messages('error', TRUE);
        $form = \Drupal::formBuilder()->getForm('Drupal\oeaw\Form\SearchForm');
        return $form;
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
    public function oeaw_complexsearch(string $metavalue, string $limit = "10", string $page = "0"):array {

        drupal_get_messages('error', TRUE);
        
        $res = array();        
        $errorMSG = array();        
        $page = (int)$page;
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
        $sparql = $this->OeawFunctions->createFullTextSparql($searchStr, $limit, $pageData['end']);
        
        $res = $this->OeawStorage->runUserSparql($sparql);
        
        if(count($res) > 0){
            $i = 0;
            foreach($res as $r){
                if( !empty($r['uri']) ){
                    $result[$i]['resUri'] = base64_encode($r['uri']);
                    $result[$i]['title'] = $r['title'];
                    
                    if($r['rdfTypes']){
                        $x = 0;
                        $types = explode(",", $r['rdfTypes']);
                        
                        foreach ($types as  $t){
                            if (strpos($t, RC::vocabsNmsp()) !== false) {
                                $result[$i]['rdfType']['typeName'] = str_replace(RC::vocabsNmsp(), "", $t);
                            }
                        }
                    }
                    if($r['description']){
                        $result[$i]['description'] = $r['description'];
                    }
                    if($r['createdDate']){
                        $result[$i]['createdDate'] = $r['createdDate'];
                    }
                    if($r['hasTitleImage']){
                        $imageUrl = $this->OeawStorage->getImageByIdentifier($r['hasTitleImage']);
                        if($imageUrl){
                            $result[$i]['image'] = $imageUrl;
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
        $datatable['#searchedValues'] = $total . ' elements containing "' . $metavalue . '" have been found.';

        return $datatable;
    }
    
     /**
     * 
     * This contains the keyword search page results
     * 
     * @return array
     */
    public function oeaw_keywordsearch(string $metavalue):array {

        drupal_get_messages('error', TRUE);
                
        $errorMSG = array();
        
        if(empty($metavalue)){
            return drupal_set_message(t('There is no data -> Search'), 'error');        
        }
        
        $uid = \Drupal::currentUser()->id();
        //decode the search variable
        $metavalue = urldecode($metavalue);                   
        
        $fedora = new Fedora();
        
        //we will search in the title, name, fedoraid
        $keywordSearch = array(
            'title'  => $fedora->getResourcesByPropertyRegEx(\Drupal\oeaw\ConnData::$title, $metavalue),
            'description'  => $fedora->getResourcesByPropertyRegEx(\Drupal\oeaw\ConnData::$description, $metavalue),
            'contributor'  => $fedora->getResourcesByPropertyRegEx(\Drupal\oeaw\ConnData::$contributor, $metavalue),
            'name'   => $fedora->getResourcesByPropertyRegEx(\Drupal\oeaw\ConnData::$foafName, $metavalue),
            'acdhId' => $fedora->getResourcesByPropertyRegEx(RC::get('fedoraIdProp'), $metavalue),
        );
        
        $result = array();
        $uniqueMatches = array();
	$i = 0;
        foreach ($keywordSearch as $searchedIn) {            
            foreach ($searchedIn as $match) {
                //If we have some matches we get the uri and then create details to display				
                if(!empty($match->getUri())){   
                    $matchURI = $match->getUri();

                    //Ignore duplicate results
                    if (!in_array($matchURI, $uniqueMatches)) {
                        $uniqueMatches[] = $matchURI;
                        //Title and the URI
                        $result[$i]["title"] = $match->getMetadata()->get(\Drupal\oeaw\ConnData::$title);
                        $result[$i]["resUri"] = base64_encode($matchURI);
                        //Literal class information
                        $result[$i]["description"] = $match->getMetadata()->get(\Drupal\oeaw\ConnData::$description);
                        $creationdate = $match->getMetadata()->get(\Drupal\oeaw\ConnData::$creationdate);
                        $creationdate = strtotime($creationdate);
                        $result[$i]["creationdate"] = date('F jS, Y',$creationdate);

                        //Resource author and contributor information
                        $contributors = $match->getMetadata()->all(\Drupal\oeaw\ConnData::$contributor);
                        if (isset($contributors) && $contributors) {
                            $c = 0;
                            foreach ($contributors as $contributor) {
                                $contributorName = $this->OeawFunctions->getTitleByTheFedIdNameSpace($contributor);
                                if ($contributorName) {
                                    //If there are multiple people then add a comma in between
                                    if ($c > 0) {
                                        $result[$i]["contributors"][$c-1]["contributorName"] .= ",";     
                                    }								
                                    $result[$i]["contributors"][$c]["contributorName"] = $contributorName;
                                    $result[$i]["contributors"][$c]["contributorUri"] = $this->OeawFunctions->getFedoraUrlHash($contributor);
                                    $c++;
                                }    
                            }    
                        }
	                    
                        $authors = $match->getMetadata()->all(\Drupal\oeaw\ConnData::$author);
                        if (isset($authors) && $authors) {
                            $a = 0;
                            foreach ($authors as $author) {
                                $authorName = $this->OeawFunctions->getTitleByTheFedIdNameSpace($author);
                                if ($authorName) {
                                    //If there are multiple people then add a comma in between
                                    if ($a > 0) {
                                        $result[$i]["authors"][$a-1]["authorName"] .= ",";     
                                    }
                                    $result[$i]["authors"][$a]["authorName"] = $authorName;
                                    $result[$i]["authors"][$a]["authorUri"] = $this->OeawFunctions->getFedoraUrlHash($author);
                                    $a++;
                                }    
                            }
                        }
	
                        $isPartOf = $match->getMetadata()->get(\Drupal\oeaw\ConnData::$isPartOf);
                        if (isset($isPartOf) && $isPartOf) {
                            $result[$i]["isPartOfTitle"] = $this->OeawFunctions->getTitleByTheFedIdNameSpace($isPartOf);
                            $result[$i]["isPartOfUri"] = $this->OeawFunctions->getFedoraUrlHash($isPartOf);
                        }

                        $hasImageType = false;
                        $rdfType = $match->getMetadata()->all(\Drupal\oeaw\ConnData::$rdfType);
                        if (isset($rdfType) && $rdfType) {						
                            foreach ($rdfType as $type) {
                                if ($type == \Drupal\oeaw\ConnData::$imageProperty) {
                                    $hasImageType = true; 
                                } else if (preg_match("/vocabs.acdh.oeaw.ac.at/", $type)) {
                                    $result[$i]["rdfType"] = explode('https://vocabs.acdh.oeaw.ac.at/#', $type)[1];	 
                                    $result[$i]["rdfTypeUri"] = "/oeaw_classes_result/" . base64_encode('acdh:'.$result[$i]["rdfType"]);
                                    //Add a space between capital letters
                                    $result[$i]["rdfType"] = preg_replace('/(?<! )(?<!^)[A-Z]/',' $0', $result[$i]["rdfType"]);
                                    break;
                                }	 	
                            }  						
                        }

                        if ($hasImageType) {
                                $result[$i]["image"] = $matchURI;
                        } else {
                            $thumbnail = $match->getMetadata()->get(\Drupal\oeaw\ConnData::$imageThumbnail);
                            if (isset($thumbnail) && $thumbnail) {
                                $imgData = $this->OeawStorage->getImage($thumbnail);
                                if (isset($imgData) && $imgData) {
                                    $result[$i]["image"] = $imgData;
                                }	
                            }						
                        }
                        $i++;
	            }
                }
            }
        } 

        if (empty($result)){
            $errorMSG = drupal_set_message(t('Sorry, we could not find any data matching your searched filters.'), 'error');
        }
		
        $datatable['#theme'] = 'oeaw_keyword_search_res';
        $datatable['#userid'] = $uid;
        $datatable['#errorMSG'] = $errorMSG;
        $datatable['#result'] = $result;
        $datatable['#searchedValues'] = count($result) . ' elements containing "' . $metavalue . '" have been found.';

        return $datatable;
    } 

    /**
     * 
     * This contains the search page results
     * 
     * @return array
     */
    public function oeaw_resources(string $metakey, string $metavalue):array {

        drupal_get_messages('error', TRUE);
        
        $errorMSG = array();
        
        if(empty($metakey) || empty($metavalue)){
            return drupal_set_message(t('There is no data -> Search'), 'error');        
        }
        
        $uid = \Drupal::currentUser()->id();
        //normal string seacrh
        $metakey = base64_decode($metakey);
        $metavalue = base64_decode($metavalue);
        $metakey = $this->OeawFunctions->createUriFromPrefix($metakey);
        if($metakey === false){
            drupal_set_message(t('Error in function: createUriFromPrefix '), 'error'); 
            return;
        }
    
        $stringSearch = $this->OeawStorage->searchForData($metavalue, $metakey);            
        
        $fedora = new Fedora();
        
        //we will search in the title, name, fedoraid
        $idSearch = array(
            'title'  => $fedora->getResourcesByPropertyRegEx('http://purl.org/dc/elements/1.1/title', $metavalue),
            'name'   => $fedora->getResourcesByPropertyRegEx(\Drupal\oeaw\ConnData::$foafName, $metavalue),
            'acdhId' => $fedora->getResourcesByPropertyRegEx(RC::get('fedoraIdProp'), $metavalue),
        );

        $x = 0;
        $data = array();
        $datatable = array();
        
        foreach ($idSearch as $i) {            
            foreach ($i as $j) {
                //if there is any property which contains the searched value then
                // we get the uri and 
                if(!empty($j->getUri())){
                    //get the resource identifier f.e.: id.acdh.oeaw.ac.at.....                    
                    $identifier = $fedora->getResourceByUri($j->getUri())->getMetadata()->getResource('http://purl.org/dc/terms/identifier');
                    
                    if(!empty($identifier)){
                        //get the resources which is part of this identifier
                        $identifier = $identifier->getUri();                        

                        $ids = $this->OeawStorage->searchForData($identifier, $metakey);
                        
                        //generate the result array
                        foreach($ids as $v){                            
                            if(!$v["uri"]){
                                break;
                            }
                            $data[$x]["uri"] = $v["uri"];
                            
                            if(empty($v["title"])){
                                $v["title"] = "";
                            }
                            $data[$x]["title"] = $v["title"];
                            $x++;
                        }
                    }else {                       
                        $data[$x]["uri"] = $j->getUri();
                        $data[$x]["value"] = $metavalue;
                        $data[$x]["title"] = $j->getMetadata()->label()->__toString();
                        $x++;
                    }
                }
            }
        }
       
        if(!empty($data) && !empty($stringSearch)){
            $data = array_merge($data, $stringSearch);
        }elseif (empty($data)) {
            $data = $stringSearch;
        }

        if(count($data) > 0){
            $i = 0;
          
            foreach($data as $value){
                                
                // check that the value is an Url or not
                if($value["res"]){
                    $decodeUrl = $this->OeawFunctions->isURL($value["res"], "decode");
                
                    //create details and editing urls
                    if($decodeUrl){
                        $res[$i]['detail'] = "/oeaw_detail/".$decodeUrl;
                        if($uid !== 0){
                            $res[$i]['edit'] = "/oeaw_edit/".$decodeUrl;
                            $res[$i]['delete'] = "/oeaw_delete/".$decodeUrl;
                        }
                    }                
                    $res[$i]["uri"] = $value["res"];
                }
                
                if($value["thumb"]){
                    if($this->OeawStorage->getImage($value["thumb"])){
                        $res[$i]['thumb'] = $this->OeawStorage->getImage($value["thumb"]);
                    }
                }
                
                if($value["image"]){                    
                    $res[$i]['image'] = $value["res"];
                }
                
                $res[$i]["title"] = $value["title"];
                $i++;
            }
             $searchArray = array(
                "metaKey" => $metakey,
                "metaValue" => $metavalue
            );
            $decodeUrl = "";
            
        }else {
            $errorMSG = drupal_set_message(t('There is no data -> Search'), 'error');    
        }
      
        $searchArray = array(
            "metaKey" => $metakey,
            "metaValue" => $metavalue            
        );
        
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
            $datatable['#theme'] = 'oeaw_search_res_dt';
            $datatable['#result'] = $res;
            $datatable['#searchedValues'] = $searchArray;
        }
        
        return $datatable;
       
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
                "error_msg" => "URI MISSING!"
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
                    if(!empty($res[$index]['prop']) && !empty($res[$index]['title']) && !empty($res[$index]['insideUri'])){
                        $title = $res[$index]['title'];
                        $insideUri = $res[$index]['insideUri'];
                        $invData["data"][$index] = array($res[$index]['prop'], "<a href='$insideUri'>$title</a>");
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
     * Get the classes data from the sidebar class list block
     * and display them
     * 
     * @return array
     */
    public function oeaw_classes_result(string $data, string $limit = "10", string $page = "0"): array{
        drupal_get_messages('error', TRUE);
        
        if(empty($data)){
            drupal_set_message(t('There is no data -> Search'), 'error');
            return;
        }
        
        $datatable = array();
        
        $interPathArray = array();
        $classesArr = array();
        $res = array();        
        $errorMSG = array();
        $pagination = "";
        
        $page = (int)$page;
        $limit = (int)$limit;        
        //get the current page for the pagination        
        $currentPage = $this->OeawFunctions->getCurrentPageForPagination();
        
        $classesArr = explode(":", base64_decode($data));
        $property = $classesArr[0];
        $value =  $classesArr[1];
        
        if (strpos($value, '(') !== false) {
            $val = explode(' (', $value);
            if(count($val) > 0){
                $value = $val[0];
            }
        }
        
        $uid = \Drupal::currentUser()->id();
        if(!empty($property) && !empty($value)){
            //get all data
            $countRes = $this->OeawStorage->getDataByProp('http://www.w3.org/1999/02/22-rdf-syntax-ns#type', $property.':'.$value, 0, 0, true);
            $total = (int)$countRes[0]["count"];
                
            if($countRes == 0){
                $errorMSG = drupal_set_message(t('There is no data in the Database!'), 'error', FALSE);
            }
            //create data for the pagination
            $pageData = $this->OeawFunctions->createPaginationData($limit, $page, $total);
        
            if ($pageData['totalPages'] > 1) {
                $pagination =  $this->OeawFunctions->createPaginationHTML($currentPage, $pageData['page'], $pageData['totalPages'], $limit);
            }
            
            //get the search result
            $result = $this->OeawStorage->getDataByProp('http://www.w3.org/1999/02/22-rdf-syntax-ns#type', $property.':'.$value, $limit, $pageData['end']);
            
            if(count($result) > 0){
                $i = 0;
                foreach($result as $value){	            

                    // check that the value is an Url or not
                    $decodeUrl = $this->OeawFunctions->isURL($value["uri"], "decode");

                    //create details and editing urls
                    if($decodeUrl){
                        //$res[$i]['detail'] = "/oeaw_detail/".$decodeUrl;
                        $res[$i]['resUri'] = $decodeUrl;
                        /*if($uid !== 0){
                            $res[$i]['edit'] = "/oeaw_edit/".$decodeUrl;
                            $res[$i]['delete'] = $decodeUrl;
                        }*/
                    }

                    $res[$i]["uri"] = $value["uri"];
                    $res[$i]["title"] = $value["title"];
                    if(isset($value["firstName"]) && $value["lastName"]){
                        $res[$i]["specialLabel"] = $value["firstName"].' '.$value["lastName"];
                    }
                    if(isset($value["description"])){
                        $res[$i]["description"] = $value["description"];
                    }
                    $creationdate = $value["creationdate"];
                    $creationdate = strtotime($creationdate);
                    $res[$i]["creationdate"] = date('F jS, Y',$creationdate);

                    $isPartOf = $value["isPartOf"];
                    if (isset($isPartOf) && $isPartOf) {
                        $res[$i]["isPartOfTitle"] = $this->OeawFunctions->getTitleByTheFedIdNameSpace($isPartOf);
                        $res[$i]["isPartOfUri"] = $this->OeawFunctions->getFedoraUrlHash($isPartOf);
                    }

                    $rdfTypes = $value["rdfTypes"];                       
                    if (isset($rdfTypes) && $rdfTypes) {
                        $rdfTypes = explode(',', $rdfTypes);
                        foreach ($rdfTypes as $rdfType) {
                            if (preg_match("/vocabs.acdh.oeaw.ac.at/", $rdfType)) {                            
                            $res[$i]["rdfType"] = explode('https://vocabs.acdh.oeaw.ac.at/#', $rdfType)[1]; 
                            $res[$i]["rdfTypeUri"] = "/oeaw_classes_result/" . base64_encode('acdh:'.$res[$i]["rdfType"]);
                            $res[$i]["rdfType"] = preg_replace('/(?<! )(?<!^)[A-Z]/',' $0', $res[$i]["rdfType"]);
                                break;  
                            }
                        }
                    }

                    //Resource author and contributor information
                    $contributors = $value["contributors"]; 
                    if (isset($contributors) && $contributors) {
                        $c = 0;
                        $contributors = explode(',', $contributors);
                        foreach ($contributors as $contributor) {
                            $contributorName = $this->OeawFunctions->getTitleByTheFedIdNameSpace($contributor);
                            if ($contributorName) {
                            //If there are multiple people then add a comma in between
                                if ($c > 0) {
                                    $res[$i]["contributors"][$c-1]["contributorName"] .= ",";     
                                }								
                                $res[$i]["contributors"][$c]["contributorName"] = $contributorName;
                                $res[$i]["contributors"][$c]["contributorUri"] = $this->OeawFunctions->getFedoraUrlHash($contributor);
                                $c++;
                            }    
                        }
                    }

                    $authors = $value["authors"]; 
                    if (isset($authors) && $authors) {
                        $a = 0;
                        $authors = explode(',', $authors);
                        foreach ($authors as $author) {
                            $authorName = $this->OeawFunctions->getTitleByTheFedIdNameSpace($author);
                            if ($authorName) {
                                //If there are multiple people then add a comma in between
                                if ($a > 0) {
                                    $res[$i]["authors"][$a-1]["authorName"] .= ",";     
                                }								
                                $res[$i]["authors"][$a]["authorName"] = $authorName;
                                $res[$i]["authors"][$a]["authorUri"] = $this->OeawFunctions->getFedoraUrlHash($author);
                                $a++;
                            }
                        }
                    }
                    $i++;
                }
                
                $searchArray = array(
                    "metaKey" => $classesArr[0],
                    "metaValue" => $classesArr[1]
                );
                $decodeUrl = "";

            }else {
                $errorMSG = drupal_set_message(t('There is no data -> Class List Search'), 'error');                
            }
        }else {
            $searchArray = array();
            $res = array();
        }

        $datatable = array(            
            '#userid' => $uid,
            '#errorMSG' => $errorMSG,
            '#attached' => [
                'library' => [
                'oeaw/oeaw-styles', 
                ]
            ]
        );
        
        if(isset($searchArray)) {
	        $metaValueReadable = preg_replace('/(?<! )(?<!^)[A-Z]/',' $0', $searchArray["metaValue"]);
        }
 
        if(isset($res) && $res !== null && !empty($res)){
            $datatable['#theme'] = 'oeaw_keyword_search_res';
            $datatable['#result'] = $res;
            $datatable['#pagination'] = $pagination;
            $datatable['#searchedValues'] = $i . ' elements of type "' . $metaValueReadable . '" have been found.';                
        }
        
        return $datatable;     
    } 
    
    
}