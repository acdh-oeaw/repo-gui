<?php

namespace Drupal\oeaw;

use Drupal\Core\Url;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ChangedCommand;
use Drupal\Core\Ajax\CssCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Entity\Exception;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Component\Render\MarkupInterface;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;


use Drupal\oeaw\OeawStorage;
use Drupal\oeaw\ConnData;

use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\FedoraResource;
//use acdhOeaw\util\EasyRdfUtil;
//use zozlak\util\Config;
use acdhOeaw\util\RepoConfig as RC;
use EasyRdf\Graph;
use EasyRdf\Resource;


 
class OeawFunctions {
            
    public function __construct(){
        \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');
    }
        
    /**
     * 
     * Creates the Fedora instance
     * 
     * @return Fedora
     */   
    public function initFedora(): Fedora{
        // setup fedora
        $fedora = array();
        $fedora = new Fedora();
        return $fedora;
    }
        
    /**
     * 
     * Creates the EasyRdf_Resource by uri
     * 
     * @param string $uri
     * @return \EasyRdf\Resource
     */
    public function makeMetaData(string $uri): \EasyRdf\Resource{
        
        if(empty($uri)){
            return drupal_set_message(t('The uri is missing!'), 'error');
        }
        
        $meta = array();
       // setup fedora
        $fedora = new Fedora();
         try{
            $meta = $fedora->getResourceByUri($uri);
            $meta = $meta->getMetadata();
        } catch (\acdhOeaw\fedora\exceptions\NotFound $ex){
            $msg = base64_encode("URI NOT EXISTS");
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        }         
        
        return $meta;
    }
    
    
    /**
     * Creates the EasyRdf_Graph by uri
     * 
     * @param string $uri - resource uri
     * @return  \EasyRdf\Graph
     * 
     */
    public function makeGraph(string $uri): \EasyRdf\Graph{
     
        $graph = array();
        // setup fedora        
        $fedora = new Fedora();
        
        //create and load the data to the graph
        try{
            $graph = $fedora->getResourceByUri($uri);
            $graph = $graph->getMetadata()->getGraph();
        }catch (\Exception $ex){
            $msg = base64_encode($ex->getMessage());
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        }     
        catch (\acdhOeaw\fedora\exceptions\NotFound $ex){
            $msg = base64_encode("URI NOT EXISTS");
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        } catch (\acdhOeaw\fedora\exceptions\Deleted $ex){
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
        
        return $graph;
    }
    
    
     /**
     * Get the title by the property
     * This is a static method because the Edit/Add form will use it
     * over their callback method.
     *     
     * 
     * @param array $formElements -> the actual form input
     * @param string $mode -> edit/new form.
     * @return AjaxResponse
     * 
     */    
    public function getFieldNewTitle(array $formElements, string $mode = 'edit'): AjaxResponse {
        
        $ajax_response = array();         
        $fedora = new Fedora();
        
        if($mode == "edit"){
            //create the old values and the new values arrays with the user inputs
            foreach($formElements as $key => $value){
                if (strpos($key, ':oldValues') !== false) {
                    if(strpos($key, ':prop') === false){
                        $newKey = str_replace(':oldValues', "", $key);
                        $oldValues[$newKey] = $value;
                    }
                }else {
                    $newValues[$key] = $value;
                }
            }
            //get the differences
            $result = array_diff_assoc($newValues, $oldValues);
            
        }else if($mode == "new"){
                 //get the values which are urls
            foreach($formElements as $key => $value){
                if((strpos($key, ':prop') !== false)) {
                    unset($formElements[$key]);
                }elseif (strpos($value, 'http') !== false) {
                    $result[$key] = $value;
                }
            }
        }
        
        $ajax_response = new AjaxResponse();
        
        if(empty($result)){ return $ajax_response; }
        
        foreach($result as $key => $value){
            //get the fedora urls, where we can create a FedoraObject
            if (!empty($value) && strpos($value, RC::get('fedoraApiUrl')) !== false && $key != "file" && !is_array($value)) {
                $lblFO = $this->makeMetaData($value);
                //if not empty the fedoraObj then get the label
                if(!empty($lblFO)){
                    $lbl = $lblFO->label();
                }
            }
            
            if(!empty($lbl)){
                $label = htmlentities($lbl, ENT_QUOTES, "UTF-8");
            }else {
                $label = "";
            }
                        
            $ajax_response->addCommand(new HtmlCommand('#edit-'.$key.'--description', "New Value: <a href='".(string)$value."' target='_blank'>".(string)$label."</a>"));
            $ajax_response->addCommand(new InvokeCommand('#edit-'.$key.'--description', 'css', array('color', 'green')));
        }
        
        // Return the AjaxResponse Object.
        return $ajax_response;        
    }
  
           
    /**
     * 
     * Create array from  EasyRdf_Sparql_Result object
     * 
     * @param \EasyRdf\Sparql\Result $result
     * @param array $fields
     * @return array
     */
    public function createSparqlResult(\EasyRdf\Sparql\Result $result, array $fields): array{
        
        if(empty($result) && empty($fields)){
            return drupal_set_message(t('Error in function: '.__FUNCTION__), 'error');
        }
        $res = array();
        $resCount = count($result)-1;        
        $val = "";
        
        for ($x = 0; $x <= $resCount; $x++) {
        
            foreach($fields as $f){                
                
                if(!empty($result[$x]->$f)){
                    
                    $objClass = get_class($result[$x]->$f);
                    
                    if($objClass == "EasyRdf\Resource"){                        
                        $val = $result[$x]->$f;
                        $val = $val->getUri();
                        $res[$x][$f] = $val;                        
                    }else if($objClass == "EasyRdf\Literal"){                                                
                        $val = $result[$x]->$f;
                        $val = $val->__toString();
                        $res[$x][$f] = $val;                        
                    } else {
                        $res[$x][$f] = $result[$x]->$f->__toString();
                    } 
                }
                else{
                    $res[$x][$f] = "";
                }
            }
        }
        return $res;
    }
    
    /**
     * 
     * create prefix from string based on the connData.php prefixes     
     * 
     * @param string $string
     * @return string
     */
    public static function createPrefixesFromString(string $string): string{
        
        if (empty($string)) { return false; }
        
        $result = array();        
        
        $endValue = explode('/', $string);
        $endValue = end($endValue);
        
        if (strpos($endValue, '#') !== false) {
            $endValue = explode('#', $string);
            $endValue = end($endValue);
        }
        
        $newString = array();
        $newString = explode($endValue, $string);
        $newString = $newString[0];
                
        if(!empty(\Drupal\oeaw\ConnData::$prefixesToChange[$newString])){
            
            $result = \Drupal\oeaw\ConnData::$prefixesToChange[$newString].':'.$endValue;
        }
        else {
            $result = $string;
        }         
        return $result;        
    }

    
    /**
     * 
     * create prefix from array based on the connData.php prefixes     
     * 
     * @param array $array
     * @param array $header
     * @return array
     */
    public function createPrefixesFromArray(array $array, array $header): array{
        
        if (empty($array) && empty($header)) {
            return drupal_set_message(t('Error in function: '.__FUNCTION__), 'error');
        }
        
        $result = array();        
        $newString = array();        
        
        for ($index = 0; $index < count($header); $index++) {
            
            $key = $header[$index];
            foreach($array as $a){
                $value = $a[$key];
                $endValue = explode('/', $value);
                $endValue = end($endValue);
                
                if (strpos($endValue, '#') !== false) {
                    $endValue = explode('#', $value);
                    $endValue = end($endValue);
                }
                
                $newString = explode($endValue, $value);
                $newString = $newString[0];
                 
                if(!empty(\Drupal\oeaw\ConnData::$prefixesToChange[$newString])){            
                    $result[$key][] = \Drupal\oeaw\ConnData::$prefixesToChange[$newString].':'.$endValue;
                }else {
                    $result[$key][] = $value;
                }
            }
        }       
        return $result;        
    }
    
    /**
     * 
     * details button url generating to pass the uri value to the next page     
     * 
     * @param string $data
     * @param string $way
     * @param string $dl
     * @return string
     */
    public function createDetailsUrl(string $data, string $way = 'encode', string$dl = null): string {
      
        $returnData = "";
        
        if ($way == 'encode') {            
            $data = str_replace(RC::get('fedoraApiUrl').'/', '', $data);
            $data = base64_encode($data);
            $returnData = str_replace(array('+', '/', '='), array('-', '_', ''), $data);
        }

        if ($way == 'decode') {
            $data = str_replace('oeaw_detail/', '', $data);
            $data = str_replace('/', '', $data);
            $data = str_replace(array('-', '_'), array('+', '/'), $data);
            $mod4 = strlen($data) % 4;
            
            if ($mod4) { $data .= substr('====', $mod4); }
            
            $data = base64_decode($data);
                        
            $returnData = RC::get('fedoraApiUrl').'/' . $data;
            
        }
        return $returnData;
    }
    
    /**
     * 
     * create the data for the children resource in the detail view
     * 
     * @param array $data
     * @return array
     */
    public function createChildrenDetailTableData(array $data): array{
        
        if(empty($data)){
            return drupal_set_message(t('Error in function: '.__FUNCTION__), 'error');
        }
        
        $i = 0;
        $childResult = array();
        $uid = \Drupal::currentUser()->id();
        
        foreach($data as $r){
            $childResult[$i]['uri']= $r->getUri();                
            $childResult[$i]['title']= $r->getMetadata()->label();
                
            $imageThumbnail = $r->getMetadata()->get(\Drupal\oeaw\ConnData::$imageThumbnail);
            $imageRdfType = $r->getMetadata()->all(\Drupal\oeaw\ConnData::$rdfType);

            
            //check the thumbnail
            if($imageThumbnail && $imageThumbnail !== NULL){
                //check the resource type
                if(get_class($imageThumbnail) == "EasyRdf\Resource"){
                    $imgUri = $imageThumbnail->getUri();
                }
                
                if(!empty($imgUri)){
                    $OeawStorage = new OeawStorage();
                    $childThumb = $OeawStorage->getImage($imgUri);
                    
                    if(!empty($childThumb)){
                        $childResult[$i]['thumbnail'] = $childThumb;
                    }
                }
            }else if(!empty($imageRdfType)){
                //if we dont have a thumbnail then maybe it is an IMAGE Resource
                foreach($imageRdfType as $rdfVal){
                    if($rdfVal->getUri() == \Drupal\oeaw\ConnData::$imageProperty){
                        $childResult[$i]['thumbnail'] = $r->getUri();
                    }
                }
            }
            
            $decUrlChild = $this->isURL($r->getUri(), "decode");

            $childResult[$i]['detail'] = "/oeaw_detail/".$decUrlChild;
            if($uid !== 0){
                $childResult[$i]['edit'] = "/oeaw_edit/".$decUrlChild;
                $childResult[$i]['delete'] = $decUrlChild;
            } 
            $i++;
        }
        return $childResult;
    }
    
    /**
     * 
     * create table data for the root resource in the detail view.
     * changes the uris to prefixes
     * 
     * @param string $uri
     * @return array
     */
    public function createDetailTableData(string $uri): array{
        
        $OeawStorage = new OeawStorage();
        
        if(empty($uri)){
            return drupal_set_message(t('Error in function: '.__FUNCTION__), 'error');
        }
        
        $results = array();
        $resVal = "";
        $rootMeta =  $this->makeMetaData($uri);
        
        if(count($rootMeta) > 0){
            $i = 0;
            
            foreach($rootMeta->propertyUris($uri) as $v){
            
                foreach($rootMeta->all($v) as $item){
            
                    // if there is a thumbnail
                    if($v == \Drupal\oeaw\ConnData::$imageThumbnail){
                        if($item){                                                    
                            $imgData = $OeawStorage->getImage($item);
                            if($imgData){                                
                                $results[$i]["image"] = $imgData;
                            }
                        }
                    }else if($v == \Drupal\oeaw\ConnData::$rdfType){
                        if($item == \Drupal\oeaw\ConnData::$imageProperty){
                            $hasImage = $uri;
                            $results[$i]["image"] = $uri;
                        }
                    }
                    
                    if(get_class($item) == "EasyRdf\Resource"){
                        if($this->createPrefixesFromString($v) === false){                            
                            return drupal_set_message(t('Error in function: createPrefixesFromString'), 'error');
                        }
                        
                        //check the title based on the acdh id
                        if($item->getUri()){
                            
                            $resVal = $item->getUri();
                            //get the resource title
                            if($this->getTitleByTheFedIdNameSpace($resVal)){
                                $resValTitle = "";
                                $resValTitle = $this->getTitleByTheFedIdNameSpace($resVal);
                                //we have a title for the resource
                                if($resValTitle){
                                    $results[$i]["val_title"][] = $resValTitle;
                                }
                            }
                            //itt a query
                            
                            $results[$i]["value"][] = $resVal;                            
                            $results[$i]["property"] = $this->createPrefixesFromString($v);
                            //create the HASH URL for the table value
                            if($this->getFedoraUrlHash($resVal)){
                                $results[$i]["inside_url"][] = $this->getFedoraUrlHash($resVal);
                            }
                        }
                        
                        if($item->getUri() == \Drupal\oeaw\ConnData::$fedoraBinary){ $results["hasBinary"] = $uri; }

                    }else if(get_class($item) == "EasyRdf\Literal"){
                                                
                        if($this->createPrefixesFromString($v) === false){
                            return drupal_set_message(t('Error in function: createPrefixesFromString'), 'error');
                        }
                        
                        if($v == \Drupal\oeaw\ConnData::$acdhQuery){
                            $results['query'] = $item->__toString();
                        }
                        if($v == \Drupal\oeaw\ConnData::$acdhQueryType){
                            $results['queryType'] = $item->__toString();
                        }
                        
                        $results[$i]["property"] = $this->createPrefixesFromString($v);
                        $results[$i]["value"][] = $item->__toString();
                    }else {
                        if($this->createPrefixesFromString($v) === false){
                            return drupal_set_message(t('Error in function: createPrefixesFromString'), 'error');
                        }
                        $results[$i]["property"] = $this->createPrefixesFromString($v);
                        $results[$i]["value"][] = $item;
                        
                    }
                }
                $i++;                    
            } 
        }


        return $results;
    }
    
    /**
     * 
     * Get the keys from a multidimensional array
     * 
     * @param array $arr
     * @return array
     */
    public function getKeysFromMultiArray(array $arr): array{
     
        foreach($arr as $key => $value) {
            $return[] = $key;
            if(is_array($value)) $return = array_merge($return, $this->getKeysFromMultiArray($value));
        }
        
        //remove the duplicates
        $return = array_unique($return);
        
        //remove the integers from the values, we need only the strings
        foreach($return as $key => $value){
            if(is_numeric($value)) unset($return[$key]);
        }
        
        return $return;
    }
        
    /**
     * Get the title if the url contains the fedoraIDNamespace
     * 
     * 
     * @param string $string
     * @return string
     */
    public function getTitleByTheFedIdNameSpace(string $string): string{
        
        if(!$string) { return false; }
        
        $return = "";
        $OeawStorage = new OeawStorage();
        
        if (strpos($string, 'https://id.acdh.oeaw.ac.at/') !== false) {
            
            $itemRes = $OeawStorage->getDataByProp(RC::get('fedoraIdProp'), $string);
            if(count($itemRes) > 0){
                if($itemRes[0]["title"]){
                    $return = $itemRes[0]["title"];
                }else if($itemRes[0]["label"]){
                    $return = $itemRes[0]["label"];
                }else if($itemRes[0]["name"]){
                    $return = $itemRes[0]["name"];
                }
            }
        }        
        return $return;
    }
     
    /**
     * Get the urls from the Table Detail View, and make an inside URL if it is possible
     * 
     * @param string $string
     * @return string
     */
    public function getFedoraUrlHash(string $string): string{
        if(!$string) { return false; }
        
        $return = "";        
        $OeawStorage = new OeawStorage();
        $urls = array('https://fedora', 'https://id.acdh.oeaw.ac.at/', 'https://redmine.acdh.oeaw.ac.at/');
        
        foreach($urls as $url){            
            if (strpos($string, $url) !== false) {
                
                $itemRes = $OeawStorage->getDataByProp(RC::get('fedoraIdProp'), $string);
                if(count($itemRes) > 0){
                    if($itemRes[0]['uri']){
                        $fedoraUrl = RC::get('fedoraApiUrl');
                        $url = str_replace($fedoraUrl."/", "", $itemRes[0]['uri']);
                        if($url){
                            $return = $this->createDetailsUrl($url);
                        }
                    }
                    
                }
            }
        }
        return $return;
    }
    
    /**
     * 
     * check that the string is URL
     * 
     * @param string $string
     * @return string
     */
    public function isURL(string $string): string{
        
        $res = "";        
        if (filter_var($string, FILTER_VALIDATE_URL)) {
            if (strpos($string, RC::get('fedoraApiUrl')) !== false) {
                $res = $this->createDetailsUrl($string, 'encode');
            }
            return $res;
        } else {
            return false;
        }        
    }

    /**
     * 
     * Creates a property uri based on the prefix
     * 
     * @param string $prefix
     * @return string
     */
    public function createUriFromPrefix(string $prefix): string{
        
        if(empty($prefix)){ return false; }
        
        $res = "";
        
        $newValue = explode(':', $prefix);
        $newPrefix = $newValue[0];
        $newValue =  $newValue[1];
        
        $prefixes = \Drupal\oeaw\ConnData::$prefixesToChange;
        
        foreach ($prefixes as $key => $value){
            if($value == $newPrefix){
                $res = $key.$newValue;
            }
        }        
        return $res;
    }
    
    /**
     * 
     * Array Unique function to multidimensional arrays
     * 
     * @param array $data
     * @param string $key
     * @return array
     */
    public function arrUniqueToMultiArr(array $data, string $key): array{
        
        if(empty($data) || empty($key)){ return array(); }
        
        $return = array();
        
        foreach ($data as $d) {
            $return[] = $d[$key];
        }
        $return = array_unique($return);
        
        return $return;
        
    }
    
}
