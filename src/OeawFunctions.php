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
use Drupal\Core\Cache\CacheBackendInterface;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;


use Drupal\oeaw\Model\OeawStorage;
use Drupal\oeaw\Model\OeawResource;
use Drupal\oeaw\Model\OeawResourceChildren;
use Drupal\oeaw\ConfigConstants as CC;
use Drupal\oeaw\Helper\Helper;
use Drupal\oeaw\Model\OeawCustomSparql;

use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\FedoraResource;
use acdhOeaw\fedora\acl\WebAclRule as WAR;
use acdhOeaw\util\RepoConfig as RC;
use EasyRdf\Graph;
use EasyRdf\Resource;

/**
 * Description of OeawFunctions
 *
 * @author nczirjak
 */
class OeawFunctions {
    
    /**
     * Set up the config file
     * @param type $cfg
     */
    public function __construct($cfg = null){
        if($cfg == null){
            \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');
        }else {
            \acdhOeaw\util\RepoConfig::init($cfg);
        }
        
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
     * Check the data array for the PID, identifier or uuid identifier
     * 
     * @param array $data
     * @return string
     */
    public function createDetailViewUrl(array $data): string {
        //check the PID
        if(isset($data['pid']) && !empty($data['pid'])){
            if (strpos($data['pid'], RC::get('epicResolver')) !== false) {
                return $data['pid'];
            }
        }
        
        if(isset($data['identifier'])){
            //if we dont have pid then check the identifiers
            $idArr = explode(",", $data['identifier']);
            $uuid = "";
            foreach($idArr as $id){
                //the id contains the acdh uuid
                if (strpos($id, RC::get('fedoraUuidNamespace')) !== false) {
                    return $id;
                }
            }
        }
        
        return "";
    }
    
    /**
     * Get the actual child page and limit from the actual url
     * 
     * @param string $data
     * @return array
     */
    public function getLimitAndPageFromUrl(string $data): array {
        if(empty($data)){ return array(); }
        
        $data = explode("&", $data);
        $page = 0;
        $limit = 10;
        foreach($data as $d) {
            if (strpos($d, 'page') !== false) {
                $page = str_replace("page=", "", $d);
            }
            if (strpos($d, 'limit') !== false) {
                $limit = str_replace("limit=", "", $d);
            }
        }
        
        return array("page" => $page, "limit" => $limit);
        
    }
    
    /**
     * 
     * Encode or decode the detail view url
     * 
     * @param string $uri
     * @param bool $code : 0 - decode / 1 -encode
     * @return string
    */
    public function detailViewUrlDecodeEncode(string $data, int $code = 0): string {
        
        if(empty($data)){ return ""; }
        
        if($code == 0) {
            $data = explode(":", $data);
            $page = 0;
            $limit = 0;
            $identifier = "";

            foreach($data as $ra) {
                if (strpos($ra, '&') !== false) {
                    $pos = strpos($ra, '&');
                    $ra = substr($ra, 0, $pos);
                    //$page = str_replace("page=", "", $ra);
                    $identifier .= $ra."/";
                }else {
                    $identifier .= $ra."/";
                }
            }
            
            if (strpos($identifier, 'hdl.handle.net') !== false) {
                $identifier = "http://".$identifier;
            }else {
                $identifier = "https://".$identifier;
            }
            
            if(substr($identifier, -1) == "/") { 
                $identifier = substr_replace($identifier, "", -1); 
            }
            return $identifier;
        }
        
        if($code == 1){
            if (strpos($data, 'hdl.handle.net') !== false) {
                $data = str_replace("http://", "", $data);
            }else if(strpos($data, 'https') !== false) {
                $data = str_replace("https://", "", $data);
            }else {
                $data = str_replace("http://", "", $data);
            }
            return $data;
            //return array($data['identifier']);
        }
        
    }
    
    /**
     * 
     * This function is get the acdh identifier by the PID, because all of the functions
     * are using the identifier and not the pid :)
     * 
     * @param string $identifier
     * @return string
     */
    public function pidToAcdhIdentifier(string $identifier): string {
        $return = "";
        $oeawStorage = new OeawStorage();
        
        try {
            $idsByPid = $oeawStorage->getACDHIdByPid($identifier);
        } catch (Exception $ex) {
            drupal_set_message($ex->getMessage(), 'error');
            return "";
        }catch (\InvalidArgumentException $ex){
            drupal_set_message($ex->getMessage(), 'error');
            return "";
        }
        
        if(count($idsByPid) > 0){
            foreach ($idsByPid as $d){
                if (strpos((string)$d['id'], RC::get('fedoraIdNamespace')) !== false) {
                    $return = $d['id'];
                    break;
                }
            }
        }
        return $return;
    }
    
    /**
     * Get the actual Resource Dissemination services
     * 
     * @param FedoraResource $fedoraRes
     * @return array
     * @throws \Exception
     * @throws \acdhOeaw\fedora\exceptions\NotFound
     */
    public function getResourceDissServ(\acdhOeaw\fedora\FedoraResource $fedoraRes): array {
        
        $result = array();
        if($fedoraRes){
            try{
                $id = $fedoraRes->getId();
                $dissServ = $fedoraRes->getDissServices();

                if(count($dissServ) > 0){
                    $processed = array();
                
                    foreach ($dissServ as $service) {
                        //get the acdh identifiers for the dissemination services
                        if(!in_array($id, $processed)) {
                            $processed[] = $service->getId();
                        }
                    }
                    
                    if(count($processed) > 0){
                        $oeawStorage = new OeawStorage();
                        //get the titles
                        $titles = array();
                        $titles = $oeawStorage->getTitleByIdentifierArray($processed, true);
                        
                        if(count($titles) > 0){
                            $titles = Helper::removeDuplicateValuesFromMultiArrayByKey($titles, "title");
                            $result = $titles;
                        }
                    }
                }
                return $result;
            } catch (Exception $ex) {
                throw new \Exception('Error in function: '.__FUNCTION__.' Error msg: '.$ex->getMessage());
            } catch (\acdhOeaw\fedora\exceptions\NotFound $ex){
                throw new \acdhOeaw\fedora\exceptions\NotFound('Error in function: '.__FUNCTION__.' Error msg: '.$ex->getMessage());
            }
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
        
        $filters = array("type", "dates", "words", "mindate", "maxdate", "years");
        //$operands = array("and" => "+", "not" => "-");
        $positions = array();
        
        $res = array();
        
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
     * Creates a string from the currentPage For the pagination
     * 
     * @return string
     */
    public function getCurrentPageForPagination(): string{
        
        $currentPath = "";
        $currentPage = "";
        
        $currentPath = \Drupal::service('path.current')->getPath();
        $currentPage = substr($currentPath, 1);
        $currentPage = explode("/", $currentPage);
        if(isset($currentPage[0]) && isset($currentPage[1])){
            $currentPage = $currentPage[0].'/'.$currentPage[1];
        }else{
            $currentPage = $currentPage[0].'/';
        }
        return $currentPage;
    }
    
    /**
     * Create a rawurlencoded string from the users entered search string
     * 
     * @param string $string
     * @param array $extras
     * @return string
     */
    public function convertSearchString(string $string, array $extras = null): string{
      
        $filters = array("type", "date", "words",);
        $operands = array("or", "not");
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
                        $tyStr .= "or+".$t."+";
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
                    $tyStr .= "or+";
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
        $prevlabel = "<i class='material-icons'>&#xE5CB;</i>";
        $nextlabel = "<i class='material-icons'>&#xE5CC;</i>";
        $out = "";
        $actualPage;
        $tpages = $tpages;
        // previous
        if ($page == 0) {
	        //Don't show prev if we are on the first page
            //$out.= "<li class='pagination-item'><span>" . $prevlabel . "</span></li>";
        } else {
            $out.= "<li class='pagination-item'><a data-pagination='" . $page . "'>" . $prevlabel . "</a>\n</li>";
        }

        $pmin = ($page > $adjacents) ? ($page - $adjacents) : 1;
        $pmax = ($page < ($tpages - $adjacents)) ? ($page + $adjacents) : $tpages;
        
        for ($i = $pmin; $i <= $pmax; $i++) {
            if ($i-1 == $page) {
                $out.= "<li class='pagination-item active'><a data-pagination='".$i."'>" . $i . "</a></li>\n";
            } else {
                $out.= "<li class='pagination-item'><a data-pagination='" . $i . "'>" . $i . "</a>\n</li>";
            }
        }

        // next
        if ($page < $tpages-1) {
            $out.= "<li class='pagination-item'><a data-pagination='" . ($page + 2) . "'>" . $nextlabel . "</a>\n</li>";
        } else {
	        //Don't show next if we are on the last page
            //$out.= "<li class='pagination-item'><span style=''>" . $nextlabel . "</span></li>";
        }
        
        if ($page < ($tpages - $adjacents)) {
            $out.= "<li class='pagination-item'><a data-pagination='" . $tpages . "'><i class='material-icons'>&#xE5DD;</i></a></li>";
        }
        $out.= "";
        
        return $out;
    }
    

    /**
     * 
     * Check the Resource Rules and display the users/grants
     * 
     * @param array $rules
     * @return array
     * 
     */
    public function checkRules(array $rules): array{
        
        $rights = array();
        //if we dont have rights then we have some error in the fedora db, so we 
        // will automatically adding the READ rights for the resource
        if(count($rules) == 0){
            $rights['username'] = "user";
            $rights['mode'][] = "READ";
        }else {
            $i = 0;
            //check the rules
            foreach($rules as $r){
                if( $r->getRoles(\acdhOeaw\fedora\acl\WebAclRule::USER) ){
                    $rights['username'] = "user";
                }
                else if( $r->getRoles(\acdhOeaw\fedora\acl\WebAclRule::GROUP) ){
                    $rights['username'] = "group";
                }
                
                switch ($r->getMode(\acdhOeaw\fedora\acl\WebAclRule::WRITE)) {
                    case \acdhOeaw\fedora\acl\WebAclRule::READ :
                        $rights['mode'][] = "READ"; 
                        break;
                    case \acdhOeaw\fedora\acl\WebAclRule::WRITE :
                        $rights['mode'][] = "WRITE";
                        break;
                    default:
                        $rights['mode'][] = "NONE";
                }
            }
        }
        
        if(count($rights) == 0 || $this->checkMultiDimArrayForValue('NONE', $rights) == true){
            throw new \Exception("You have no rights to check the Resource!");
        }
        return $rights;
    }
    
    /**
     * 
     * Get the Fedora Resource Rules
     * If it is empty, then it is a private resource
     * 
     * @param string $uri
     * @param FedoraResource $fedoraRes
     * @return array
     */
    public function getRules(string $uri, \acdhOeaw\fedora\FedoraResource $fedoraRes): array{
        $result = array();
        
        try{
            $aclObj = $fedoraRes->getAcl()->getRules();
        }catch (Exception $ex) {
            throw new \Exception('Error in function: '.__FUNCTION__.' Error: '.$ex->getMessage());
        } catch (\acdhOeaw\fedora\exceptions\NotFound $ex){
            throw new \acdhOeaw\fedora\exceptions\NotFound('Error in function: '.__FUNCTION__.' Error: '.$ex->getMessage());
        }
        return $result;
    }
    
    /**
     * 
     * Add access to the user on the actual resource
     * 
     * @param string $uri
     * @param string $user
     * @param Fedora $fedora
     * @return array
     */
    public function grantAccess(string $uri, string $user, \acdhOeaw\fedora\Fedora $fedora): array{
        $result = array();
        
        $fedora->begin();
        
        try{
            $res = $fedora->getResourceByUri($uri);
        } catch (Exception $ex) {
            return array();
        }catch (\acdhOeaw\fedora\exceptions\NotFound $ex){
            return array();
        }
        
        $aclObj = $res->getAcl();
        $aclObj->grant(\acdhOeaw\fedora\acl\WebAclRule::USER, $user, \acdhOeaw\fedora\acl\WebAclRule::READ);
        $aclObj = $res->getAcl();
        $result = $aclObj->getRules();        
        $fedora->commit();
        
        return $result;

    }   
    
    /**
     * Remove the user rules from the resource
     * 
     * @param string $uri
     * @param string $user
     * @param Fedora $fedora
     * @return array
     */
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
     * This functions create the Concept template data for the basic view
     * 
     * @param array $data
     * @return array
     */
    public function createPlacesTemplateData(array $data): array {
        $result = array();
        
        if(count($data['table']) > 0){
             //basic
            $basicPropertys = array(
                "acdh:hasTitle",
                "acdh:hasIdentifier",
                "acdh:hasAlternativeTitle",                
                "acdh:hasAddressLine1",
                "acdh:hasAddressLine2",
                "acdh:hasPostcode",
                "acdh:hasCity",
                "acdh:hasRegion",
                "acdh:hasCountry",
                "acdh:hasPart",
                "acdh:isPartOf",
                "acdh:isIdenticalTo"
            );
            
            foreach($basicPropertys as $bP) {
                if( (isset($data['table'][$bP])) && (count($data['table'][$bP]) > 0) ){
                    foreach($data['table'][$bP] as $val){
                        if($bP == "acdh:hasIdentifier"){
                            if (strpos($val['uri'], 'id.acdh.oeaw.ac.at') == false) {
                                $result['basic'][$bP][] = $val;
                            }
                        }else {
                            $result['basic'][$bP][] = $val;
                        }
                    }
                }
            }
            if(isset($data['acdh_rdf:type'])){
                $result['basic']['acdh_rdf:type'] = $data['acdh_rdf:type'];
            }
            
            //contact details
            $spatialProperties = array(
                "acdh:hasLatitude",
                "acdh:hasLongitude",
                "acdh:hasWKT"
            );
            
            //generate the contact data
            foreach ($spatialProperties as $prop){
                if( (isset($data['table'][$prop])) && (count($data['table'][$prop]) > 0) ){
                    $result['spatial'][$prop] = $data['table'][$prop];
                }
            }
        }
        return $result;
    }
    
    
    /**
     * This functions create the Project template data for the basic view
     * 
     * @param OeawResource $data
     * @param string $type
     * @return \Drupal\oeaw\Model\OeawResourceCustomData
     * @throws \ErrorException
     */
    public function createCustomDetailViewTemplateData(\Drupal\oeaw\Model\OeawResource $data, string $type): \Drupal\oeaw\Model\OeawResourceCustomData {
        
        //check the table data in the object that we have enough data :)
        if(count($data->getTable()) > 0){
            //set the data for the resource custom data object
            $arrayObject = new \ArrayObject();
            $arrayObject->offsetSet('uri', $data->getUri());
            $arrayObject->offsetSet('insideUri', $data->getInsideUri());
            $arrayObject->offsetSet('fedoraUri', $data->getFedoraUri());
            $arrayObject->offsetSet('identifiers', $data->getIdentifiers());
            $arrayObject->offsetSet('title', $data->getTitle());
            $arrayObject->offsetSet('type', $data->getType());
            $arrayObject->offsetSet('typeUri', $data->getTypeUri());
            if(!empty($data->getPID())){
                $arrayObject->offsetSet('pid', $data->getPID());
            }
            if(!empty($data->getAccessRestriction())){
                $arrayObject->offsetSet('accessRestriction', $data->getAccessRestriction());
            }
            
            if(!empty($data->getType())){
                $arrayObject->offsetSet('acdh_rdf:type', $data->getType() );
            }
            
            try {
                //get the obj
                $obj = new \Drupal\oeaw\Model\OeawResourceCustomData($arrayObject);
                $obj->setupBasicExtendedData($data);
            } catch (\ErrorException $ex) {
                throw new \ErrorException($ex->getMessage());
            }
        }
        
        return $obj;
    }
    
    /**
     * 
     * Convers the sparql result contributors, authors, creators data to fit our spec. Obj
     * 
     * @param array $data
     * @return array
     */
    public function createContribAuthorData(array $data): array {
        $result = array();
        $oeawStorage = new OeawStorage();
        foreach ($data as $d) {
            $title = $oeawStorage->getTitleByIdentifier($d);
            if(count($title) > 0){
                if(!empty($title[0]['title'])) {
                    $result[] = array("title" => $title[0]['title'], "insideUri" => $this->detailViewUrlDecodeEncode($d, 1));
                }
            }
        }
        return $result;
        
    }

    /**
     * 
     * Get the necessary data for the CITE Widget based on the properties
     * 
     * @param array $data - resource data array
     * @param string $property - shortcur property - f.e.: acdh:hasCreator
     * @return string - a string with the available data
     */
    private function getCiteWidgetData(\Drupal\oeaw\Model\OeawResource $data, string $property): string{
        $result = "";
        
        if(count((array)$data) > 0){
            if (!empty($data->getTableData($property))) {
                foreach ($data->getTableData($property) as $key => $val) {
                    if (count($data->getTableData($property)) > 1) {
                        if(isset($val["title"])){
                            $result .= ", " . $val["title"];
                        }else{
                            $result .= ", " . $val;
                        }
                    } else {
                        if(isset($val["title"])){
                            $result = $val["title"];
                        }else if(isset($val["uri"])){
                            $result = $val["uri"];
                        }
                        else{
                            $result = $val;
                        }
                    }
                }
            }
        }
        return $result;
    }
    
    /**
     * 
     * Create the HTML content of the cite-this widget on single resource view
     * 
     * @param array $resourceData Delivers the properties of the resource
     * @return array $widget Returns the cite-this widget as HTML
     */
    public function createCiteThisWidget(\Drupal\oeaw\Model\OeawResource $resourceData): array {
        
        $content = [];

        /** MLA Format
	     * Example:
         * Mörth, Karlheinz. Dictionary Gate. ACDH, 2013, hdl.handle.net/11022/0000-0000-001B-2. Accessed 12 Oct. 2017.
         */
        $widget["MLA"] = ["authors" => "", "creators" => "", "contributors" => "", "title" => "", "isPartOf" => "", "availableDate" => "", "hasHosting" => "", "hasEditor" => "", "accesedDate" => "", "acdhURI" => ""];

        //Get authors(s)
        $authors = "";
        $authors = $this->getCiteWidgetData($resourceData, "acdh:hasAuthor");
        if(!empty($authors) ){
            $widget["MLA"]["authors"] = $authors;
        }
        
        //Get creator(s)
        $creators = "";
        $creators = $this->getCiteWidgetData($resourceData, "acdh:hasCreator");
        if(!empty($creators) ){
            $widget["MLA"]["creators"] = $creators;
        }
        
        //Get contributor(s) 
        $contributors = "";
        $contributors = $this->getCiteWidgetData($resourceData, "acdh:hasContributor");
        if(!empty($creators) ){
            $widget["MLA"]["contributors"] = $contributors;
        }

        //Get title
        if (!empty($resourceData->getTitle())) {
            $widget["MLA"]["title"] = $resourceData->getTitle();
        }

        //Get isPartOf		
        if (!empty($resourceData->getTableData("acdh:isPartOf"))) {
            $isPartOf = $resourceData->getTableData("acdh:isPartOf")[0]["title"];
            $widget["MLA"]["isPartOf"] = $isPartOf;		
        }
        
        //Get hasHosting		
        if (!empty($resourceData->getTableData("acdh:hasHosting"))) {
            $hasHosting = $resourceData->getTableData("acdh:hasHosting")[0]["title"];
            $widget["MLA"]["hasHosting"] = $hasHosting;		
        }

        /* Get hasPid & create copy link
         * Order of desired URIs:
         * PID > id.acdh > id.acdh/uuid > long gui url
         */
        if (!empty($resourceData->getPID())) {
            $widget["MLA"]["acdhURI"] = $resourceData->pid;
        }
        
        if (!$widget["MLA"]["acdhURI"]) {
            if (!empty($resourceData->getIdentifiers()) && count($resourceData->getIdentifiers()) > 0 ){
                $acdhURIs = $resourceData->getIdentifiers();
                //Only one value under acdh:hasIdentifier
                
                $uuid = "";
                
                foreach($acdhURIs as $id){
                    //the id contains the acdh uuid
                    if (strpos($id, RC::get('fedoraUuidNamespace')) !== false) {
                        $uuid = $id;
                        //if the identifier is the normal acdh identifier then return it
                    }else if (strpos($id, RC::get('fedoraIdNamespace')) !== false) {
                        $uuid = $id;
                        break;
                    }
                }
                $widget["MLA"]["acdhURI"] = $uuid;
            }
        }

        //Get available date
        if (!empty($resourceData->getTableData("acdh:hasAvailableDate"))) {
            $availableDate = $resourceData->getTableData("acdh:hasAvailableDate")[0];
            $availableDate = strtotime($availableDate);
            $widget["MLA"]["availableDate"] = date('Y',$availableDate);
        }
        
         //Get accesed date
        $widget["MLA"]["accesedDate"] = date('d M Y');			

        
        //Process MLA
        //Top level resource
        //if (!$widget["MLA"]["isPartOf"]) {

        $widget["MLA"]["string"] = "";
        //AUTHORS
        if ($widget["MLA"]["authors"]) { $widget["MLA"]["string"] .= $widget["MLA"]["authors"].'. '; }
        else if ($widget["MLA"]["creators"]) { $widget["MLA"]["string"] .= $widget["MLA"]["creators"].'. '; }
        else if ($widget["MLA"]["contributors"]) { $widget["MLA"]["string"] .= $widget["MLA"]["contributors"].'. '; }

        //TITLE
        if ($widget["MLA"]["title"]) { $widget["MLA"]["string"] .= '<em>'.$widget["MLA"]["title"].'.</em> '; }

        //PUBLISHER
        if ($widget["MLA"]["hasHosting"]) { $widget["MLA"]["string"] .= $widget["MLA"]["hasHosting"].', '; }

        //DATE
        if ($widget["MLA"]["availableDate"]) { $widget["MLA"]["string"] .= $widget["MLA"]["availableDate"].', '; }

        //HANDLE
        if ($widget["MLA"]["acdhURI"]) { $widget["MLA"]["string"] .= $widget["MLA"]["acdhURI"].'. '; }

        //DATE
        if ($widget["MLA"]["accesedDate"]) { $widget["MLA"]["string"] .= 'Accessed '.$widget["MLA"]["accesedDate"].'. '; }

        /*
        } else {
            //Only cite top level collections for now
            return $content;
        }
        */

        return $widget;
	}

    /**
     * 
     * Creates the EasyRdf_Resource by uri
     * 
     * @param string $uri
     * @return \EasyRdf\Resource
     */
    public function makeMetaData(string $uri): \EasyRdf\Resource {
        
        if(empty($uri)){
            return drupal_set_message(t('Resource does not exist!'), 'error');
        }
        
        $meta = array();
       // setup fedora
        $fedora = new Fedora();
         try{
            $meta = $fedora->getResourceByUri($uri);
            $meta = $meta->getMetadata();
        } catch (\acdhOeaw\fedora\exceptions\NotFound $ex){
            throw new \acdhOeaw\fedora\exceptions\NotFound("Resource does not exist!");
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            throw new \GuzzleHttp\Exception\ClientException($ex->getMessage());
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
        }catch ( \Exception $ex){
            throw new \Exception($ex->getMessage());
        }     
        catch (\acdhOeaw\fedora\exceptions\NotFound $ex){
            throw new \acdhOeaw\fedora\exceptions\NotFound("Resource does not exist!");
        } catch (\acdhOeaw\fedora\exceptions\Deleted $ex){
            throw new \acdhOeaw\fedora\exceptions\Deleted($ex->getMessage());
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            throw new \GuzzleHttp\Exception\ClientException($ex->getMessage());
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
    public function createSparqlResult(\EasyRdf\Sparql\Result $result, array $fields): array {
        
        if(empty($result) && empty($fields)){
            drupal_set_message(t('Error in function: '.__FUNCTION__), 'error');
            return array();
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
     * create prefix from string based on the  prefixes     
     * 
     * @param string $string
     * @return string
     */
    public static function createPrefixesFromString(string $string): string {
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
                
        if(!empty(CC::$prefixesToChange[$newString])){
            $result = CC::$prefixesToChange[$newString].':'.$endValue;
        }else {
            $result = $string;
        }
        return $result;
    }

    
    /**
     * 
     * create prefix from array based on the ConfigConstants.php prefixes     
     * 
     * @param array $array
     * @param array $header
     * @return array
     */
    public function createPrefixesFromArray(array $array, array $header): array {
        
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
                 
                if(!empty(CC::$prefixesToChange[$newString])){            
                    $result[$key][] = CC::$prefixesToChange[$newString].':'.$endValue;
                }else {
                    $result[$key][] = $value;
                }
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
    public function createChildrenViewData(array $data): array {
        $result = array();
        
        if(count($data) == 0) {   
            throw new \ErrorException("There is no any children data");
        }
        
        foreach($data as $d){
            
            $id = $this->createDetailViewUrl($d);
            $arrayObject = new \ArrayObject();
            
            $arrayObject->offsetSet('uri', $d['uri']);
            $arrayObject->offsetSet('title', $d['title']);
            $arrayObject->offsetSet('pid', $d['pid']);
            $arrayObject->offsetSet('description', $d['description']);
            $arrayObject->offsetSet('types', $d['types']);
            $arrayObject->offsetSet('identifier', $d['identifier']);
            $arrayObject->offsetSet('insideUri', $this->detailViewUrlDecodeEncode($id, 1));
            $arrayObject->offsetSet('accessRestriction', $d['accessRestriction']);
            
            if(isset($d['uri'])){
                $arrayObject->offsetSet('typeName', explode(RC::get('fedoraVocabsNamespace'), $d['types'])[1]);
            }
            $result[] = new \Drupal\oeaw\Model\OeawResourceChildren($arrayObject);
        }

        return $result;
    }
    

    /**
     * 
     * this will generate an array with the Resource data.
     * The Table will contains the resource properties with the values in array.
     * 
     * There will be also some additional data:
     * - resourceTitle -> the Main Resource Title
     * - uri -> the Main Resource Uri
     * - insideUri -> the base64_encoded uri to the gui browsing
     * 
     * @param Resource $data
     * @return array
     */
    public function createDetailViewTable(\EasyRdf\Resource $data): \Drupal\oeaw\Model\OeawResource{
        
        $result = array();
        $arrayObject = new \ArrayObject();
        $OeawStorage = new OeawStorage();

        if(empty($data)){
            return drupal_set_message(t('Error in function: '.__FUNCTION__), 'error');
        }
        
        //get the resource Title
        $resourceTitle = $data->get(RC::get('fedoraTitleProp'));
        $resourceUri = $data->getUri();
        $resourceIdentifiers = $data->all(RC::get('fedoraIdProp'));
        $resourceIdentifier = Helper::getAcdhIdentifier($resourceIdentifiers);
        
        $rsId = array();
        $uuid = "";
        if(count($resourceIdentifiers) > 0){
            foreach ($resourceIdentifiers as $ids){
                if (strpos($ids->getUri(), RC::get('fedoraUuidNamespace')) !== false) {
                   $uuid =  $ids->getUri();
                }
                $rsId[] = $ids->getUri();
            }
        }
        
        //get the resources and remove fedora properties
        $properties = array();
        $properties = $data->propertyUris();
        foreach ($properties as $key => $val){
            if (strpos($val, 'fedora.info') !== false) {
                unset($properties[$key]);
            }
        }
        
        //reorder the array because have missing keys
        $properties = array_values($properties);
        $searchTitle = array();

        foreach ($properties as $p){
            
            $propertyShortcut = $this->createPrefixesFromString($p);
            //get the properties data from the easyrdf resource object
            foreach ($data->all($p) as $key => $val){
                
                if(get_class($val) == "EasyRdf\Resource" ){
                    $classUri = $val->getUri();
                    
                    if($p == RC::get("drupalRdfType"));{
                        if (strpos($val->__toString(), 'vocabs.acdh.oeaw.ac.at') !== false) {
                            $result['acdh_rdf:type']['title'] = $val->localName();
                            $result['acdh_rdf:type']['insideUri'] = $this->detailViewUrlDecodeEncode($val->__toString(), 1);   
                            $result['acdh_rdf:type']['uri'] = $val->__toString();
                        }
                    }
                    $result['table'][$propertyShortcut][$key]['uri'] = $classUri;
                    
                    //we will skip the title for the resource identifier
                    if($p != RC::idProp() ){
                        //this will be the proper
                        $searchTitle[] = $classUri;
                    }
                    //if the acdhImage is available or the ebucore MIME
                    if($p == RC::get("drupalRdfType")){
                        if($val == RC::get('drupalHasTitleImage')){
                            $result['image'] = $resourceUri;
                        }
                        //check that the resource has Binary or not
                        if($val == RC::get('drupalFedoraBinary')){
                            $result['hasBinary'] = $resourceUri;
                        }
                        
                        if($val == RC::get('drupalMetadata') ){
                            $invMeta = $OeawStorage->getMetaInverseData($resourceUri);
                            if(count($invMeta) > 0){
                                $result['isMetadata'] = $invMeta;
                            }
                        }
                    }
                    
                    //simply check the acdh:hasTitleImage for the root resources too.
                    if($p == RC::get('drupalHasTitleImage')){
                        $imgUrl = "";
                        $imgUrl = $OeawStorage->getImageByIdentifier($val->getUri());
                        if($imgUrl){
                            $result['image'] = $imgUrl;
                        }
                    }
                }
                
                if( (get_class($val) == "EasyRdf\Literal") || 
                        (get_class($val) == "EasyRdf\Literal\DateTime") || 
                        (get_class($val) == "EasyRdf\Literal\Integer")){
                    
                    if(get_class($val) == "EasyRdf\Literal\DateTime"){
                        $dt = $val->__toString();                        
                        $time = strtotime($dt);
                        $result['table'][$propertyShortcut][$key]  = date('Y-m-d', $time);
                    }else{
                        $result['table'][$propertyShortcut][$key] = $val->getValue();
                    }
                    
                    //we dont have the image yet but we have a MIME
                    if( ($p == RC::get('drupalEbucoreHasMime')) && (!isset($result['image'])) && (strpos($val, 'image') !== false) ) {
                        //if we have image/tiff then we need to use the loris
                        if($val == "image/tiff"){
                            $lorisImg = array();
                            $lorisImg = Helper::generateLorisUrl(base64_encode($resourceUri), true);
                            if(count($lorisImg) > 0){
                                $result['image'] = $lorisImg['imageUrl'];
                            }
                        }else {
                            $result['image'] = $resourceUri;
                        }
                    }
                    if( $p == RC::get('fedoraExtentProp') ) {
                        if($val->getValue()){
                            $result['table'][$propertyShortcut][$key] = Helper::formatSizeUnits($val->getValue());
                        }
                    }
                }
            }
        }
        
        if(count($searchTitle) > 0){
            //get the not literal propertys TITLE
            $existinTitles = array();
            $existinTitles = $OeawStorage->getTitleByIdentifierArray($searchTitle);
            $resKeys = array_keys($result['table']);

            //change the titles
            foreach($resKeys as $k){
                foreach($result['table'][$k] as $key => $val){
                    if(is_array($val)){
                        foreach($existinTitles as $t){
                            
                            if($t['identifier'] == $val['uri'] || $t['pid'] == $val['uri'] || $t['uuid'] == $val['uri']){
                                $result['table'][$k][$key]['title'] = $t['title'];
                                
                                $decodId = "";
                                if(isset($t['pid']) && !empty($t['pid'])){
                                    $decodId = $t['pid'];
                                }else if(isset($t['uuid']) && !empty($t['uuid'])){
                                    $decodId = $t['uuid'];
                                }else if(isset($t['identifier']) && !empty($t['identifier'])){
                                    $decodId = $t['identifier'];
                                }
                                
                                if(!empty($decodId)){
                                    $result['table'][$k][$key]['insideUri'] = $this->detailViewUrlDecodeEncode($decodId, 1);
                                }
                            }
                        }
                    }
                }
            }
        }
        if(empty($result['acdh_rdf:type']['title']) || !isset($result['acdh_rdf:type']['title'])){
            throw new \ErrorException("There is no acdh rdf type!", 0);
        }
      
        $result['resourceTitle'] = $resourceTitle;
        $result['uri'] = $resourceUri;
        $result['insideUri'] = $this->detailViewUrlDecodeEncode($resourceIdentifier, 1);
        
        $arrayObject->offsetSet('table', $result['table']);
        $arrayObject->offsetSet('title', $resourceTitle->__toString());
        $arrayObject->offsetSet('uri', $this->detailViewUrlDecodeEncode( $resourceIdentifier, 0));
        $arrayObject->offsetSet('type', $result['acdh_rdf:type']['title'] );
        $arrayObject->offsetSet('typeUri', $result['acdh_rdf:type']['uri'] );
        $arrayObject->offsetSet('acdh_rdf:type', array("title" => $result['acdh_rdf:type']['title'], "insideUri" => $result['acdh_rdf:type']['insideUri']));
        $arrayObject->offsetSet('fedoraUri', $resourceUri);
        $arrayObject->offsetSet('identifiers', $rsId);
        if(isset($result['table']['acdh:hasAccessRestriction']) && !empty($result['table']['acdh:hasAccessRestriction'][0]) ){
            $arrayObject->offsetSet('accessRestriction', $result['table']['acdh:hasAccessRestriction'][0]);
        }
        $arrayObject->offsetSet('insideUri', $this->detailViewUrlDecodeEncode( $uuid, 1));
        if(isset($result['image'])){
            $arrayObject->offsetSet('imageUrl', $result['image']);
        }
        try {
            $obj = new \Drupal\oeaw\Model\OeawResource($arrayObject);
        } catch (ErrorException $ex) {
            throw new \ErrorException("The resource object initialization failed!", 0);
        }
        
        return $obj;
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
            }
        }
        return $return;
    }
    
    /**
     * Get the title if the url contains the fedoraIDNamespace or the viaf.org ID
     * 
     * @param string $string
     * @return array
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
     * 
     * Check the value in the array
     * 
     * @param type $needle -> strtolower value of the string
     * @param type $haystack -> the array where the func should serach
     * @param type $strict
     * @return boolean
     */
    function checkMultiDimArrayForValue($needle, $haystack, $strict = false) {
        foreach ($haystack as $item) {
            if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && $this->checkMultiDimArrayForValue($needle, $item, $strict))) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check value in array, recursive mode
     * 
     * @param string $needle
     * @param array $haystack
     * @param bool $strict
     * @param array $keys
     * @return bool
     */
    function in_array_r(string $needle, array $haystack, bool $strict = false, array &$keys): bool {
        foreach ($haystack as $key => $item) {
            if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && $this->in_array_r($needle, $item, $strict, $keys))) {
                //we checking only the propertys
                if (strpos($key, ':') !== false) {
                    $keys[$key] = $needle;
                }
                return true;
            }
        }
        return false;
    }
    
    /**
     * Generate the collection data for the download view
     * 
     * @param string $id
     * @return array
     */
    public function generateCollectionData(string $id): array{
                
        $fedora = $this->initFedora();
        $fedoraRes = array();
        $rootMeta = array();
        $resData = array();
        
        //if the id is not an acdh id
        if (strpos($id, RC::get('fedoraIdNamespace')) === false) {
            return $resData;
        }

        try{
            //get the resource data 
            $fedoraRes = $fedora->getResourceById($id);
            $rootMeta = $fedoraRes->getMetadata();            
            $uri = $rootMeta->getUri();
            //get title
            $title = $rootMeta->get(RC::get('fedoraTitleProp'));
            //get number of files
            $filesNum = $rootMeta->get(RC::get('fedoraCountProp'));
            //get the sum binary size of the collection
            $binarySize = $rootMeta->get(RC::get('fedoraExtentProp'));
            $license = $rootMeta->get(RC::get('fedoraVocabsNamespace').'hasLicense');
            $isPartOf = $rootMeta->get(RC::get('fedoraRelProp'));
            $accessRestriction = $rootMeta->get(RC::get('fedoraAccessRestrictionProp'));
            
            if(isset($title) && $title->getValue()){
                $resData['title'] = $title->getValue();
            }
            if(isset($accessRestriction) && $accessRestriction->getValue()){
                $resData['accessRestriction'] = $accessRestriction->getValue();
            }
            if(isset($filesNum) && $filesNum->getValue()){
                $resData['filesNum'] = $filesNum->getValue();
            }
            if(isset($license)){
                $objClass = get_class($license);
                if($objClass == "EasyRdf\Resource"){
                    $resData['license'] = $license->getUri();
                }else if($objClass == "EasyRdf\Literal"){
                    $resData['license'] = $license->__toString();
                } 
            }
            if(isset($isPartOf) && $isPartOf->getUri()){
                $OeawStorage = new OeawStorage();
                $isPartTitle = $OeawStorage->getParentTitle($isPartOf->getUri());
                if(count($isPartTitle) > 0){
                    if($isPartTitle[0]["title"]){
                        $resData['isPartOf'] = $isPartTitle[0]["title"];
                    }
                }
            }
            
            //if we have binary size
            if(isset($binarySize) && $binarySize->getValue()){
                $bs = 0;
                $bs = $binarySize->getValue();
                $resData['binarySize'] = $bs;
                //formatted binary size for the gui
                $resData['formattedSize'] = Helper::formatSizeUnits($bs);

                //the estimated download time
                $estDLTime = Helper::estDLTime($bs);
                if($estDLTime > 0){ $resData['estDLTime'] = $estDLTime; }

                $freeSpace = 0;
                if(!file_exists($_SERVER['DOCUMENT_ROOT'].'/sites/default/files/collections/')){
                    mkdir($_SERVER['DOCUMENT_ROOT'].'/sites/default/files/collections/', 0777);
                }
                //get the free space to we can calculate the zipping will be okay or not?!
                $freeSpace = disk_free_space($_SERVER['DOCUMENT_ROOT'].'/sites/default/files/collections/');

                if($freeSpace){
                    $resData['freeSpace'] = $freeSpace;
                    $resData['formattedFreeSpace'] = Helper::formatSizeUnits((string)$freeSpace);

                    if($freeSpace > 1499999999 * 2.2){
                        //if there is no enough free space then we will not allow to DL the collection
                        $resData['dl'] = true;
                    }
                }
            }

            $resData["uri"] = $id;
            $resData["fedoraUri"] = $uri;
            
            //check the cache
            $cacheData = array();
            $cache = new CollectionCache();
            
            if($cache->getCachedData($uri)){
                $cacheData = $cache->getCachedData($uri);
            }else{
                $cacheData = $cache->setCacheData($uri);
            }
            
            if(count($cacheData) > 0){
                foreach($cacheData as $k => $v){
                    if($v['binarySize']){
                        $cacheData[$k]['formSize'] = Helper::formatSizeUnits((string)$v['binarySize']);
                    }
                    if(isset($v['accessRestriction'])){
                        if(empty($v['accessRestriction'])){ $v['accessRestriction'] = "public"; }
                        $cacheData[$k]['accessRestriction'] = $v['accessRestriction'];
                    }

                    if($v['identifier']){
                        $dtUri = $this->createDetailViewUrl($v);
                        $dtUri = $this->detailViewUrlDecodeEncode($dtUri, 1);
                        $cacheData[$k]['encodedUri'] = $dtUri;
                    }

                    if(!empty($v['filename']) && $v['binarySize'] > 0){
                        $cacheData[$k]['text'] = $v['filename']." | ".$cacheData[$k]['formSize'];
                        $cacheData[$k]['dir'] = false;
                        $cacheData[$k]['icon'] = "jstree-file";
                    }else{
                        $cacheData[$k]['text'] = $v['title'];
                         $cacheData[$k]['dir'] = true;
                    }
                    //if there is no text then it could be a wrong binary
                    //so we will remove it from the list
                    if(empty($cacheData[$k]['text'])){
                        unset($cacheData[$k]);
                    }
                }
                $resData['binaries'] = $cacheData;
            }

        } catch (\acdhOeaw\fedora\exceptions\NotFound $ex){
            return array();
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            return array();
        }
        return $resData;
    }
    
    /**
     * 
     * THis func is generating a child based array from a single array
     * 
     * @param array $flat
     * @param type $idField
     * @param type $parentIdField
     * @param type $childNodesField
     * @return type
     */
    public function convertToTree(
        array $flat, $idField = 'id', $parentIdField = 'parentId',
        $childNodesField = 'children') {
        
        $indexed = array();
        // first pass - get the array indexed by the primary id  
        foreach ($flat as $row) {
            $indexed[$row[$idField]] = $row;
            $indexed[$row[$idField]][$childNodesField] = array();
        }
   
        //second pass  
        $root = null;
        foreach ($indexed as $id => $row) {
            $indexed[$row[$parentIdField]][$childNodesField][] =& $indexed[$id];
            if (!$row[$parentIdField] || empty($row[$parentIdField])) {
                
                $root = $id;
            }
        }
        return array($indexed[$root]);
    }
    
    
    /**
     * Generate the resource child data and some pagination data also
     * 
     * @param array $identifiers - Resource acdh:hasIdentifier
     * @param array $data - Resource metadata
     * @param array $properties - actual uri and for pagination: limit, page 
     * @return array with children array, type and currentpage
     * 
     */
    public function generateChildViewData(array $identifiers, \Drupal\oeaw\Model\OeawResource $data, array $properties): array{
        
        $result = array();
        if( (count($identifiers) == 0 ) || (count((array)$data) == 0 ) || (count($properties) == 0) ){
            return $result;
        }
        
        $countData = array();
        $typeProperties = array();
        $oeawStorage = new OeawStorage();
        $specialType = "child";
        $currentPage = $this->getCurrentPageForPagination();
        
        //we checks if the acdh:Person is available then we will get the Person Detail view data
        if(!empty($data->getType()) && !empty($data->getTypeUri())){
            if(in_array(strtolower($data->getType()), CC::$availableCustomViews)){
                $specialType = $data->getType();
                $typeProperties = CC::getDetailChildViewProperties($data->getTypeUri());
                if(count($typeProperties) > 0){
                    $countData = $oeawStorage->getSpecialDetailViewData($properties['identifier'][0], $properties['limit'], $properties['page'], true, $typeProperties);
                }
            }else{
                if(count($countData) == 0) {
                    $countData = $oeawStorage->getChildrenViewData($identifiers, $properties['limit'], $properties['page'], true);   
                }
            }
        }
       
        $total = (int)count($countData);
        if($properties['limit'] == "0") { $pagelimit = "10"; } else { $pagelimit = $properties['limit']; }
        //create data for the pagination                
        $pageData = $this->createPaginationData($pagelimit, (int)$properties['page'], $total);

        if ($pageData['totalPages'] > 1) {
           $result["pagination"] =  $this->createPaginationHTML($currentPage, $pageData['page'], $pageData['totalPages'], $pagelimit);
        }
 
        if(in_array(strtolower($specialType), CC::$availableCustomViews)){
            $childrenData = $oeawStorage->getSpecialDetailViewData($properties['identifier'][0], $pagelimit, $pageData['end'], false, $typeProperties);
        }else{
            //there is no special children view, so we are using the the default children table
            $childrenData = $oeawStorage->getChildrenViewData($identifiers, $pagelimit, $pageData['end']);
        }

        //we have children data so we will generate the view for it
        if(count($childrenData) > 0){
            try {
                $result["childResult"] = $this->createChildrenViewData($childrenData);
            } catch (Exception $ex) {
                throw new \ErrorException($ex->getMessage());
            }
        }
        $result["currentPage"] = $currentPage;
        $result["specialType"] = $specialType;
        
        return $result;
    }
    
    
    public function generateChildAPIData(string $identifier, int $limit, int $page, string $order): array {
        
        $result = array();
        $countData = array();
        $typeProperties = array();
        $oeawStorage = new OeawStorage();
        $specialType = "child";
        //get the main resource data
        $resType = $oeawStorage->getTypeByIdentifier($identifier);
        
        if(count($resType) == 0) { return array(); }
        
        $typeUri = $resType[0]['type'];
        $type = str_replace(RC::get('fedoraVocabsNamespace'), '', $resType[0]['type']);
        
        //we checks if the acdh:Person is available then we will get the Person Detail view data
        if(!empty($type) && !empty($typeUri)){
            if(in_array(strtolower($type), CC::$availableCustomViews)){
                $specialType = $type;
                $typeProperties = CC::getDetailChildViewProperties($typeUri);
                if(count($typeProperties) > 0){
                    $countData = $oeawStorage->getSpecialDetailViewData($identifier, 0, $page, true, $typeProperties);
                    $result["maxPage"] = count($countData);
                }
            }else{
                if(count($countData) == 0) {
                    $countData = $oeawStorage->getChildrenViewData(array($identifier), 0, $page, true);   
                    $result["maxPage"] = count($countData);
                }
            }
        }
       
        $total = (int)count($countData);
        if($limit == "0") { $limit = "10"; }
        if($page != "0" && $page != "1") {  $offset = ($page - 1)  * $limit; } else { $offset = 0; }
        
        if(in_array(strtolower($specialType), CC::$availableCustomViews)) {
            //$childrenData = $oeawStorage->getSpecialDetailViewData($identifier, $limit, $offset, false, $typeProperties);
            $childrenData = $oeawStorage->getSpecialDetailViewData($identifier, $limit, $offset, false, $typeProperties, "en", $order);
        }else {
            //there is no special children view, so we are using the the default children table
            //$childrenData = $oeawStorage->getChildrenViewData(array($identifier), $limit, $offset);
            $childrenData = $oeawStorage->getChildrenViewData(array($identifier), $limit, $offset, false, "en", $order);
        }
        
        //we have children data so we will generate the view for it
        /*if(count($childrenData) > 0){
            try {
               // $result["childResult"] = $this->createChildrenViewData($childrenData);
            } catch (Exception $ex) {
                throw new \ErrorException($ex->getMessage());
            }
        }
        */
        
        $cacheId = str_replace(RC::get('fedoraUuidNamespace'), '', $identifier);
        $maxPageCh = \Drupal::cache()->get('oeaw.dV'.$cacheId.'.maxPage');
        
        if($maxPageCh === false) {
            \Drupal::cache()->set('oeaw.dV'.$cacheId.'.maxPage', $countData, CacheBackendInterface::CACHE_PERMANENT);
            $maxPage = \Drupal::cache()->get('oeaw.dV'.$cacheId.'.maxPage');
            $result["maxPage"] = $maxPage->data;
        }
        
        
         //we have children data so we will generate the view for it
        if(count($childrenData) > 0){
            try {
                $result["childResult"] = $this->createChildrenViewData($childrenData);
            } catch (Exception $ex) {
                throw new \ErrorException($ex->getMessage());
            }
        }
        
        $result["currentPage"] = $page;
        $result["currentLimit"] = $limit;
        $result["specialType"] = $specialType;
        
        return $result;
    }
}