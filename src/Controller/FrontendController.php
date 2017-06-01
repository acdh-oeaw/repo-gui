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
        
        //check the user entered char's
        if(strlen($string) < 3) { return new JsonResponse(array()); }
        
        //f.e.: depositor
        $propUri = base64_decode(strtr($prop1, '-_,', '+/='));

        if(empty($propUri)){ return new JsonResponse(array()); }
        
        $fedora = new Fedora(); 
        //get the property resources
        $rangeRes = null;
        
        try {
            $prop = $fedora->getResourceById($propUri);
            //get the property metadata
            $propMeta = $prop->getMetadata();
            // check the range property in the res metadata
            $rangeRes = $propMeta->getResource('http://www.w3.org/2000/01/rdf-schema#range');
        }  catch (\RuntimeException $e){
            return new JsonResponse(array());
        }

        if($rangeRes === null){
            return new JsonResponse(array()); // range property is missing - no autocompletion
        }

        $matchClass = $fedora->getResourcesByProperty('http://www.w3.org/1999/02/22-rdf-syntax-ns#type', $rangeRes->getUri());

        // if we want additional properties to be searched, we should add them here:
        $match = array(
            'title'  => $fedora->getResourcesByPropertyRegEx('http://purl.org/dc/elements/1.1/title', $string),
            'name'   => $fedora->getResourcesByPropertyRegEx('http://xmlns.com/foaf/0.1/name', $string),
            'acdhId' => $fedora->getResourcesByPropertyRegEx(RC::get('fedoraIdProp'), $string),
        );

        $matchResource = $matchValue = array();
        foreach ($matchClass as $i) {
            $matchResource[] = $i->getUri();
            if (stripos($i->getUri(), $string) !== false) {
                $matchValue[] = $i->getUri();
            }
        }
        foreach ($match as $i) {
            foreach ($i as $j) {
                $matchValue[] = $j->getUri();
            }
        }
        $matchValue = array_unique($matchValue);
        $matchBoth = array_intersect($matchResource, $matchValue);

        foreach ($matchClass as $i) {
            
            if (!in_array($i->getUri(), $matchBoth)) {
                continue;
            }

            $meta = $i->getMetadata();
            
            //$acdhId = $meta->getResource(EasyRdfUtil::fixPropName($config->get('fedoraIdProp')));
            $acdhId = $fedora->getResourceByUri($i->getUri());
            $acdhId = $acdhId->getId();
         
            $label = empty($meta->label()) ? $acdhId : $meta->label();
            //because of the special characters we need to convert it
            $label = htmlentities($label, ENT_QUOTES, "UTF-8");
                
            $matches[] = ['value' => $acdhId , 'label' => $label];

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
        
    
    public function oeaw_new_res_success(string $uri){
        
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
           return drupal_set_message(t('The uri is missing!'), 'error');
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
                return drupal_set_message(t('The resource has no metadata!'), 'error');
            }           
        } else {
            return drupal_set_message(t('The resource has no metadata!'), 'error');
        }

        try{
            if( $fedora->getResourceByUri($uri)->getChildren()){
                $childF = $fedora->getResourceByUri($uri)->getChildren();                 
                //get the childrens table data
                if(count($childF) > 0){            
                    $childResult = $this->OeawFunctions->createChildrenDetailTableData($childF);
                }
            }
        } catch (\Exception $ex) {
            return drupal_set_message(t('There was a runtime error during the getChildren method!'), 'error');
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
    public function oeaw_resources():array {

        drupal_get_messages('error', TRUE);
        
        $url = Url::fromRoute('<current>');
        $internalPath = $url->getInternalPath();
        $interPathArray = explode("/", $internalPath);
        $errorMSG = array();
        
        if($interPathArray[0] == "oeaw_resources"){            
            $metaKey = urldecode($interPathArray[1]);
            $metaValue = urldecode($interPathArray[2]);            
        }else{
            return drupal_set_message(t('There is no data -> Search'), 'error');        
        }
        $uid = \Drupal::currentUser()->id();
        //normal string seacrh

        $metaKey = $this->OeawFunctions->createUriFromPrefix($metaKey);
        if($metaKey === false){
            return drupal_set_message(t('Error in function: createUriFromPrefix '), 'error'); 
        }
    
        $stringSearch = $this->OeawStorage->searchForData($metaValue, $metaKey);            
        $fedora = new Fedora();
        
        //we will search in the title, name, fedoraid
        $idSearch = array(
            'title'  => $fedora->getResourcesByPropertyRegEx('http://purl.org/dc/elements/1.1/title', $metaValue),
            'name'   => $fedora->getResourcesByPropertyRegEx(\Drupal\oeaw\ConnData::$foafName, $metaValue),
            'acdhId' => $fedora->getResourcesByPropertyRegEx(RC::get('fedoraIdProp'), $metaValue),
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

                        $ids = $this->OeawStorage->searchForData($identifier, $metaKey);
                        
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
                        $data[$x]["value"] = $metaValue;
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
                
                $res[$i]["title"] = $value["title"];
                $i++;
            }
             $searchArray = array(
                "metaKey" => $metaKey,
                "metaValue" => $metaValue
            );
            $decodeUrl = "";
            
        }else {
            $errorMSG = drupal_set_message(t('There is no data -> Search'), 'error');    
        }


        $searchArray = array(
            "metaKey" => $metaKey,
            "metaValue" => $metaValue            
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
    public function oeaw_classes_result(): array{
        drupal_get_messages('error', TRUE);
        $datatable = array();
        $data = array();
        $interPathArray = array();
        $classesArr = array();
        $res = array();
        $errorMSG = array();
        
        $url = Url::fromRoute('<current>');
        $internalPath = $url->getInternalPath();
        $interPathArray = explode("/", $internalPath);
        
        if($interPathArray[0] == "oeaw_classes_result"){
            
            $searchResult = urldecode($interPathArray[1]);
            $classesArr = explode(":", $searchResult);        
            $property = $classesArr[0];
            $value =  $classesArr[1];
            $uid = \Drupal::currentUser()->id();
        
            if (strpos($value, '(') !== false) {
                $val = explode(' (', $value);
                if(count($val) > 0){
                    $value = $val[0];
                }                
            }
            
            $data = $this->OeawStorage->getDataByProp('http://www.w3.org/1999/02/22-rdf-syntax-ns#type', $property.':'.$value);
        
            if(count($data) > 0){
                $i = 0;
                foreach($data as $value){
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
