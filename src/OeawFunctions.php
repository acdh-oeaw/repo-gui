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
    
    public function createFullTextSparql(array $data, string $limit, string $page, bool $count = false, string $order = "titleasc"): string{

        //Let's process the order argument
        switch ($order) {
            case "titleasc":
                $order = "ASC( fn:lower-case(?title))";
                break;
            case "titledesc":
                $order = "DESC( fn:lower-case(?title))";
                break;
            case "dateasc":
                $order = "ASC(?availableDate)";
                break;
            case "datedesc":
                $order = "DESC(?availableDate)";
                break;
            default:
                $order = "ASC( fn:lower-case(?title))";
        }

        $wordsQuery = "";
        $query = "";
        $prefix = 'PREFIX fn: <http://www.w3.org/2005/xpath-functions#> ';
        if($count == true){
            $select = "SELECT (COUNT(?uri) as ?count) ";
        }else {
            $select = 'SELECT DISTINCT ?uri ?description ?title ?availableDate ?hasTitleImage (GROUP_CONCAT(DISTINCT ?rdfType;separator=",") AS ?rdfTypes) 
                       (GROUP_CONCAT(DISTINCT ?author;separator=",") AS ?authors) 
                       (GROUP_CONCAT(DISTINCT ?contrib;separator=",") AS ?contribs) ';
        }
        
        $conditions = "";
        $query .= "?uri ?prop ?obj . \n
            ?uri <".RC::titleProp()."> ?title . \n
            FILTER( ?prop IN (<".RC::titleProp().">, <".RC::get('drupalHasDescription').">, <".RC::get('drupalHasContributor')."> )) .   \n";
        
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
            $or = false;
            $storage =  new OeawStorage();
            $acdhTypes = $storage->getACDHTypes();
        
            if(count($acdhTypes) > 0){
                $query .= " { ";    
                foreach($td as $dtype){                        
                    foreach($acdhTypes as $t){
                        
                        $val = explode(RC::get('fedoraVocabsNamespace'), $t["type"]);
                        $val = strtolower($val[1]);
                        
                        if($dtype == "or"){ $or = true; continue;}
                        
                        if( ($dtype == "not") || ($dtype == "and") ){
                            continue;
                        }
                        
                        if (strpos(strtolower($dtype), $val) !== false) {
                            if($or == true){$query .= " UNION "; $or = false;}
                            $query .= " { SELECT * WHERE { ?uri <".RC::get('drupalRdfType')."> <".$t['type']."> . } }\n";
                        }
                    }
                }
                $query .= " } ";
            }            
        }
        
        if(isset($data["mindate"]) && isset($data["maxdate"])){
            
            if( (bool)strtotime($data["mindate"])  ){
                $mindate = new \DateTime($data["mindate"]);
            }else  {
                $msg = base64_encode("The Minimum date is wrong!");
                $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
                $response->send();
                return;
            }
            if( (bool)strtotime($data["maxdate"]) ){
                $maxdate = new \DateTime($data["maxdate"]);
            }else  {
                $msg = base64_encode("The Maximum date is wrong!");
                $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
                $response->send();
                return;
            }
            
            
            
            $conditions .= " ?uri <".RC::get('drupalHasAvailableDate')."> ?date . \n";
            //(?date < "2017-10-20T00:00:00+00:00"^^xsd:dateTime && ?date > "2017-05-11T00:00:00+00:00"^^xsd:dateTime) .
            // $query .= "FILTER (?date < '".$maxdate->format(DATE_ATOM)."' ^^xsd:dateTime && ?date > '".$mindate->format(DATE_ATOM)."'^^xsd:dateTime)  \n";
            $query .= "FILTER (str(?date) < '".$maxdate->format('Y-m-d')."' && str(?date) > '".$mindate->format('Y-m-d')."')  \n";
        }
        $query .= "OPTIONAL{ ?uri <".RC::get('drupalHasDescription')."> ?description .}                
    	OPTIONAL{ ?uri <".RC::get('drupalAuthor')."> ?author .}	    	
        OPTIONAL{ ?uri <".RC::get('drupalHasContributor')."> ?contrib .}	
    	OPTIONAL{ ?uri <".RC::get('drupalRdfType')."> ?rdfType . }
        OPTIONAL{ ?uri <".RC::get('drupalHasTitleImage')."> ?hasTitleImage .}                
        OPTIONAL{ ?uri <".RC::get('drupalHasAvailableDate')."> ?availableDate . }";
        
        $query = $prefix.$select." Where { ".$conditions." ".$query." } GROUP BY ?title ?description ?uri ?hasTitleImage ?availableDate ORDER BY " . $order;
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
        $prevlabel = "<i class='material-icons'>&#xE5CB;</i>";
        $nextlabel = "<i class='material-icons'>&#xE5CC;</i>";
        $out = "";
        
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
     * 
     * Create the HTML content of the cite-this widget on single resource view
     * 
     * @param array $resourceData Delivers the properties of the resource
     * @return array $widget Returns the cite-this widget as HTML
     */
    public function createCiteThisWidget(array $resourceData): array {
	    
	    /*
		function input argument: \EasyRdf\Resource $data

	    $OeawStorage = new OeawStorage();
 
		$creatorsData = $data->all(RC::get('drupalHasCreator'));
		$creators = [];
		foreach ($creatorsData as $key => $creator) {
			$uri = $creator->getUri();
			$creators[$key]['hasFirstName'] = $OeawStorage->getPropertyValueByUri($uri, RC::get('drupalHasFirstName'));
			$creators[$key]['hasLastName'] = $OeawStorage->getPropertyValueByUri($uri, RC::get('drupalHasLastName'));
		}
		*/

        /** MLA Format
	     * Example:
         * Wheelis, Mark. "Investigating Disease Outbreaks Under a Protocol to the Biological and Toxin Weapons Convention." Emerging Infectious Diseases, vol. 6, no. 6, 2000, pp. 595-600, wwwnc.cdc.gov/eid/article/6/6/00-0607_article. Accessed 8 Feb. 2009.
         */
        $widget["MLA"] = ["authors" => "", "creators" => "", "contributors" => "", "title" => "", "isPartOf" => "", "availableDate" => "", "hasHosting" => "", "hasEditor" => ""];
		
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
                $widget["MLA"]["isPartOf"] = ' <em>'.$isPartOf.'.</em>';		
        }

        //Get created date
        if (isset($resourceData["table"]["acdh:availableDate"])) {
                $availableDate = $resourceData["table"]["acdh:availableDate"][0];
                $availableDate = strtotime($availableDate);
                $widget["MLA"]["availableDate"] = date('Y',$availableDate);			
        }
        
        $widget["MLA"]["string"] = $widget["MLA"]["creators"].'. "'.$widget["MLA"]["title"].'."'.$widget["MLA"]["isPartOf"].', '.$widget["MLA"]["availableDate"];

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
        
        if($rdfType == RC::get('drupalPerson') ){
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
                        if($val == \Drupal\oeaw\ConnData::$fedoraBinary){
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
                
                if( (get_class($val) == "EasyRdf\Literal") || (get_class($val) == "EasyRdf\Literal\DateTime") ){
                    if(get_class($val) == "EasyRdf\Literal\DateTime"){
                        $dt = $val->__toString();                        
                        $time = strtotime($dt);
                        $result['table'][$propertyShortcut][$key]  = date('F jS, Y', $time);
                    }else{
                        $result['table'][$propertyShortcut][$key] = $val->getValue();
                    }
                    
                    //we dont have the image yet but we have a MIME
                    if( ($p == \Drupal\oeaw\ConnData::$ebucoreMime) && (!isset($result['image'])) && (strpos($val, 'image') !== false) ) {
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
    
}