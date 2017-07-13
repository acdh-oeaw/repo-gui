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

class FrontendController extends ControllerBase {
    
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
     * @return array
     */
    public function roots_list(): array {
        drupal_get_messages('error', TRUE);
        // get the root resources
        // sparql result fields - uri, title
        $result = array();
        $datatable = array();
        $res = array();
        $decodeUrl = "";
        $errorMSG = array();
        
        $result = $this->OeawStorage->getRootFromDB();      

        $uid = \Drupal::currentUser()->id();
        
        if(count($result) > 0){
            $i = 0;            
            foreach($result as $value){
                // check that the value is an Url or not
                
                $decodeUrl = $this->OeawFunctions->isURL($value["uri"], "decode");                
                //create details and editing urls
                if($decodeUrl){
                    $res[$i]['detail'] = "/oeaw_detail/".$decodeUrl;
                    if($uid !== 0){
                        $res[$i]['edit'] = "/oeaw_edit/".$decodeUrl;
                        $res[$i]['delete'] = "/oeaw_delete/".$decodeUrl;
                    }
                }
                $res[$i]["uri"] = $value["uri"];
                $res[$i]["title"] = $value["title"];
                $i++;
            }
            $decodeUrl = "";
            
        }else {
            $errorMSG = drupal_set_message(t('You have no root elements!'), 'error', FALSE);
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
            $datatable['#theme'] = 'oeaw_root_dt';
            $datatable['#result'] = $res;
            $datatable['#header'] = $header;
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
    
    public function oeaw_new_success(string $uri){
        
        if (empty($uri)) {
           return drupal_set_message(t('The uri is missing!'), 'error');
        }
        $uid = \Drupal::currentUser()->id();
        // decode the uri hash
        $uri = $this->OeawFunctions->createDetailsUrl($uri, 'decode');
        
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
     * This will generate the detail view when a user clicked the detail href on a result page
     * 
     * 
     * @param string $uri
     * @param Request $request
     * @return array
     */
    public function oeaw_detail(string $uri, Request $request): array {
        drupal_get_messages('error', TRUE);
        
        if (empty($uri)) {
            $msg = base64_encode("The URI is missing");
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;            
        }
        
        $hasBinary = "";        
       //get the childrens
        $fedora = $this->OeawFunctions->initFedora();
        $childResult = array();
        
        // decode the uri hash
        $uri = $this->OeawFunctions->createDetailsUrl($uri, 'decode');
 
        $uid = \Drupal::currentUser()->id();
        
        $rootGraph = $this->OeawFunctions->makeGraph($uri);                 
        $rootMeta =  $this->OeawFunctions->makeMetaData($uri);

        if(count($rootMeta) > 0){
            $results = array();
            //get the root table data
            $results = $this->OeawFunctions->createDetailTableData($uri);        
            if(empty($results)){                
                $msg = base64_encode("The resource has no metadata!");
                $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
                $response->send();
                return;            
            }           
        } else {            
            $msg = base64_encode("The resource has no metadata!");
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;            
        }

        try{            
            if( 
                $fedora->getResourceByUri($uri)->getChildren()){
                $childF = $fedora->getResourceByUri($uri)->getChildren();                 
                //get the childrens table data
                if(count($childF) > 0){            
                    $childResult = $this->OeawFunctions->createChildrenDetailTableData($childF);
                }
            }
        } catch (\Exception $ex) {
            $msg = base64_encode("There was a runtime error during the getChildren method! Error message: ".$ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;            
        }
        
        $resTitle = $rootGraph->label($uri);
        
        if($resTitle){
            $resTitle->dumpValue('text');
        }else {
            $resTitle = "title is missing";
        }
        
        $editResData = array(
            "editUrl" => $this->OeawFunctions->createDetailsUrl($uri, 'encode'),
            "title" => $resTitle
        );
        
        if(empty($results["hasBinary"])){
            $results["hasBinary"] = "";
        }
        
        $datatable = array(
            '#theme' => 'oeaw_detail_dt',
            '#result' => $results,            
            '#userid' => $uid,            
            '#hasBinary' => $results["hasBinary"],
            '#childResult' => $childResult,         
            '#editResData' => $editResData,
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
    
    public function oeaw_depagree_base(){
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
        $resUri = $this->OeawFunctions->createDetailsUrl($uri, 'decode');
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
     * Get the classes data from the sidebar class list block
     * and display them
     * 
     * @return array
     */
    public function oeaw_classes_result(string $data): array{
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
            $result = $this->OeawStorage->getDataByProp('http://www.w3.org/1999/02/22-rdf-syntax-ns#type', $property.':'.$value);
            if(count($result) > 0){
                $i = 0;
                foreach($result as $value){
                    // check that the value is an Url or not
                    $decodeUrl = $this->OeawFunctions->isURL($value["uri"], "decode");

                    //create details and editing urls
                    if($decodeUrl){
                        $res[$i]['detail'] = "/oeaw_detail/".$decodeUrl;
                        if($uid !== 0){
                            $res[$i]['edit'] = "/oeaw_edit/".$decodeUrl;
                            $res[$i]['delete'] = $decodeUrl;
                        }
                    }
                    $res[$i]["uri"] = $value["uri"];
                    $res[$i]["title"] = $value["title"];
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
        
        if(isset($res) && $res !== null && !empty($res)){
            $datatable['#theme'] = 'oeaw_search_class_res_dt';
            $datatable['#result'] = $res;
            $datatable['#searchedValues'] = $searchArray;                
        }
        
        return $datatable;     
    } 
    
    
}
