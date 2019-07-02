<?php

namespace Drupal\oeaw\Helper;

use Drupal\oeaw\OeawFunctions;
use Drupal\oeaw\Model\OeawStorage;
use Drupal\oeaw\Helper\HelperFunctions as HF;
use Drupal\oeaw\Model\OeawResourceCustomData;
use acdhOeaw\util\RepoConfig as RC;
use Drupal\oeaw\Cache\BreadcrumbCache;
use Drupal\oeaw\Cache\PropertyTableCache;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DetailViewFunctions
{
    private $langConf;
    private $fedoraResource;
    private $fedoraMetadata;
    private $oeawFunctions;
    private $oeawStorage;
    private $breadcrumbCache;
    private $propertyTableCache;
    
    public function __construct(
        $langConf,
        \Drupal\oeaw\OeawFunctions $oeawFunctions,
        \Drupal\oeaw\Model\OeawStorage $oeawStorage,
        \Drupal\oeaw\Cache\PropertyTableCache $propertyTableCache
    ) {
        \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');
        $this->langConf = $langConf;
        $this->oeawFunctions = $oeawFunctions;
        $this->oeawStorage = $oeawStorage;
        $this->breadcrumbCache = new BreadcrumbCache();
        $this->propertyTableCache = $propertyTableCache;
    }
    
    /**
     * Get the actual child page and limit from the actual url
     *
     * @param string $data
     * @return array
     */
    public function getLimitAndPageFromUrl(string $data): array
    {
        if (empty($data)) {
            return array();
        }
        
        $data = explode("&", $data);
        $page = 0;
        $limit = 10;
        foreach ($data as $d) {
            if (strpos($d, 'page') !== false) {
                $page = str_replace("page=", "", $d);
            }
            if (strpos($d, 'limit') !== false) {
                $limit = str_replace("limit=", "", $d);
            }
        }
        
        return array("page" => $page, "limit" => $limit);
    }
    
    
    private function getResouceDataById(string $uuid, \acdhOeaw\fedora\Fedora $fedora): object
    {
        $result = new \stdClass();
        //get the resource metadata
        try {
            return $fedora->getResourceById($uuid);
        } catch (\acdhOeaw\fedora\exceptions\NotFound $ex) {
            $result->error =
                $this->langConf->get('errmsg_fedora_exception') ? $this->langConf->get('errmsg_fedora_exception').' :getMetadata function' : 'Fedora Exception : getMetadata function';
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            $result->error = t($ex->getMessage());
            return $result;
        }
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
    private function createDetailViewTable(\EasyRdf\Resource $data): \Drupal\oeaw\Model\OeawResource
    {
        $result = array();
        $arrayObject = new \ArrayObject();

        if (empty($data)) {
            return drupal_set_message(t('Error').':'.__FUNCTION__, 'error');
        }
        
        //get the resource Title
        $resourceTitle = $data->get(RC::get('fedoraTitleProp'));
        $resourceUri = $data->getUri();
        $resourceIdentifiers = $data->all(RC::get('fedoraIdProp'));
        $resourceIdentifier = HF::getAcdhIdentifier($resourceIdentifiers);
        
        $rsId = array();
        $uuid = "";
        if (count($resourceIdentifiers) > 0) {
            foreach ($resourceIdentifiers as $ids) {
                if (strpos($ids->getUri(), RC::get('fedoraUuidNamespace')) !== false) {
                    $uuid =  $ids->getUri();
                }
                $rsId[] = $ids->getUri();
            }
        }
        //get the resources and remove fedora properties
        $properties = array();
        $properties = $data->propertyUris();
        foreach ($properties as $key => $val) {
            if (strpos($val, 'fedora.info') !== false) {
                unset($properties[$key]);
            }
        }
        //reorder the array because have missing keys
        $properties = array_values($properties);
        $searchTitle = array();
        
        foreach ($properties as $p) {
            $propertyShortcut = $this->oeawFunctions->createPrefixesFromString($p);
            //get the properties data from the easyrdf resource object
            foreach ($data->all($p) as $key => $val) {
                if (get_class($val) == "EasyRdf\Resource") {
                    $classUri = $val->getUri();
                    if ($p == RC::get("drupalRdfType")) {
                        if ((strpos($val->__toString(), 'vocabs.acdh.oeaw.ac.at') !== false)
                                &&
                                $val->localName()) {
                            $result['acdh_rdf:type']['title'] = $val->localName();
                            $result['acdh_rdf:type']['insideUri'] = $this->oeawFunctions->detailViewUrlDecodeEncode($val->__toString(), 1);
                            $result['acdh_rdf:type']['uri'] = $val->__toString();
                        }
                    }
                    $result['table'][$propertyShortcut][$key]['uri'] = $classUri;
                    
                    //we will skip the title for the resource identifier
                    if ($p != RC::idProp()) {
                        //this will be the proper
                        $searchTitle[] = $classUri;
                    }
                    //if the acdhImage is available or the ebucore MIME
                    if ($p == RC::get("drupalRdfType")) {
                        if ($val == RC::get('drupalHasTitleImage')) {
                            $result['image'] = $resourceUri;
                        }
                        //check that the resource has Binary or not
                        if ($val == RC::get('drupalFedoraBinary')) {
                            $result['hasBinary'] = $resourceUri;
                        }
                        
                        if ($val == RC::get('drupalMetadata')) {
                            $invMeta = $this->oeawStorage->getMetaInverseData($resourceUri);
                            if (count($invMeta) > 0) {
                                $result['isMetadata'] = $invMeta;
                            }
                        }
                    }
                    //simply check the acdh:hasTitleImage for the root resources too.
                    if ($p == RC::get('drupalHasTitleImage')) {
                        $imgUrl = "";
                        $imgUrl = $this->oeawStorage->getImageByIdentifier($val->getUri());
                        if ($imgUrl) {
                            $result['image'] = $imgUrl;
                        }
                    }
                }
                if ((get_class($val) == "EasyRdf\Literal") ||
                        (get_class($val) == "EasyRdf\Literal\DateTime") ||
                        (get_class($val) == "EasyRdf\Literal\Integer")) {
                    if (get_class($val) == "EasyRdf\Literal\DateTime") {
                        $dt = $val->__toString();
                        $time = strtotime($dt);
                        $result['table'][$propertyShortcut][$key]  = date('Y-m-d', $time);
                    } else {
                        $result['table'][$propertyShortcut][$key] = $val->getValue();
                    }
                    
                    //we dont have the image yet but we have a MIME
                    if (($p == RC::get('drupalEbucoreHasMime')) && (!isset($result['image'])) && (strpos($val, 'image') !== false)) {
                        //if we have image/tiff then we need to use the loris
                        if ($val == "image/tiff") {
                            $lorisImg = array();
                            $lorisImg = HF::generateLorisUrl(base64_encode($resourceUri), true);
                            if (count($lorisImg) > 0) {
                                $result['image'] = $lorisImg['imageUrl'];
                            }
                        } else {
                            $result['image'] = $resourceUri;
                        }
                    }
                    if ($p == RC::get('fedoraExtentProp')) {
                        if ($val->getValue()) {
                            $result['table'][$propertyShortcut][$key] = HF::formatSizeUnits($val->getValue());
                        }
                    }
                }
            }
        }
        
        if (count($searchTitle) > 0) {
            //get the not literal propertys TITLE
            $existinTitles = array();
            $existinTitles = $this->oeawStorage->getTitleAndBasicInfoByIdentifierArray($searchTitle);
            
            if (count($existinTitles) > 0) {
                $resKeys = array_keys($result['table']);
                //change the titles
                foreach ($resKeys as $k) {
                    foreach ($result['table'][$k] as $key => $val) {
                        if (is_array($val)) {
                            foreach ($existinTitles as $t) {
                                if ($t['identifier'] == $val['uri'] || $t['pid'] == $val['uri'] || $t['uuid'] == $val['uri']) {
                                    $result['table'][$k][$key]['title'] = $t['title'];

                                    $decodId = "";
                                    if (isset($t['pid']) && !empty($t['pid'])) {
                                        $decodId = $t['pid'];
                                    } elseif (isset($t['uuid']) && !empty($t['uuid'])) {
                                        $decodId = $t['uuid'];
                                    } elseif (isset($t['identifier']) && !empty($t['identifier'])) {
                                        $decodId = $t['identifier'];
                                    }

                                    if (!empty($decodId)) {
                                        $result['table'][$k][$key]['insideUri'] = $this->oeawFunctions->detailViewUrlDecodeEncode($decodId, 1);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        if (empty($result['acdh_rdf:type']['title']) || !isset($result['acdh_rdf:type']['title'])) {
            throw new \ErrorException(t("Empty").': ACDH RDF TYPE', 0);
        }
        $result['resourceTitle'] = $resourceTitle;
        $result['uri'] = $resourceUri;
        $result['insideUri'] = $this->oeawFunctions->detailViewUrlDecodeEncode($resourceIdentifier, 1);
        
        $arrayObject->offsetSet('table', $result['table']);
        $arrayObject->offsetSet('title', $resourceTitle->__toString());
        $arrayObject->offsetSet('uri', $this->oeawFunctions->detailViewUrlDecodeEncode($resourceIdentifier, 0));
        $arrayObject->offsetSet('type', $result['acdh_rdf:type']['title']);
        $arrayObject->offsetSet('typeUri', $result['acdh_rdf:type']['uri']);
        $arrayObject->offsetSet('acdh_rdf:type', array("title" => $result['acdh_rdf:type']['title'], "insideUri" => $result['acdh_rdf:type']['insideUri']));
        $arrayObject->offsetSet('fedoraUri', $resourceUri);
        $arrayObject->offsetSet('identifiers', $rsId);
        if (isset($result['table']['acdh:hasAccessRestriction']) && !empty($result['table']['acdh:hasAccessRestriction'][0])) {
            $arrayObject->offsetSet('accessRestriction', $result['table']['acdh:hasAccessRestriction'][0]);
        }
        $arrayObject->offsetSet('insideUri', $this->oeawFunctions->detailViewUrlDecodeEncode($uuid, 1));
        if (isset($result['image'])) {
            $arrayObject->offsetSet('imageUrl', $result['image']);
        }
        
        try {
            $obj = new \Drupal\oeaw\Model\OeawResource($arrayObject);
        } catch (ErrorException $ex) {
            throw new \ErrorException(t('Init').' '.t('Error').' : OeawResource', 0);
        }
        return $obj;
    }
    
    /**
     * This functions create the Project template data for the basic view
     *
     * @param OeawResource $data
     * @param string $type
     * @return \Drupal\oeaw\Model\OeawResourceCustomData
     * @throws \ErrorException
     */
    private function createCustomDetailViewTemplateData(\Drupal\oeaw\Model\OeawResource $data, string $type): \Drupal\oeaw\Model\OeawResourceCustomData
    {
        
        //check the table data in the object that we have enough data :)
        if (count($data->getTable()) > 0) {
            //set the data for the resource custom data object
            $arrayObject = new \ArrayObject();
            $arrayObject->offsetSet('uri', $data->getUri());
            $arrayObject->offsetSet('insideUri', $data->getInsideUri());
            $arrayObject->offsetSet('fedoraUri', $data->getFedoraUri());
            $arrayObject->offsetSet('identifiers', $data->getIdentifiers());
            $arrayObject->offsetSet('title', $data->getTitle());
            $arrayObject->offsetSet('type', $data->getType());
            $arrayObject->offsetSet('typeUri', $data->getTypeUri());
            if (!empty($data->getPID())) {
                $arrayObject->offsetSet('pid', $data->getPID());
            }
            if (!empty($data->getAccessRestriction())) {
                $arrayObject->offsetSet('accessRestriction', $data->getAccessRestriction());
            }
            
            if (!empty($data->getType())) {
                $arrayObject->offsetSet('acdh_rdf:type', $data->getType());
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
    
    public function generateDetailViewMainData(&$fedora, &$uuid): object
    {
        $result = new \stdClass();
        //get the basic resource data
        $this->fedoraResource = $this->getResouceDataById($uuid, $fedora);
        if (isset($this->fedoraResource->error) && !empty($this->fedoraResource->error)) {
            return $this->fedoraResource->error;
        } else {
            $this->fedoraMetadata = $this->fedoraResource->getMetadata();
        }
                
        if (count((array)$this->fedoraMetadata)) {
            //create the OEAW resource Object for the GUI data
            try {
                $resultsObj = $this->createDetailViewTable($this->fedoraMetadata);
            } catch (\ErrorException $ex) {
                drupal_set_message(t("Error").' : '.$ex->getMessage(), 'error');
                return array();
            }
            
            //check the acdh:hasIdentifier data to the child view
            if (count($resultsObj->getIdentifiers()) > 0) {
                $customDetailView = array();
                //if we have a type and this type can found in the available custom views array
                try {
                    $customDetailView = $this->createCustomDetailViewTemplateData($resultsObj, $resultsObj->getType());
                } catch (\ErrorException $ex) {
                    return $result->error = t("Error message").' : Resource Custom Table View. '.$ex->getMessage();
                }
                if (count((array)$customDetailView) > 0) {
                    $extras['specialType'][strtolower($resultsObj->getType())] = $customDetailView;
                }
            }
        } else {
            return $result->error = $this->langConf->get('errmsg_resource_no_metadata') ? $this->langConf->get('errmsg_resource_no_metadata') : 'The resource has no metadata!';
        }
                
        //the breadcrumb section
        if ($resultsObj->getType() == "Collection" || $resultsObj->getType() == "Resource"
                || $resultsObj->getType() == "Metadata") {
            $breadcrumbs = array();
            
           
            //we have cached breadcrumbs with this identifier
            if (count($this->breadcrumbCache->getCachedData($uuid)) > 0) {
                $extras['breadcrumb'] = $this->breadcrumbCache->getCachedData($uuid);
            } else {
                $breadcrumbs = $this->breadcrumbCache->setCacheData($uuid);
                if (count($breadcrumbs) > 0) {
                    $extras['breadcrumb'] = $breadcrumbs;
                }
            }
        }
        
        $dissServices = array();
        //check the Dissemination services
        try {
            $dissServices = $this->oeawFunctions->getResourceDissServ($this->fedoraResource);
        } catch (Exception $ex) {
            return $result->error = $ex->getMessage();
        } catch (\acdhOeaw\fedora\exceptions\NotFound $ex) {
            return $result->error = $ex->getMessage();
        }
        if (count($dissServices) > 0 && $this->fedoraResource->getId()) {
            //we need to remove the raw from the list if it is a collection
            if ($resultsObj->getType() == "Collection") {
                for ($i=0; $i <= count($dissServices); $i++) {
                    if ($dissServices[$i]['returnType'] == "raw") {
                        unset($dissServices[$i]);
                        break;
                    }
                }
            }
            $extras['dissServ']['services'] = $dissServices;
            $extras['dissServ']['identifier'] = $this->fedoraResource->getId();
        }
        // Pass fedora uri so it can be linked in the template
        $extras["fedoraURI"] = $this->fedoraMetadata->getUri();
        
        //format the hasavailable date
        if (!empty($resultsObj->getTableData("acdh:hasAvailableDate"))) {
            $avDate = $resultsObj->getTableData("acdh:hasAvailableDate");
            if (is_array($avDate)) {
                $avDate = $avDate[0];
            }
            if (\DateTime::createFromFormat('Y-d-d', $avDate) !== false) {
                $time = strtotime($avDate);
                $newTime = date('Y-m-d', $time);
                if ($resultsObj->setTableData("acdh:hasAvailableDate", array($newTime)) == false || empty($newTime)) {
                    return $result->error = t('Error').' : Available date format';
                }
            }
            //if we dont have a real date just a year
            if (\DateTime::createFromFormat('Y', $avDate) !== false) {
                $year = \DateTime::createFromFormat('Y', $avDate);
                if ($resultsObj->setTableData("acdh:hasAvailableDate", array($year->format('Y'))) == false || empty($year->format('Y'))) {
                    return $result->error = t('Error').' : Available date format';
                }
            }
        }
        
        //generate the NiceUri to the detail View
        $niceUri = "";
        $niceUri = HF::generateNiceUri($resultsObj);
        if (!empty($niceUri)) {
            $extras["niceURI"] = $niceUri;
        }
        
        //Create data for cite-this widget
        $typesToBeCited = ["collection", "project", "resource", "publication"];
        if (!empty($resultsObj->getType()) && in_array(strtolower($resultsObj->getType()), $typesToBeCited)) {
            //pass $rootMeta for rdf object
            $extras["CiteThisWidget"] = $this->oeawFunctions->createCiteThisWidget($resultsObj);
        }
        
        //get the tooltip from cache
        $cachedTooltip = $this->propertyTableCache->getCachedData($resultsObj->getTable());
        if (count($cachedTooltip) > 0) {
            $extras["tooltip"] = $cachedTooltip;
        }
        
        //if it is a resource then we need to check the 3dContent
        if ($resultsObj->getType() == "Resource") {
            if (HF::check3dData($resultsObj->getTable()) === true) {
                $extras['3dData'] = true;
            } else {
                //but if we have resource and the diss-serv contains the 3d viewer, but our viewer cant show it, then we need to remove
                // the 3d viewer from the dissemination services.
                if (array_search('https://id.acdh.oeaw.ac.at/dissemination/3DObject', array_column($dissServices, 'identifier')) !== false) {
                    $key = array_search('https://id.acdh.oeaw.ac.at/dissemination/3DObject', array_column($dissServices, 'identifier'));
                    unset($dissServices[$key]);
                }
            }
        }
        
        //we have a shibboleth user logged in
        if ((isset($_SERVER['HTTP_EPPN']) && $_SERVER['HTTP_EPPN'] != "(null)")
               && (isset($_SERVER['HTTP_AUTHORIZATION']) && $_SERVER['HTTP_AUTHORIZATION'] != "(null)")
                ) {
            $extras['basic_auth'] = $_SERVER["HTTP_AUTHORIZATION"];
        }
        
        $result->mainData = $resultsObj;
        $result->extraData = $extras;
        return $result;
    }
    
    /**
     * Extend the collection download python script with the url
     *
     * @param string $fdUrl
     * @return string
     */
    public function changeCollDLScript(string $fdUrl)
    {
        $text = "";
        try {
            $fileName = $_SERVER["DOCUMENT_ROOT"].'/sites/default/files/coll_dl_script/collection_download.py';
            
            if (!file_exists($fileName)) {
                return $text;
            }
            
            $text = file_get_contents($fileName);
            
            if (strpos($text, 'args = args.parse_args()') !== false) {
                $text = str_replace("args = args.parse_args()", "args = args.parse_args(['".$fdUrl."', '--recursive'])", $text);
            }
            
            return $text;
        } catch (\Exception $e) {
            return;
        }
        return $text;
    }
}