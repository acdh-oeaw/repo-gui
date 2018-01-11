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
use Drupal\oeaw\OeawCustomSparql;

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
    public function getResourceDissServ(\acdhOeaw\fedora\FedoraResource $fedoraRes): array {
        
        $result = array();
        if($fedoraRes){
            try{
                $id = $fedoraRes->getId();
                $dissServ = $fedoraRes->getDissServices();
                if($dissServ){
                    foreach($dissServ as $k => $v) {
                        $result[$k] = $id;            
                    }
                }
                return $result;
            } catch (Exception $ex) {
                $msg = base64_encode('Error in function: '.__FUNCTION__);
                $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
                $response->send();
                return;
            } catch (\acdhOeaw\fedora\exceptions\NotFound $ex){
                $msg = base64_encode('Error in function: '.__FUNCTION__);
                $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
                $response->send();
                return;
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
        if(isset($currentPage[0]) && isset($currentPage[1])){
            $currentPage = $currentPage[0].'/'.$currentPage[1];
        }else{
            $currentPage = $currentPage[0].'/';
        }
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
     * * Get the Fedora Resource Rules
     * If it is empty, then it is a private resource
     * 
     * @param string $uri
     * @param FedoraResource $fedoraRes
     * @return array
     */
    public function getRules(string $uri, \acdhOeaw\fedora\FedoraResource $fedoraRes): array{
        $result = array();
        
        try{
            $aclObj = $fedoraRes->getAcl();
            $result = $aclObj->getRules();
        }catch (Exception $ex) {
            $msg = base64_encode('Error in function: '.__FUNCTION__);
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        } catch (\acdhOeaw\fedora\exceptions\NotFound $ex){
            $msg = base64_encode('Error in function: '.__FUNCTION__);
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        }
        return $result;
    }
    
    
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
     * @param array $data
     */
    public function createCustomDetailViewTemplateData(array $data, string $type): array {
        $result = array();
        $basicProp = array();
        $extendedProp = array();
        
        if(count($data['table']) > 0){
            
            switch ($type) {
                case "person":
                    $basicProp = array(
                        "acdh:hasTitle",
                        "acdh:hasIdentifier",
                        "acdh:isMember"
                    );
                    
                    //contact details
                    $extendedProp = array(
                        "acdh:hasAddressLine1",
                        "acdh:hasAddressLine2",
                        "acdh:hasCountry",
                        "acdh:hasRegion",
                        "acdh:hasCity",
                        "acdh:hasEmail",
                        "acdh:hasUrl",
                        "acdh:hasPostcode"
                    );
                    
                    break;
                case "project":
                    $basicProp = array(
                        "acdh:hasTitle",
                        "acdh:hasIdentifier",
                        "acdh:hasAlternativeTitle",
                        "acdh:hasUrl",
                        "acdh:hasContact",
                        "acdh:hasFunder",
                        "acdh:hasPrincipalInvestigator",
                        "acdh:hasStartDate",
                        "acdh:hasEndDate",
                        "acdh:hasLifeCycleStatus",
                        "acdh:language"
                    );
                    
                    $extendedProp = array(
                        "acdh:hasRelatedDiscipline",
                        "acdh:hasSubject",
                        "acdh:hasActor",
                        "acdh:hasSpatialCoverage",
                        "acdh:hasTemporalCoverage",
                        "acdh:hasCoverageStartDate",
                        "acdh:hasCoverageEndDate",
                        "acdh:hasAppliedMethod",
                        "acdh:hasAppliedMethodDescription",
                        "acdh:hasTechnicalInfo",
                        "acdh:hasEditorialPractice",
                        "acdh:hasNote"
                    );                    
                    break;
                case "organisation":
                    $basicProp = array(
                        "acdh:hasTitle",
                        "acdh:hasAlternativeTitle",
                        "acdh:hasIdentifier",
                        "acdh:hasAddressLine1",
                        "acdh:hasAddressLine2",
                        "acdh:hasPostcode",
                        "acdh:hasCity",
                        "acdh:hasRegion",
                        "acdh:hasCountry",
                        "acdh:hasUrl",
                        "acdh:hasEmail"
                    );
                    /*
                    $extendedProp = array(
                        "acdh:hasCreator",
                        "acdh:hasAuthor",
                        "acdh:hasEditor",
                        "acdh:hasCurator",
                        "acdh:hasDepositor",
                        "acdh:hasMetadataCreator",
                        "acdh:hasContact"
                    );*/  
                    break;
                case "place":
                    $basicProp = array(
                        "acdh:hasTitle",
                        "acdh:hasAlternativeTitle",
                        "acdh:hasIdentifier",
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
                    
                    $extendedProp = array(
                        "acdh:hasLatitude",
                        "acdh:hasLongitude",
                        "acdh:hasWKT"
                        );
                    break;
                case "publication":
                    $basicProp = array(
                        "acdh:hasTitle",
                        "acdh:hasAlternativeTitle",
                        "acdh:hasIdentifier",
                        "acdh:hasAuthor",
                        "acdh:hasEditor",
                        "acdh:hasSeriesInformation",
                        "acdh:hasPages",
                        "acdh:hasRegion",
                        "acdh:hasCity",
                        "acdh:hasPublisher",
                        "acdh:isPartOf",
                        "acdh:hasNonLinkedIdentifier",
                        "acdh:hasUrl",
                        "acdh:hasEditorialPractice",
                        "acdh:hasNote",
                        "acdh:hasLanguage"
                    );
                    break;
                default:
                break;
        
            }
            if(count($basicProp) > 0){
                foreach($basicProp as $bP) {
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
            }
            
            if(count($extendedProp) > 0){
                //generate the contact data
                foreach ($extendedProp as $prop){
                    if( (isset($data['table'][$prop])) && (count($data['table'][$prop]) > 0) ){
                        $result['extended'][$prop] = $data['table'][$prop];
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
    public function createCiteThisWidget(array $resourceData): array {
        
        $content = [];

        /** MLA Format
	     * Example:
         * Mörth, Karlheinz. Dictionary Gate. ACDH, 2013, hdl.handle.net/11022/0000-0000-001B-2. Accessed 12 Oct. 2017.
         */
        $widget["MLA"] = ["authors" => "", "creators" => "", "contributors" => "", "title" => "", "isPartOf" => "", "availableDate" => "", "hasHosting" => "", "hasEditor" => "", "accesedDate" => "", "acdhURI" => ""];

        //Get authors(s)
        if (isset($resourceData["table"]["acdh:hasAuthor"])) {
            foreach ($resourceData["table"]["acdh:hasAuthor"] as $key => $author) {
                if ($key > 0) {
                    if(isset($author["title"])){
                        $widget["MLA"]["authors"] .= ", " . $author["title"];
                    }else{
                        $widget["MLA"]["authors"] .= ", " . $author;
                    }

                } else {
                    if(isset($author["title"])){
                        $widget["MLA"]["authors"] = $author["title"];
                    }else{
                        $widget["MLA"]["authors"] = $author;
                    }
                }			
            }
        }

        //Get creator(s)
        if (isset($resourceData["table"]["acdh:hasCreator"])) {
            foreach ($resourceData["table"]["acdh:hasCreator"] as $key => $creator) {
                if ($key > 0) {
                    if(isset($creator["title"])){
                        $widget["MLA"]["creators"] .= ", " . $creator["title"];
                    }else{
                        $widget["MLA"]["creators"] .= ", " . $creator;
                    }

                } else {
                    if(isset($creator["title"])){
                        $widget["MLA"]["creators"] = $creator["title"];
                    }else{
                        $widget["MLA"]["creators"] = $creator;
                    }
                }			
            }
        }

        //Get contributor(s) 
        if (isset($resourceData["table"]["acdh:hasContributor"])) {
            foreach ($resourceData["table"]["acdh:hasContributor"] as $key => $contributor) {			
                if ($key > 0) {
                    if(isset($contributor["title"])){
                        $widget["MLA"]["contributors"] .= ", " . $contributor["title"];
                    }else{
                        $widget["MLA"]["contributors"] .= ", " . $contributor;
                    }
                } else {
                    if(isset($contributor["title"])){
                        $widget["MLA"]["contributors"] = $contributor["title"];
                    }else{
                        $widget["MLA"]["contributors"] = $contributor;
                    }
                        
                }			
            }
        }

        //Get title
        if (isset($resourceData["table"]["acdh:hasTitle"])) {
            $title = $resourceData["table"]["acdh:hasTitle"][0];
            $widget["MLA"]["title"] = $title;
        }

        //Get isPartOf		
        if (isset($resourceData["table"]["acdh:isPartOf"])) {
            $isPartOf = $resourceData["table"]["acdh:isPartOf"][0]["title"];
            $widget["MLA"]["isPartOf"] = $isPartOf;		
        }
        
        //Get hasHosting		
        if (isset($resourceData["table"]["acdh:hasHosting"])) {
            $hasHosting = $resourceData["table"]["acdh:hasHosting"][0]["title"];
            $widget["MLA"]["hasHosting"] = $hasHosting;		
        }

        /* Get hasPid & create copy link
         * Order of desired URIs:
         * PID > id.acdh > id.acdh/uuid > long gui url
         */
        if (isset($resourceData["table"]["acdh:hasPid"])) {
            if (isset($resourceData["table"]["acdh:hasPid"][0]['uri'])) {
                $widget["MLA"]["acdhURI"] = $resourceData["table"]["acdh:hasPid"][0]['uri'];
            }
        }
        if (!$widget["MLA"]["acdhURI"]) {
            if (isset($resourceData["table"]["acdh:hasIdentifier"]) && !empty($resourceData["table"]["acdh:hasIdentifier"]) ){
                $acdhURIs = $resourceData["table"]["acdh:hasIdentifier"];
                //Only one value under acdh:hasIdentifier
                if (isset($acdhURIs["uri"])) {
                    //id.acdh/uuid
                    if (strpos($acdhURIs["uri"], 'id.acdh.oeaw.ac.at/uuid') !== false) {
                        $widget["MLA"]["acdhURI"] = $acdhURIs["uri"];
                    }
                    //id.acdh
                    if (!isset($widget["MLA"]["acdhURI"]) && strpos($acdhURIs["uri"], 'id.acdh.oeaw.ac.at') !== false) {
                        $widget["MLA"]["acdhURI"] = $acdhURIs["uri"];
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
                        $widget["MLA"]["acdhURI"] = $acdhURIidacdh;
                    } else if (isset($acdhURIuuid)) {
                        $widget["MLA"]["acdhURI"] = $acdhURIuuid;
                    }
                }
            }
        }

        //Get available date
        if (isset($resourceData["table"]["acdh:hasAvailableDate"])) {
            $availableDate = $resourceData["table"]["acdh:hasAvailableDate"][0];
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
    public function makeMetaData(string $uri): \EasyRdf\Resource{
        
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
            $msg = base64_encode("Resource does not exist!");
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
                $result[$x]['typeName'] = explode(RC::get('fedoraVocabsNamespace'), $data[$x]['types'])[1];
            }
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
        //reorder the array because have missing keys
        $properties = array_values($properties);
        
        //it will be the function for the cache
        //$acdhProp = $OeawStorage->getPropDataToExpertTable($properties);

        $searchTitle = array();
        
        foreach ($properties as $p){
            $propertyShortcut = $this->createPrefixesFromString($p);
            
            foreach ($data->all($p) as $key => $val){
                
                if(get_class($val) == "EasyRdf\Resource" ){
                    $classUri = $val->getUri();
                    $result['table'][$propertyShortcut][$key]['uri'] = $classUri;
                    $result['table'][$propertyShortcut][$key]['title'] = $classUri;
                    
                    //we will skip the title for the resource identifier
                    if($p != RC::idProp() ){
                        //$title = $OeawStorage->getTitleByIdentifier($classUri);
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
                        $result['image'] = $resourceUri;
                    }
                    if( $p == RC::get('fedoraExtentProp') ) {
                        if($val->getValue()){
                            $result['table'][$propertyShortcut][$key] = $this->formatSizeUnits($val->getValue());
                        }
                    }
                }
            }
        }
        
      
        //get the not literal propertys TITLE
        $existinTitles = array();
        $existinTitles = $OeawStorage->getTitlyByIdentifierArray($searchTitle);
        
        $resKeys = array_keys($result['table']);
        //change the titles
        foreach($resKeys as $k){
            foreach($result['table'][$k] as $key => $val){
                if(is_array($val)){
                    foreach($existinTitles as $t){
                        if($t['identifier'] == $val['title']){
                            if($k == "rdf:type"){
                                $result['acdh_'.$k]['title'] = $t['title'];
                                $result['acdh_'.$k]['insideUri'] = base64_encode($t['uri']);
                            }
                            $result['table'][$k][$key]['title'] = $t['title'];
                            $result['table'][$k][$key]['insideUri'] = base64_encode($t['uri']);
                        }
                    }
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
     * Create nice format from file sizes
     * 
     * @param type $bytes
     * @return string
     */
    public function formatSizeUnits(string $bytes): string
    {
        if ($bytes >= 1073741824)
        {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        }
        elseif ($bytes >= 1048576)
        {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        }
        elseif ($bytes >= 1024)
        {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        }
        elseif ($bytes > 1)
        {
            $bytes = $bytes . ' bytes';
        }
        elseif ($bytes == 1)
        {
            $bytes = $bytes . ' byte';
        }
        else
        {
            $bytes = '0 bytes';
        }

        return $bytes;
    }

    
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
    
    /**
     * 
     * This function checks that the Resource is a 3dData or not
     * 
     * @param array $data
     * @return bool
     */
    public function check3dData(array $data): bool{
        $return = false;
       
        if( (isset($data['ebucore:filename'][0])) 
            && 
            ( (strpos(strtolower($data['ebucore:filename'][0]), '.nxs') !== false) 
            || 
            (strpos(strtolower($data['ebucore:filename'][0]), '.ply') !== false) ) 
            &&
            ( isset($data['acdh:hasCategory'][0]) && $data['acdh:hasCategory'][0] =="3dData")    
        )
        {
            $return = true;
        }
        return $return;
    }
    
    /**
     * 
     * Calculate the estimated Download time for the collection
     * 
     * @param int $binarySize
     * @return string
     */
    public function estDLTime(int $binarySize): string{
        
        $result = "";
        if($binarySize < 1){ return $result; }
        
        $kb=1024;
        flush();
        $time = explode(" ",microtime());
        $start = $time[0] + $time[1];
        for( $x=0; $x < $kb; $x++ ){
            str_pad('', 1024, '.');
            flush();
        }
        $time = explode(" ",microtime());
        $finish = $time[0] + $time[1];
        $deltat = $finish - $start;
        
        $input = (($binarySize / 512) * $deltat);
        $input = floor($input / 1000);
        $seconds = $input;
        
        if($seconds > 0){
            //because of the zip time we add
            $result = round($seconds * 1.35) * 4;
            return $result;
        }
        
        return $result;
    }
    
    /**
     * 
     * Generate the collection data for the download view
     * 
     * @param string $uri
     * @return array
     */
    public function genCollectionData(string $uri): array{
        
        $uri = base64_decode($uri);
        $fedora = $this->initFedora();
        $fedoraRes = array();
        $rootMeta = array();
        $resData = array();

        try{
            //get the resource data 
            $fedoraRes = $fedora->getResourceByUri($uri);
            $rootMeta = $fedoraRes->getMetadata();            
            //get title
            $title = $rootMeta->get(RC::get('fedoraTitleProp'));
            //get number of files
            $filesNum = $rootMeta->get(RC::get('fedoraCountProp'));
            //get the sum binary size of the collection
            $binarySize = $rootMeta->get(RC::get('fedoraExtentProp'));
            $license = $rootMeta->get(RC::get('fedoraVocabsNamespace').'hasLicense');
            $isPartOf = $rootMeta->get(RC::get('fedoraRelProp'));

            if(isset($title) && $title->getValue()){
                $resData['title'] = $title->getValue();
            }
            if(isset($filesNum) && $filesNum->getValue()){
                $resData['filesNum'] = $filesNum->getValue();
            }
            if(isset($license) && $license->getValue()){
                $resData['license'] = $license->getValue();
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
                $resData['formattedSize'] = $this->formatSizeUnits($bs);

                //the estimated download time
                $estDLTime = $this->estDLTime($bs);
                if($estDLTime > 0){ $resData['estDLTime'] = $estDLTime; }

                $freeSpace = 0;
                if(!file_exists($_SERVER['DOCUMENT_ROOT'].'/sites/default/files/collections/')){
                    mkdir($_SERVER['DOCUMENT_ROOT'].'/sites/default/files/collections/', 0777);
                }
                //get the free space to we can calculate the zipping will be okay or not?!
                $freeSpace = disk_free_space($_SERVER['DOCUMENT_ROOT'].'/sites/default/files/collections/');

                if($freeSpace){
                    $resData['freeSpace'] = $freeSpace;
                    $resData['formattedFreeSpace'] = $this->formatSizeUnits((string)$freeSpace);

                    if($freeSpace > 1499999999 * 2.2){
                        //if there is no enough free space then we will not allow to DL the collection
                        $resData['dl'] = true;
                    }
                }
            }

            //create the binaries list for the view
            $oeawCustSparql = new OeawCustomSparql();
            $collBinSql = $oeawCustSparql->getCollectionBinaries($uri);
          
            if(!empty($collBinSql)){
                $OeawStorage = new OeawStorage();
                
                $bin = $OeawStorage->runUserSparql($collBinSql);

                if(count($bin) > 0){
                    foreach($bin as $k => $v){
                        if($v['binarySize']){
                            $bin[$k]['formSize'] = $this->formatSizeUnits((string)$v['binarySize']);
                        }
                        if($v['uri']){
                            $bin[$k]['encodedUri'] = base64_encode($v['uri']);
                        }
                        
                        //change the rdf type
                        if (strpos($v['type'], 'https://vocabs.acdh.oeaw.ac.at/schema#') !== false) {
                            $t = explode(",", $v['type']);
                            foreach ($t as $ty){
                                if (strpos($ty, 'https://vocabs.acdh.oeaw.ac.at/schema#') !== false) {
                                    $ty = str_replace("https://vocabs.acdh.oeaw.ac.at/schema#", "", $ty);
                                    $bin[$k]['type'] = $ty;
                                    if($ty == "Resource"){
                                        if($v['title']){
                                            if($v['filename'] && $bin[$k]['formSize']){
                                                $bin[$k]['text'] = $v['filename']." | ".$bin[$k]['formSize'];
                                            }
                                        }
                                        $bin[$k]['dir'] = false;
                                        $bin[$k]['icon'] = "jstree-file";
                                    }else{
                                        if($v['title']){
                                           $bin[$k]['text'] = $v['title'];
                                        }
                                        $bin[$k]['dir'] = true;
                                    }
                                }
                            }
                        }
                    }
                    $resData['binaries'] = $bin;
                }
            }

        } catch (\acdhOeaw\fedora\exceptions\NotFound $ex){
            $errorMSG = "Error during the url parsing";
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            $errorMSG = "Error during the url parsing";
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
    
    
    public function checkArrayForValue(array $data, string $str):bool {
        
        if(count($data) > 0){
            foreach($data as $item){
                if(strpos($item, $str)!== false){
                    return true;
                }
            }
        }
        
        return false;
    }
    
}