<?php

namespace Drupal\oeaw\Helper;

use acdhOeaw\util\RepoConfig as RC;
use Drupal\oeaw\Model\OeawCustomSparql;
use Drupal\oeaw\Helper\HelperFunctions as HF;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Response;

/**
 * Description of CollectionFunctions
 *
 * @author nczirjak
 */
class CollectionFunctions {
    
    private $fedora;
    private $cfg;
    private $oeawFunctions;
    private $metadata;
    private $resData;
    private $binaries;
    private $id;
    private $fedoraGlobalModDate;
    private $cacheModel;
    private $oeawStorage;
    
    public function __construct(\acdhOeaw\fedora\Fedora $fedora, $cfg, \Drupal\oeaw\OeawFunctions $oeawFunctions, string $fedoraGlobalModDate, \Drupal\oeaw\Model\CacheModel $cacheModel, \Drupal\oeaw\Model\OeawStorage $oeawStorage ) {
        $this->fedora = $fedora;
        $this->cfg = $cfg;
        $this->oeawFunctions = $oeawFunctions;
        $this->resData = array();
        $this->fedoraGlobalModDate = $fedoraGlobalModDate;
        $this->cacheModel = $cacheModel;
        $this->oeawStorage = $oeawStorage;
    }
    
    /**
     * Get the collections metadata and binary list
     * 
     * @param string $id
     * @param bool $binaries
     * @return array
     */
    public function getCollectionData(string $id, bool $binaries = false): array {
        if(empty($id)) {
            return array();
        }
        $result = array();
        $this->id = $id;
        
        if(!$this->cacheModel) {
            return array();
        }
        
        //get the metadata
        if(!$this->generateCollectionMetaData($id)){
            return array();
        }
        
        if($this->metadata) {
            $this->setUpCollectionMetaData();
        }
        
        $actualCacheObj = new \stdClass();
        $actualCacheObj = $this->cacheModel->getCacheByUUID($this->id, "C");
        if($binaries) {
            $actualCacheBinaries = $this->cacheModel->getCacheByUUID($this->id, "B");
        }
        
        $fdDate = strtotime($this->fedoraGlobalModDate);
        
        $needsToCache = FALSE;
        if(isset($actualCacheObj->modify_date) && ($fdDate >  $actualCacheObj->modify_date)) {
            $needsToCache = TRUE;
        }else if(count((array)$actualCacheObj) == 0) {
            $needsToCache = TRUE;
        }
        
        if($needsToCache === TRUE) {
            if($binaries) {
                if($this->setUpCollectionBinaries() === false) {
                    return array();
                }
                
                if(!$this->cacheModel->addCacheToDB($this->id, serialize($this->binaries), "B", $fdDate)){
                    return array();
                }
            }
            if(!$this->cacheModel->addCacheToDB($this->id, serialize($this->resData), "C", $fdDate)){
                return array();
            }
            $actualCacheObj = new \stdClass();
            $actualCacheObj = $this->cacheModel->getCacheByUUID($this->id, "C");
            if($binaries) {
                $actualCacheBinaries = $this->cacheModel->getCacheByUUID($this->id, "B");
            }
        }

        if(count((array)$actualCacheObj) > 0){
            $result['metadata'] = $actualCacheObj;
            $result['metadata']->data = unserialize($actualCacheObj->data);
        }
        if( isset($actualCacheBinaries->data) 
                && 
            (count((array)$actualCacheBinaries->data) > 0 )
        ){
            $result['binaries'] = unserialize($actualCacheBinaries->data);
        }
        return $result;
    }
    
    /**
     * Setup the collection binary files list
     * @return bool
     */
    private function setUpCollectionBinaries(): bool {
        
        $oeawCustSparql = new OeawCustomSparql();
        $collBinSql = $oeawCustSparql->getCollectionBinaries($this->resData['fedoraUri']);
        
        if (!empty($collBinSql)) {
            
            $cacheData = $this->oeawStorage->runUserSparql($collBinSql);
                 
            if (count($cacheData) > 0) {
                foreach ($cacheData as $k => $v) {
                    $cacheData[$k]['userAllowedToDL'] = true;
                    if ($v['binarySize']) {
                        $cacheData[$k]['formSize'] = \Drupal\oeaw\Helper\HelperFunctions::formatSizeUnits((string)$v['binarySize']);
                    }
                    if (isset($v['accessRestriction'])) {
                        if (empty($v['accessRestriction'])) {
                            $v['accessRestriction'] = "public";
                        }
                        $cacheData[$k]['accessRestriction'] = $v['accessRestriction'];
                    }
                    
                    if(isset( $v['path']) && !empty($v['path'])){
                        $cacheData[$k]['path'] = $v['path'];
                    }

                    if ($v['identifier']) {
                        $dtUri = $this->oeawFunctions->createDetailViewUrl($v);
                        $dtUri = $this->oeawFunctions->detailViewUrlDecodeEncode($dtUri, 1);
                        $cacheData[$k]['encodedUri'] = $dtUri;
                    }

                    if (!empty($v['filename']) && $v['binarySize'] > 0) {
                        $cacheData[$k]['text'] = $v['filename']." | ".$cacheData[$k]['formSize'];
                        $cacheData[$k]['dir'] = false;
                        $cacheData[$k]['icon'] = "jstree-file";
                    } else {
                        $cacheData[$k]['text'] = $v['title'];
                        $cacheData[$k]['dir'] = true;
                    }
                    //if there is no text then it could be a wrong binary
                    //so we will remove it from the list
                    if (empty($cacheData[$k]['text'])) {
                        unset($cacheData[$k]);
                    }
                }
                $this->binaries = $cacheData;
                return true;
            }
            return false;
        } 
        return false;
    }
    
    /**
     * Setup the collection metadata
     */
    private function setUpCollectionMetaData() {
        $uri                = $this->metadata->getUri();
        //get title
        $title              = $this->getStringData(RC::get('fedoraTitleProp'), "title");
        //number of the files
        $filesNum           = $this->getStringData(RC::get('fedoraCountProp'), "filesNum");
        //get the sum binary size of the collection
        $binarySize         = $this->getStringData(RC::get('fedoraExtentProp'), "binarySize");
        $license            = $this->getStringData(RC::get('fedoraVocabsNamespace').'hasLicense', "license");
        $isPartOf           = $this->getStringData(RC::get('fedoraRelProp'), "isPartOfUrl");
        $accessRestriction  = $this->getStringData(RC::get('fedoraAccessRestrictionProp'), "accessRestriction");
        $locationPath       = $this->getStringData(RC::get('fedoraLocProp'), "locationPath");
        
        $this->resData["uri"] = $this->id;
        $this->resData["fedoraUri"] = $uri;
        if(isset($locationPath)) {
            $this->resData["locationPath"] = $locationPath;
        }
        
        if(isset($this->resData['binarySize']) && $this->resData['binarySize'] > 0) {
            $this->resData['formattedSize'] = \Drupal\oeaw\Helper\HelperFunctions::formatSizeUnits($this->resData['binarySize']);
            $estDLTime = \Drupal\oeaw\Helper\HelperFunctions::estDLTime($this->resData['binarySize']);
            if ($estDLTime > 0) {
                $this->resData['estDLTime'] = $estDLTime;
            }
            
            $freeSpace = 0;
            if (!file_exists($_SERVER['DOCUMENT_ROOT'].'/sites/default/files/collections/')) {
                mkdir($_SERVER['DOCUMENT_ROOT'].'/sites/default/files/collections/', 0777);
            }
            //get the free space to we can calculate the zipping will be okay or not?!
            $freeSpace = disk_free_space($_SERVER['DOCUMENT_ROOT'].'/sites/default/files/collections/');

            if ($freeSpace) {
                $this->resData['freeSpace'] = $freeSpace;
                $this->resData['formattedFreeSpace'] = \Drupal\oeaw\Helper\HelperFunctions::formatSizeUnits((string)$freeSpace);

                if ($freeSpace > 1499999999 * 2.2) {
                    //if there is no enough free space then we will not allow to DL the collection
                    $this->resData['dl'] = true;
                }
            }
        }
        
        /*
        if (isset($this->resData['isPartOfUrl'])) {
            $OeawStorage = new OeawStorage();
            $isPartTitle = $OeawStorage->getParentTitle($isPartOf->getUri());
            if (count($isPartTitle) > 0) {
                if ($isPartTitle[0]["title"]) {
                    $resData['isPartOf'] = $isPartTitle[0]["title"];
                }
            }
        }*/
    }
    
    /**
     * Get the string/uri value from the Easyrdf content
     * 
     * @param string $prop
     * @param string $arrName
     * @return string
     */
    private function getStringData(string $prop, string $arrName = ""): string {
        $str = $this->metadata->get($prop);
        
        if($str) {
            $objClass = get_class($str);
            if ($objClass == "EasyRdf\Resource") {
                    if($arrName) {
                        $this->resData[$arrName] = $str->getUri();
                    }
                    return $str->getUri();

            } elseif ( ($objClass == "EasyRdf\Literal") || ( strpos($objClass, "EasyRdf\Literal") !== false )) {
                if (isset($str) && $str->getValue()) {
                    if($arrName) {
                        $this->resData[$arrName] = $str->getValue();
                    }
                    return $str->getValue();
                }
            } else {
                return "";
            }
        }
        return "";
    }
    
    /**
     * Generate the collection data for the download view
     *
     * @param string $id
     * @return array
     */
    private function generateCollectionMetaData(string $id): bool
    {        
        if (strpos($id, RC::get('fedoraIdNamespace')) === false) {
            return false;
        }

        try {
            //get the resource data
            $fedoraRes = $this->fedora->getResourceById($id);
            $this->metadata = $fedoraRes->getMetadata();
            return true;
        } catch (\acdhOeaw\fedora\exceptions\NotFound $ex) {
            return false;
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return false;
        }
    }
    
    /**
     * Setup the collection directory for the downloads
     * 
     * @param string $dateID
     * @return string
     */
    public function setupDirForCollDL(string $dateID): string {
        //the main dir
        $tmpDir = $_SERVER['DOCUMENT_ROOT'].'/sites/default/files/collections/';
        //the collection own dir
        $tmpDirDate = $tmpDir.$dateID;
        //if the main directory is not exists
        if (!file_exists($tmpDir)) {
            mkdir($tmpDir, 0777);
        }
        //if we have the main directory then create the sub
        if (file_exists($tmpDir)) {
            //create the actual dir
            if (!file_exists($tmpDirDate)) {
                mkdir($tmpDirDate, 0777);
                $GLOBALS['resTmpDir'] = $tmpDirDate;
            }
        }
        return $tmpDirDate;
    }
    
    /**
     * Download collection selected files
     * 
     * @param array $binaries
     */
    public function downloadFiles(array $binaries) 
    {
        $client = new \GuzzleHttp\Client(['auth' => [RC::get('fedoraUser'), RC::get('fedoraPswd')], 'verify' => false]);
        ini_set('max_execution_time', 1800);
        
        foreach ($binaries as $b) {
            if (isset($b['path']) && isset($b['filename']) ) {
                $exp = explode("/", $b['path']);
                $last = end($exp);
                $filename = "";
                $path = $b['path'];
                $dir = "";

                if (strpos($last, '.') !== false) {
                    $filename = ltrim($last); 
                    $filename = str_replace(' ', "_", $filename);
                }else {
                    $filename = ltrim($b['filename']);
                    $filename = str_replace(' ', "_", $filename);
                }

                $path = str_replace($last, "", $path);

                if (!file_exists($GLOBALS['resTmpDir'])) {
                    mkdir($GLOBALS['resTmpDir'], 0777);
                    $dir = $GLOBALS['resTmpDir'];
                }

                if($path) {
                    $path = preg_replace('/\s+/', '_', $path);
                    mkdir($GLOBALS['resTmpDir'].'/'.$path, 0777, true);
                    $dir = $GLOBALS['resTmpDir'].'/'.$path;
                }

                try {
                    $resource = fopen($GLOBALS['resTmpDir'].'/'.$path.'/'.$filename, 'w');
                    $client->request('GET', $b['uri'], ['save_to' => $resource]);
                    chmod($GLOBALS['resTmpDir'].'/'.$path.'/'.$filename, 0777);

                } catch (\GuzzleHttp\Exception\ClientException $ex) {
                    continue;
                } catch(\GuzzleHttp\Exception\ServerException $ex) {
                    //the file is empty
                    continue;
                } catch(\RuntimeException $ex) {
                    //the file is empty
                    continue;
                }

            } elseif (isset($b['path'])) {
                mkdir($GLOBALS['resTmpDir'].'/'.$b['path'], 0777);
            }
        }
    }
    
    /**
     * Remove the directory files/directories and keep the collection.tar
     * 
     * @param string $dir
     */
    public function removeDirContent(string $dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir."/".$object) == "dir") {
                        $this->removeDirContent($dir."/".$object); 
                    }else if (strpos($object, 'collection.tar') !== false) {
                        continue;
                    } else {
                        if(file_exists($dir."/".$object) && is_writable($dir."/".$object)){
                            @unlink($dir."/".$object);
                        }
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }
}
