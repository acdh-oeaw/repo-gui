<?php

namespace Drupal\oeaw\Helper;

use Drupal\oeaw\OeawFunctions;
use Drupal\oeaw\Model\OeawStorage;
use Drupal\oeaw\Helper\HelperFunctions as HF;
use Drupal\oeaw\Model\OeawResourceCustomData;
use acdhOeaw\util\RepoConfig as RC;
use Drupal\oeaw\Cache\BreadcrumbCache;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DetailViewFunctions
{
    private $langConf;
    private $fedoraResource;
    private $fedoraMetadata;
    private $oeawFunctions;
    private $oeawStorage;
    private $breadcrumbCache;
    private $searchTitle;
    private $dvResult;
    
    public function __construct(
        $langConf,
        \Drupal\oeaw\OeawFunctions $oeawFunctions,
        \Drupal\oeaw\Model\OeawStorage $oeawStorage
    ) {
        $this->langConf = $langConf;
        $this->oeawFunctions = $oeawFunctions;
        $this->oeawStorage = $oeawStorage;
        $this->breadcrumbCache = new BreadcrumbCache();
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
    
    
    /**
     * Get the resource by identifier
     *
     * @param string $uuid
     * @param \acdhOeaw\fedora\Fedora $fedora
     * @return object
     */
    private function getResouceDataById(string $uuid, \acdhOeaw\fedora\Fedora $fedora): object
    {
        $result = new \stdClass();
        //get the resource metadata
        try {
            return $fedora->getResourceById($uuid);
        } catch (\acdhOeaw\fedora\exceptions\NotFound $ex) {
            $result->error =
                $this->langConf->get('errmsg_fedora_exception') ? $this->langConf->get('errmsg_fedora_exception').' :getMetadata function -> uuid not found!' : 'Fedora Exception : getMetadata function -> uuid not found!';
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            $result->error = t($ex->getMessage());
            return $result;
        }
    }
    
    /**
     * check if the image is loris image
     *
     * @param \EasyRdf\Literal $d
     * @return string
     */
    private function checkLorisImage(\EasyRdf\Literal $d): string
    {
        if (strpos($d->__toString(), 'image') !== false) {
            if ($d->__toString() == "image/tiff") {
                $lorisImg = array();
                $lorisImg = HF::generateLorisUrl(base64_encode($d->getUri()), true);
                if (count($lorisImg) > 0) {
                    $this->dvResult['image'] = $lorisImg['imageUrl'];
                }
            } else {
                $this->dvResult['image'] = $d->getUri();
            }
            return "";
        } else {
            return $d->__toString();
        }
    }
    
   
    /**
     * Get the Literal values from the easyrdf obj
     *
     * @param \EasyRdf\Resource $data
     * @param string $prop
     * @param string $lang
     * @return type
     */
    private function getLiteralValuesByLangFromResource(\EasyRdf\Resource &$data, string $prop, string $lang)
    {
        ($prop == RC::get('fedoraExtentProp')) ? $extent = true : $extent = false;
        ($prop == RC::get('drupalEbucoreHasMime') && (!isset($this->dvResult['image']))) ? $image = true : $image = false;
        ($data->allLiterals($prop, $lang)) ? $value = $data->allLiterals($prop, $lang) : $value = $data->allLiterals($prop);
        
        $result = array();
        foreach ($value as $d) {
            if (get_class($d) == "EasyRdf\Literal\DateTime") {
                $dt = $d->__toString();
                $time = strtotime($dt);
                $result[] = date('Y-m-d', $time);
            } elseif ($extent) {
                $result[] = HF::formatSizeUnits($d->__toString());
            /*} elseif ($image && get_class($d) == "EasyRdf\Literal") {
                if (!empty($this->checkLorisImage($d))) {
                    $result[] = $this->checkLorisImage($d);
                }*/
            } else {
                $result[] = $d->__toString();
            }
        }
        return $result;
    }
    
    /**
     * Format the resource values
     *
     * @param array $data
     * @param string $p
     * @param string $propertyShortcut
     */
    private function formatResourceValues(array $data, string $p, string $propertyShortcut)
    {
        foreach ($data as $d) {
            //we will skip the title for the resource identifier
            if ($p != RC::idProp()) {
                //this will be the proper
                $this->searchTitle[] = $d->getUri();
            }
            
            $classUri = $d->getUri();
            if ($p == RC::get("drupalRdfType")) {
                if ((strpos($d->__toString(), 'vocabs.acdh.oeaw.ac.at') !== false)
                    &&
                $d->localName()) {
                    $this->dvResult['acdh_rdf:type']['title'] = $d->localName();
                    $this->dvResult['acdh_rdf:type']['insideUri'] = $this->oeawFunctions->detailViewUrlDecodeEncode($d->__toString(), 1);
                    $this->dvResult['acdh_rdf:type']['uri'] = $d->__toString();
                }
            }
            $this->dvResult['table'][$propertyShortcut][]['uri'] = $classUri;
            
            //if the acdhImage is available or the ebucore MIME
            if ($p == RC::get("drupalRdfType")) {
                if ($d == RC::get('drupalHasTitleImage')) {
                    $this->dvResult['image'] = $d->getUri();
                    $this->dvResult['imageID'] = $d->getUri();
                }
                //check that the resource has Binary or not
                if ($d == RC::get('drupalFedoraBinary')) {
                    $this->dvResult['hasBinary'] = $d->getUri();
                }
                        
                if ($d == RC::get('drupalMetadata')) {
                    $invMeta = $this->oeawStorage->getMetaInverseData($d->getUri());
                    if (count($invMeta) > 0) {
                        $this->dvResult['isMetadata'] = $invMeta;
                    }
                }
            }
            
            //simply check the acdh:hasTitleImage for the root resources too.
            if ($p == RC::get('drupalHasTitleImage')) {
                $imgUrl = "";
                $imgUrl = $this->oeawStorage->getImageByIdentifier($d->getUri());
                if ($imgUrl) {
                    $this->dvResult['image'] = $imgUrl;
                    $this->dvResult['imageID'] = $d->getUri();
                    (!empty(HF::createThumbnailUrl($d->getUri()))) ? $this->dvResult['imageThumbUrl'] = HF::createThumbnailUrl($d->getUri()) : "";
                }
            }
        }
    }
    
    
    
    /**
     * Get the literal and resource values from the easyrdf resource object
     *
     * @param \EasyRdf\Resource $data
     * @param string $lang
     * @return array
     */
    private function getLiteralsResourcesByLang(\EasyRdf\Resource $data, string $lang)
    {
        $result = array();
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
        
        //loop through the properties
        foreach ($properties as $p) {
            
            //create the property shortcuts for the array
            $propertyShortcut = $this->oeawFunctions->createPrefixesFromString($p);
            //if it is a liteal
            
            //LITERALS
            if ($data->getLiteral($p)) {
                $val = $this->getLiteralValuesByLangFromResource($data, $p, $lang);
                $this->dvResult['table'][$propertyShortcut] = $val;
            }
            
            //// RESOURCES
            if ($data->getResource($p)) {
                ($data->allResources($p)) ? $val = $data->allResources($p) : $val = array();
                if (empty($val)) {
                    continue;
                }
                $this->formatResourceValues($val, $p, $propertyShortcut);
            }
        }
    }
    
    /**
     *  Get the actual vocabs translations for the special properties
     * @param string $lang
     */
    private function getVocabsForDetailViewTable(string $lang)
    {
        $vf = new \Drupal\oeaw\Helper\CacheVocabsHelper();
        $vocabs = array();
        $vocabs = $vf->getVocabsTitle($lang);
        
        if (count((array)$vocabs[$lang]) > 0) {
            foreach ($vocabs[$lang] as $k => $v) {
                if (isset($this->dvResult['table'][$k][0]['uri'])) {
                    foreach ($v as $vocab) {
                        if (($vocab->uri) && ($vocab->uri == $this->dvResult['table'][$k][0]['uri'])) {
                            $this->dvResult['table'][$k][0]['uri'] = $vocab->uri;
                            $this->dvResult['table'][$k][0]['title'] = $vocab->label;
                            $this->dvResult['table'][$k][0]['lang'] = $vocab->language;
                        }
                    }
                }
            }
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
    private function createDetailViewTable(\EasyRdf\Resource $data, string $lang): \Drupal\oeaw\Model\OeawResource
    {
        if (empty($data)) {
            return drupal_set_message(t('Error').':'.__FUNCTION__, 'error');
        }
        
        $arrayObject = new \ArrayObject();
        
        //get the resource Title
        $resourceTitle = $this->getLiteralValuesByLangFromResource($data, RC::get('fedoraTitleProp'), $lang)[0];
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
       
        $this->getLiteralsResourcesByLang($data, $lang);

        if (count($this->searchTitle) > 0) {
            //get the not literal propertys TITLE
            $existinTitles = array();
            $existinTitles = $this->oeawStorage->getTitleAndBasicInfoByIdentifierArray($this->searchTitle, false, $lang);
            
            if (count($existinTitles) > 0) {
                $resKeys = array_keys($this->dvResult['table']);
                //change the titles
                foreach ($resKeys as $k) {
                    foreach ($this->dvResult['table'][$k] as $key => $val) {
                        if (is_array($val)) {
                            foreach ($existinTitles as $t) {
                                if ($t['identifier'] == $val['uri'] || $t['pid'] == $val['uri'] || $t['uuid'] == $val['uri']) {
                                    $this->dvResult['table'][$k][$key]['title'] = $t['title'];

                                    $decodId = "";
                                    if (isset($t['pid']) && !empty($t['pid']) && (strpos($t['pid'], 'http') !== false)) {
                                        $decodId = $t['pid'];
                                    } elseif (isset($t['uuid']) && !empty($t['uuid'])) {
                                        $decodId = $t['uuid'];
                                    } elseif (isset($t['identifier']) && !empty($t['identifier'])) {
                                        $decodId = $t['identifier'];
                                    }

                                    if (!empty($decodId)) {
                                        $this->dvResult['table'][$k][$key]['insideUri'] = $this->oeawFunctions->detailViewUrlDecodeEncode($decodId, 1);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        if (empty($this->dvResult['acdh_rdf:type']['title']) || !isset($this->dvResult['acdh_rdf:type']['title'])) {
            throw new \ErrorException(t("Empty").': ACDH RDF TYPE', 0);
        }
        
        //update the data with the vocabs translated values
        $this->getVocabsForDetailViewTable($lang);
        
        $this->dvResult['resourceTitle'] = $resourceTitle;
        $this->dvResult['uri'] = $resourceUri;
        $this->dvResult['uuid'] = $uuid;
        $this->dvResult['insideUri'] = $this->oeawFunctions->detailViewUrlDecodeEncode($resourceIdentifier, 1);
        //get the vocabs cache!
       
        $arrayObject->offsetSet('table', $this->dvResult['table']);
        $arrayObject->offsetSet('title', $this->dvResult['resourceTitle']);
        $arrayObject->offsetSet('uri', $this->oeawFunctions->detailViewUrlDecodeEncode($resourceIdentifier, 0));
        $arrayObject->offsetSet('type', $this->dvResult['acdh_rdf:type']['title']);
        $arrayObject->offsetSet('typeUri', $this->dvResult['acdh_rdf:type']['uri']);
        $arrayObject->offsetSet('acdh_rdf:type', array("title" => $this->dvResult['acdh_rdf:type']['title'], "insideUri" => $this->dvResult['acdh_rdf:type']['insideUri']));
        $arrayObject->offsetSet('fedoraUri', $this->dvResult['uri']);
        $arrayObject->offsetSet('identifiers', $rsId);
        if (isset($this->dvResult['table']['acdh:hasAccessRestriction']) && !empty($this->dvResult['table']['acdh:hasAccessRestriction'][0])) {
            $arrayObject->offsetSet('accessRestriction', $this->dvResult['table']['acdh:hasAccessRestriction'][0]);
        }
        $arrayObject->offsetSet('insideUri', $this->oeawFunctions->detailViewUrlDecodeEncode($this->dvResult['uuid'], 1));
        if (isset($this->dvResult['image'])) {
            $arrayObject->offsetSet('imageUrl', $this->dvResult['image']);
        }
        
        if (isset($this->dvResult['imageThumbUrl'])) {
            $arrayObject->offsetSet('imageThumbUrl', $this->dvResult['imageThumbUrl']);
        }
        
        if (strpos(strtolower($this->dvResult['acdh_rdf:type']['title']), 'image') !== false) {
            $arrayObject->offsetSet('imageThumbUrl', HF::createThumbnailUrl($this->dvResult['uuid']));
        }
        
        try {
            $obj = new \Drupal\oeaw\Model\OeawResource($arrayObject, $lang);
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
    private function createCustomDetailViewTemplateData(\Drupal\oeaw\Model\OeawResource $data, string $type, string $lang = "en"): \Drupal\oeaw\Model\OeawResourceCustomData
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
                $obj = new \Drupal\oeaw\Model\OeawResourceCustomData($arrayObject, $lang);
                $obj->setupBasicExtendedData($data);
            } catch (\ErrorException $ex) {
                throw new \ErrorException($ex->getMessage());
            }
        }
        return $obj;
    }
    
    /**
     * The main view data for the oeaw_detail page
     *
     * @param type $fedora
     * @param type $uuid
     * @param string $lang
     * @return object
     */
    public function generateDetailViewMainData(&$fedora, &$uuid, string $lang = ""): object
    {
        $result = new \stdClass();
        //get the basic resource data
        $this->fedoraResource = $this->getResouceDataById($uuid, $fedora);
        
        if (isset($this->fedoraResource->error) && !empty($this->fedoraResource->error)) {
            $result->error = $this->fedoraResource->error;
            return $result;
        } else {
            $this->fedoraMetadata = $this->fedoraResource->getMetadata();
        }
        
        if (count((array)$this->fedoraMetadata) > 0) {
            //create the OEAW resource Object for the GUI data
            try {
                $resultsObj = $this->createDetailViewTable($this->fedoraMetadata, $lang);
            } catch (\ErrorException $ex) {
                $result->error = t("Error").' : '.$ex->getMessage();
                return $result;
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
            $result->error = $ex->getMessage();
            return $result;
        } catch (\acdhOeaw\fedora\exceptions\NotFound $ex) {
            $result->error = $ex->getMessage();
            return $result;
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
                    $result->error = t('Error').' : Available date format';
                    return $result;
                }
            }
            //if we dont have a real date just a year
            if (\DateTime::createFromFormat('Y', $avDate) !== false) {
                $year = \DateTime::createFromFormat('Y', $avDate);
                if ($resultsObj->setTableData("acdh:hasAvailableDate", array($year->format('Y'))) == false || empty($year->format('Y'))) {
                    $result->error = t('Error').' : Available date format';
                    return $result;
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
        $typesToBeCited = ["collection", "project", "resource", "publication", "metadata"];
        if (!empty($resultsObj->getType()) && in_array(strtolower($resultsObj->getType()), $typesToBeCited)) {
            //pass $rootMeta for rdf object
            $extras["CiteThisWidget"] = $this->oeawFunctions->createCiteThisWidget($resultsObj);
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
