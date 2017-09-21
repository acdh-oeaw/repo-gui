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
     * Get the actual Resource Dissemination services
     * 
     * @param string $uri
     * @return array
     */
    public function getResourceDissServ(string $uri): array {
        
        $result = array();
        
        $fedora = $this->initFedora();
        $res = $fedora->getResourceByUri($uri); //or any other way to get the FedoraResource object
        $id = $res->getId();
        foreach($res->getDissServices() as $k => $v) {
            $result[$k] = $id;            
        }
        
        return $result;
    }
    
    /**
     * 
     * Create the data for the pagination function
     * 
     * @param int $limit
     * @param int $page
     * @param int $total
     * @return array
     * 
     */
    public function createPaginationData(int $limit, int $page, int $total): array {
        
        $totalPages = 0;
        $res = array();
        
        if($limit == 0){
            $totalPages = 0;
        }else {
            $totalPages = ceil( $total / $limit ) ;
        }

        if(isset($page) && $page != 0){
            if ($page > 0 && $page <= $totalPages) {
                $start = ($page - 1) * $limit;
                $end = $page * $limit;
            } else {
                // error - show first set of results
                $start = 0;
                $end = $limit;
            }
        }else {
            // if page isn't set, show first set of results
            $start = 0;
            $end = 0;
            $page = 0;
        }   
        
        $res["start"] = $start;
        $res["end"] = $end;
        $res["page"] = $page;
        $res["totalPages"] = $totalPages;
        
        return $res;
    }
    
    /**
     * 
     * Prepare the searchString for the sparql Query
     * 
     * @param string $string
     * @return array
     */
    public function explodeSearchString(string $string): array{
        
        $filters = array("type", "dates", "words", "mindate", "maxdate");
        //$operands = array("and" => "+", "not" => "-");
        $positions = array();
        
        $res = "";
        
        $strArr = explode('&', $string);
                
        foreach($filters as $f){
            foreach($strArr as $arr){
                if (strpos($arr, $f) !== false) {
                    $arr = str_replace($f.'=', '', $arr);
                    if( ($f == "mindate") || ($f == "maxdate") ){
                        $arr = str_replace('+', '', $arr);
                    }
                    $res[$f] = $arr;
                }
            }
        }
        return $res;
    }
    
    /**
     * 
     * Creates a string from the currentPage For the pagination
     * 
     * @return string
     * 
     */
    public function getCurrentPageForPagination(): string{
        $currentPath = "";
        $currentPage = "";
        
        $currentPath = \Drupal::service('path.current')->getPath();
        $currentPage = substr($currentPath, 1);
        $currentPage = explode("/", $currentPage);        
        $currentPage = $currentPage[0].'/'.$currentPage[1];
        return $currentPage;
    }
    
    
    /**
     * 
     * Create a rawurlencoded string from the users entered search string
     * 
     * @param string $string
     * @return string
     */        
    public function convertSearchString(string $string, array $extras = null): string{
        
        $filters = array("type", "date", "words",);
        $operands = array("and", "not");
        $positions = array();
        
        $res = "";
        $string = strtolower($string);
        $string = str_replace(' ', '+', $string);
        //get the filters actual position in the string
        foreach($filters as $f){
            if(strpos($string, $f)){
                $positions[$f] = strpos($string.':', $f);
            }
        }
        if(empty($positions) && !empty($string)){
            $positions["words"] = 0;
        }
        //sort them by value to get the right order in the text
        asort($positions);

        $keys = array_keys($positions);

        $newStrArr = array();
        //create the type array
        foreach(array_keys($keys) as $k ){
            $thisVal = $positions[$keys[$k]];
            if($k == 0){
                //add the first line
                $newStrArr["words"] = substr($string, 0, $thisVal);
            }
            
            if($positions[$keys[$k+1]]){
                $nextVal = $positions[$keys[$k+1]];
                $newStrArr[$keys[$k]] =  substr($string, $thisVal, $nextVal - $thisVal);
            }else {
                $newStrArr[$keys[$k]] =  substr($string, $thisVal);
            }
        }
        
        $dtStr = "";
        $tyStr = "";
        $wsStr = "";
                
        if(isset($newStrArr["words"])){
            $wdStr = strtolower($newStrArr["words"]);
            $wdStr = "words=".$wdStr;
            $res = $wdStr;
        }

        if(isset($newStrArr["type"])){
            $tyStr = strtolower($newStrArr["type"]);
            if(isset($extras["type"])){
                foreach($extras["type"] as $t){
                    if (strpos($tyStr, $t) == false) {
                        $tyStr .= "and+".$t."+";
                    }
                }
            }
            
            $tyStr = str_replace('type:', 'type=', $tyStr);
            
            if(!empty($tyStr)){
                $res = $res."&".$tyStr;
            }
            
        } elseif (isset($extras["type"])){
            $tyStr .="type=";
            
            $count = count($extras["type"]);
            $i = 0;
            foreach($extras["type"] as $t){
                if (strpos($tyStr, $t) == false) {
                    $tyStr .= "".$t."+";
                }
                if($i != $count -1){
                    $tyStr .= "and+";
                }                
                $i++;
            }
            $res = $res."&".$tyStr;
        }
        
        //date format should be: mindate=20160101&maxdate=20170817
        if(isset($newStrArr["date"])){
            $dtStr = strtolower($newStrArr["date"]);
            $dtStr = str_replace('date:[', 'mindate=', $dtStr);
            $dtStr = str_replace(']', '', $dtStr);
            $dtStr = str_replace(' ', '', $dtStr);
            $dtStr = str_replace('+to+', '&maxdate=', $dtStr);
            $newStrArr["date"] = $dtStr;
            if(!empty($res)){
                $res = $res."&".$dtStr;
            }
        }elseif (isset($extras["start_date"]) && isset($extras["end_date"])){
            $mindate = date("Ymd", strtotime($extras['start_date']));
            $maxdate = date("Ymd", strtotime($extras['end_date']));
        
            $res = $res."&mindate=".$mindate."&maxdate=".$maxdate;
        }
        
        $res = str_replace('+&', '&', $res);
        
        return $res;    
    }
    
    public function createFullTextSparql(array $data, string $limit, string $page, bool $count = false): string{
        
        $wordsQuery = "";
        $query = "";
        if($count == true){
            $select = "SELECT (COUNT(?uri) as ?count) ";
        }else {
            $select = 'SELECT ?uri ?prop ?obj ?description (GROUP_CONCAT(DISTINCT ?rdfType;separator=",") AS ?rdfTypes) 
                       (GROUP_CONCAT(DISTINCT ?author;separator=",") AS ?authors) 
                       (GROUP_CONCAT(DISTINCT ?contrib;separator=",") AS ?contribs) ';
        }
        
        $conditions = "";
        $query .= "?uri ?prop ?obj . \n
            FILTER( ?prop IN (<".RC::titleProp().">, <".\Drupal\oeaw\ConnData::$description.">, <".\Drupal\oeaw\ConnData::$contributor."> )) .   \n";
        
        if(isset($data["words"])){
            $wd = explode('+', $data["words"]);
            $not = false;
            
            foreach ($wd as $w){

                if($w == "and"){ continue; }

                if($w == "not"){
                    $not = true;
                    continue;
                }
                if($not == true){
                    $query .= "FILTER (!contains(lcase(?obj), lcase('".$w."' ))) .  \n";
                    $not = false;
                }else {
                    $query .= "FILTER (contains(lcase(?obj), lcase('".$w."' ))) .  \n";
                }
            }
        }
        
        //check the rdf types from the query
        if(isset($data["type"])){
            
            $td = explode('+', $data["type"]);
            $not = false;
            $storage =  new OeawStorage();
            $acdhTypes = $storage->getACDHTypes();
            

            if(count($acdhTypes) > 0){
                foreach($td as $dtype){                        
                    foreach($acdhTypes as $t){
                        
                        $val = explode('https://vocabs.acdh.oeaw.ac.at/#', $t["type"]);
                        $val = strtolower($val[1]);
                        
                        if($dtype == "and"){ continue; }
                        
                        if($dtype == "not"){                        
                            $not = true;
                            continue;
                        }
                        
                        if (strpos(strtolower($dtype), $val) !== false) {
                            if($not == true){
                                $query .= "filter not exists { SELECT * WHERE { ?uri <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <".$t['type']."> . } }\n";                            
                                $not = false;
                            }else {
                                $query .= "filter exists { SELECT * WHERE { ?uri <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <".$t['type']."> . } }\n";
                            }
                        }
                    }
                }
            }            
        }
        
        if(isset($data["mindate"]) && isset($data["maxdate"])){
            $mindate = new \DateTime($data["mindate"]);
            $maxdate = new \DateTime($data["maxdate"]);
            
            $conditions .= " ?uri <http://fedora.info/definitions/v4/repository#lastModified> ?date . \n";
            //(?date < "2017-10-20T00:00:00+00:00"^^xsd:dateTime && ?date > "2017-05-11T00:00:00+00:00"^^xsd:dateTime) .
            $query .= "FILTER (?date < '".$maxdate->format(DATE_ATOM)."' ^^xsd:dateTime && ?date > '".$mindate->format(DATE_ATOM)."'^^xsd:dateTime)  \n";
        }
        $query .= "OPTIONAL{ ?uri <https://vocabs.acdh.oeaw.ac.at/#hasDescription> ?description .}                
    	OPTIONAL{ ?uri <https://vocabs.acdh.oeaw.ac.at/#hasAuthor> ?author .}	    	
        OPTIONAL{ ?uri <https://vocabs.acdh.oeaw.ac.at/#hasContributor> ?contrib .}	
    	OPTIONAL {?uri <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> ?rdfType . }";
        
        $query = $select." Where { ".$conditions." ".$query." } GROUP BY ?uri ?prop ?obj ?description  ORDER BY ?obj ";
        if($limit){
            $query .= " LIMIT ".$limit." ";
            
            if($page){
                $query .= " OFFSET ".$page." ";
            }
        }        

        return $query;
    }
    
    
    /**
     * 
     * create the page navigation html code
     * 
     * @param type $actualPage
     * @param type $page
     * @param type $tpages
     * @param type $limit
     * @return string
     */
    public function createPaginationHTML(string $actualPage, string $page, $tpages, $limit): string {
       
        $adjacents = 2;
        $prevlabel = "&lsaquo; Prev";
        $nextlabel = "Next &rsaquo;";
        $out = "";
        
        $tpages = $tpages -1;
        // previous
        if ($page == 0) {
            $out.= "<li style='display: block; float:left; padding: 5px;'><span>" . $prevlabel . "</span></li>";
        } elseif ($page == 1) {
            $out.= "<li style='display: block; float:left; padding: 5px;'><a  href='/browser/" .$actualPage."/" .$limit . "/".$page."'>" . $prevlabel . "</a></li>";
        } else {
            $out.= "<li style='display: block; float:left; padding: 5px;'><a  href='/browser/".$actualPage."/" .$limit . "/" . ($page - 1) . "'>" . $prevlabel . "</a>\n</li>";
        }

        $pmin = ($page > $adjacents) ? ($page - $adjacents) : 0;
        $pmax = ($page < ($tpages - $adjacents)) ? ($page + $adjacents) : $tpages;
        
        for ($i = $pmin; $i <= $pmax; $i++) {
            if ($i == $page) {
                $out.= "<li  style='display: block; float:left; padding: 5px;'  class=\"active\"><a href=''>" . $i . "</a></li>\n";
            } elseif ($i == 0) {
                $out.= "<li style='display: block; float:left; padding: 5px;'><a  href='/browser/".$actualPage."/" .$limit . "/'>" . $i . "</a>\n</li>";
            } else {
                $out.= "<li style='display: block; float:left; padding: 5px;'><a  href='/browser/".$actualPage."/" .$limit . "/" . $i . "'>" . $i . "</a>\n</li>";
            }
        }

        // next
        if ($page < $tpages) {
            $out.= "<li style='display: block; float:left; padding: 5px;'><a  href='/browser/".$actualPage."/" .$limit . "/" . ($page + 1) . "'>" . $nextlabel . "</a>\n</li>";
        } else {
            $out.= "<li style='display: block; float:left; padding: 5px;'><span style=''>" . $nextlabel . "</span></li>";
        }
        
        if ($page < ($tpages - $adjacents)) {
            $out.= "<li style='display: block; float:left; padding: 5px;'>Last Page: <a style='' href='/browser/".$actualPage."/" .$limit . "/" . $tpages . "'>" . $tpages . "</a></li>";
        }
        $out.= "";
        
        return $out;
    }
    

    public function checkRules(array $rules): array{
        $ACL = array();
        
        //check the rules
        if(count($rules) == 0){
            $msg = base64_encode("The Resource is private");
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        }else {            
            $i = 0;
            //check the rules            
            foreach($rules as $r){
                foreach($r->users as $u){
                    if($u == \acdhOeaw\fedora\acl\WebAclRule::PUBLIC_USER){
                        $ACL[$i]['username'] = "Public User";
                        $ACL[$i]['user'] = base64_encode($u);
                    }else {
                        $ACL[$i]['username'] = $u;
                        $ACL[$i]['user'] = base64_encode($u);
                    }
                    
                    switch ($r->mode) {
                        case 1:
                            $ACL[$i]['mode'] = "READ";
                            break;
                        case 2:
                            $ACL[$i]['mode'] = "WRITE";
                            break;
                        default:
                            $ACL[$i]['mode'] = "NONE";
                    }
                    $i++;
                }
            }
        }
        
        return $ACL;
    }
    
    /**
     * 
     * Get the Fedora Resource Rules
     * If it is empty, then it is a private resource
     * 
     * @param string $uri
     * @return type
     */
    public function getRules(string $uri, \acdhOeaw\fedora\Fedora $fedora): array{
        $result = array();
                
        $fedora->begin();        
        $res = $fedora->getResourceByUri($uri);        
        $aclObj = $res->getAcl();
        $result = $aclObj->getRules();
        $fedora->commit();
       
        return $result;
    }
    
    
    public function grantAccess(string $uri, string $user, \acdhOeaw\fedora\Fedora $fedora): array{
        $result = array();
        
        $fedora->begin();        
        $res = $fedora->getResourceByUri($uri);
        $aclObj = $res->getAcl();
        $aclObj->grant(\acdhOeaw\fedora\acl\WebAclRule::USER, $user, \acdhOeaw\fedora\acl\WebAclRule::READ);
        $aclObj = $res->getAcl();
        $result = $aclObj->getRules();        
        $fedora->commit();
        
        return $result;

    }   
    
    public function revokeRules(string $uri, string $user, \acdhOeaw\fedora\Fedora $fedora): array{
        $result = array();
        
        $fedora->begin();        
        $res = $fedora->getResourceByUri($uri);
        $aclObj = $res->getAcl();
        $aclObj->revoke(\acdhOeaw\fedora\acl\WebAclRule::USER, $user, \acdhOeaw\fedora\acl\WebAclRule::READ);
        $aclObj->revoke(\acdhOeaw\fedora\acl\WebAclRule::USER, $user, \acdhOeaw\fedora\acl\WebAclRule::WRITE);
        $aclObj = $res->getAcl();
        $result = $aclObj->getRules();        
        $fedora->commit();
        
        return $result;
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
     * Creating an array from the vocabsNamespace
     * 
     * @param string $string
     * @return array
     * 
     * 
     */
    public function createStrongFromACDHVocabs(string $string): array {
        if (empty($string)) { return false; }
        
        $result = array();
        
        if (strpos($string, RC::vocabsNmsp()) !== false) {
            $result['rdfType']['typeUri'] = $string;
            $result['rdfType']['typeName'] = str_replace(RC::vocabsNmsp(), '', $string);
        }
        
        return $result;
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
     * Check the special RDF types and generate the special labels for them.
     * 
     * @param string $rdfType
     * @param Resource $rootMeta
     * @return string
     */
    public function checkSpecialRdfType(string $rdfType, \EasyRdf\Resource $rootMeta): string{
        $result = "";
        
        if($rdfType == \Drupal\oeaw\ConnData::$person ){
            $fn = $rootMeta->get(\Drupal\oeaw\ConnData::$hasFirstName);
            $ln = $rootMeta->get(\Drupal\oeaw\ConnData::$hasLastName);
            
            if($fn && $ln){
                return $result = $fn.' '.$ln;
            }             
        }
        
        return $result;
    }
    
    /**
     * 
     * Format data to children array
     * 
     * @param array $data
     * @return array
     * 
     */
    public function createChildrenViewData(array $data): array{
        
        $result = array();
        if(count($data) < 0){ return $result; }
        
        for ($x = 0; $x <= count($data) - 1; $x++) {
            $result[$x] = $data[$x];
            $result[$x]['insideUri'] = base64_encode($data[$x]['uri']);
            if(isset($data[$x]['uri'])){
                $result[$x]['typeName'] = explode('https://vocabs.acdh.oeaw.ac.at/#', $data[$x]['types'])[1];
            }
        }
        
        return $result;
    }
    
    /**
     * 
     * OLD FUNCTION
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
            $childResult[$i]['title']= $r->getMetadata()->get(\Drupal\oeaw\ConnData::$title);
            $childResult[$i]['description'] = $r->getMetadata()->get(\Drupal\oeaw\ConnData::$description);
            $rdfType = $r->getMetadata()->all(\Drupal\oeaw\ConnData::$rdfType);
            if (isset($rdfType) && $rdfType) {
                foreach ($rdfType as $type) {
                    if (preg_match("/vocabs.acdh.oeaw.ac.at/", $type)) {
                        $childResult[$i]["rdfType"] = explode('https://vocabs.acdh.oeaw.ac.at/#', $type)[1];
                        $childResult[$i]["rdfTypeUri"] = "/oeaw_classes_result/" . base64_encode('acdh:'.$childResult[$i]["rdfType"]);
                        //Add a space between capital letters
                        $childResult[$i]["rdfType"] = preg_replace('/(?<! )(?<!^)[A-Z]/',' $0', $childResult[$i]["rdfType"]);
                        break;
                    }
                }  						
            }
                
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
     * this will generate an array with the Resource data.
     * The Table will contains the resource properties with the values in array.
     * 
     * There will be also some additonal data:
     * - resourceTitle -> the Main Resource Title
     * - uri -> the Main Resource Uri
     * - insideUri -> the base64_encoded uri to the gui browsing
     * 
     * @param Resource $data
     * @return array
     */
    public function createDetailViewTable(\EasyRdf\Resource $data): array{
        $result = array();
        
        $OeawStorage = new OeawStorage();
        
        if(empty($data)){
            return drupal_set_message(t('Error in function: '.__FUNCTION__), 'error');
        }
        //get the resource Title
        $resourceTitle = $data->get(RC::get('fedoraTitleProp'));
        $resourceUri = $data->getUri();
        
        //get the resources and remove fedora properties
        $properties = array();
        $properties = $data->propertyUris();
              
        foreach ($properties as $key => $val){
            if (strpos($val, 'fedora.info') !== false) {
                unset($properties[$key]);
            }
        }
        
        foreach ($properties as $p){
            $propertyShortcut = $this->createPrefixesFromString($p);
            
            foreach ($data->all($p) as $key => $val){
                
                if(get_class($val) == "EasyRdf\Resource" ){
                    $classUri = $val->getUri();
                    $result['table'][$propertyShortcut][$key]['uri'] = $classUri;
                    
                    //we will skip the title for the resource identifier
                    if($p != RC::idProp() || ( ($p == RC::idProp()) && (strpos($classUri, 'id.acdh.oeaw.ac.at') == false) ) ){
                        $title = $OeawStorage->getTitleByIdentifier($classUri);
                    }
                    //add the title to the resources
                    if(count($title) > 0){
                        if($p == \Drupal\oeaw\ConnData::$rdfType){
                            $result['acdh_'.$propertyShortcut]['title'] = $title[0]['title'];
                            $result['acdh_'.$propertyShortcut]['insideUri'] = base64_encode($title[0]['uri']);
                        }
                        //we will skip the identifer, there we do not need the title
                        if($p != RC::idProp() || ( ($p == RC::idProp()) && (strpos($classUri, 'id.acdh.oeaw.ac.at') == false) ) ){
                            $result['table'][$propertyShortcut][$key]['title'] = $title[0]['title'];
                            $result['table'][$propertyShortcut][$key]['insideUri'] = base64_encode($title[0]['uri']);
                        }                        
                    }
                    /*
                    if($p == \Drupal\oeaw\ConnData::$rdfType){
                        echo $val;
                        echo "<br>";
                        echo $key;
                    }
                */
                    
                }
                
                if(get_class($val) == "EasyRdf\Literal" ){
                    $result['table'][$propertyShortcut][$key] = $val->getValue();
                }
                
                
            }
        }
        
        $result['resourceTitle'] = $resourceTitle;
        $result['uri'] = $resourceUri;
        $result['insideUri'] = base64_encode($resourceUri);
        
        return $result;
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
        $specLbl = "";
        if(count($rootMeta) > 0){
            $i = 0;
            
            foreach($rootMeta->propertyUris($uri) as $v){
                $x = 0;
                foreach($rootMeta->all($v) as $item){
            
                    // if there is a thumbnail
                    if($v == \Drupal\oeaw\ConnData::$imageThumbnail){
                        if($item){                                                    
                            $imgData = $OeawStorage->getImage($item);
                            if($imgData){                                
                                $results["image"] = $imgData;
                            }
                        }
                    }else if($v == \Drupal\oeaw\ConnData::$rdfType){
                        if($item == \Drupal\oeaw\ConnData::$imageProperty){
                            $hasImage = $uri;
                            $results["image"] = $uri;
                        }
                        
                        //if we have an acdh namespace in the rdftype
                        if (strpos($item, \Drupal\oeaw\ConnData::$acdhNamespace) !== false) {
                            //then we need to check the special rdf types
                            //f.e. Person, and so on
                            $specLbl = $this->checkSpecialRdfType($item, $rootMeta);
                            if($specLbl){
                                $results["specialLabel"] = $specLbl;
                            }
                        }
                    }                    
                    // thumbnail end
                    
                    if(get_class($item) == "EasyRdf\Resource"){
                        if($this->createPrefixesFromString($v) === false){
                            return drupal_set_message(t('Error in function: createPrefixesFromString'), 'error');
                        }
                        
                        //check the title based on the acdh id
                        if($item->getUri()){                            
                            $resVal = $item->getUri();
                            //get the resource title
                            $property = $this->createPrefixesFromString($v);
                            $propertyRep = str_replace(":","_",$property);
                            
                            // we dont need the title for the identifiers
                            if ($v != RC::idProp()) {                                
                                if(count($this->getTitleByTheFedIdNameSpace($resVal)) > 0 ){
                                    $resValTitle = "";
                                    $resValTitle = $this->getTitleByTheFedIdNameSpace($resVal);

                                    //we have a title for the resource
                                    if($resValTitle){
                                        $results[$propertyRep]["title"][$x] = $resValTitle[0]["title"];
                                    }
                                }
                            }
                            
                            $results[$propertyRep]["property"] = $property;
                            $results[$propertyRep]["value"][] = $resVal;
                           
                            //create the HASH URL for the table value
                            if($this->getFedoraUrlHash($resVal)){
                                $results[$propertyRep]["inside_url"][] = $this->getFedoraUrlHash($resVal);
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
                        
                        $property = $this->createPrefixesFromString($v);
                        $propertyRep = str_replace(":","_",$property);
                        $results[$propertyRep]["property"] = $property;
                        $results[$propertyRep]["value"][] = $item->__toString();
                    }else {
                        if($this->createPrefixesFromString($v) === false){
                            return drupal_set_message(t('Error in function: createPrefixesFromString'), 'error');
                        }
                        $property = $this->createPrefixesFromString($v);
                        $propertyRep = str_replace(":","_",$property);
                        $results[$propertyRep]["property"] = $property;
                        $results[$propertyRep]["value"][] = $item;
                    }
                    $x++;
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
     * 
     * Get the Resource Title by the uri
     * 
     * @param string $string
     * @return boolean
     * 
     */
    public function getTitleByUri(string $string){
        if(!$string) { return false; }
        
        $return = "";
        $OeawStorage = new OeawStorage();
        
        $itemRes = $OeawStorage->getResourceTitle($string);

        if(count($itemRes) > 0){
            if($itemRes[0]["title"]){
                $return = $itemRes[0]["title"];                
            }else if($itemRes[0]["firstName"] && $itemRes[0]["lastName"]){
                $return = $itemRes[0]["firstName"] . " " . $itemRes[0]["lastName"];
            }else if($itemRes[0]["contributor"]){
                $return = $itemRes[0]["contributor"];
            }
        }
        return $return;
    }
    
        
    /**
     * Get the title if the url contains the fedoraIDNamespace or the viaf.org ID
     * 
     * 
     * @param string $string
     * @return string
     */
    public function getTitleByTheFedIdNameSpace(string $string): array{
        
        if(!$string) { return false; }
        
        $return = array();
        $OeawStorage = new OeawStorage();
            
        $itemRes = $OeawStorage->getTitleByIdentifier($string);

        if(count($itemRes) > 0){
            $return = $itemRes;
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
                            $return = base64_encode($url);
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
                $res = base64_encode($string);
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