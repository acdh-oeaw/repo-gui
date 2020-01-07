<?php

namespace Drupal\oeaw\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Language\LanguageInterface;

use Drupal\oeaw\Model\OeawStorage;
use Drupal\oeaw\Model\OeawResource;
use Drupal\oeaw\Model\CacheModel;

use Drupal\oeaw\Helper\HelperFunctions;
use Drupal\oeaw\Helper\RootViewHelper;
use Drupal\oeaw\Helper\ComplexSearchViewHelper;
use Drupal\oeaw\Helper\DetailViewFunctions;
use Drupal\oeaw\Helper\CollectionFunctions;
use Drupal\oeaw\OeawFunctions;

use acdhOeaw\util\RepoConfig as RC;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;

use GuzzleHttp\Client;

/**
 * Class FrontendController
 *
 */
class FrontendController extends ControllerBase
{
    use StringTranslationTrait;
    
    /* plugin main DB class */
    private $oeawStorage;
    /* plugin main functions */
    private $oeawFunctions;
    
    private $uriFor3DObj;
    private $langConf;
    private $uuid;
    private $oeawDVFunctions;
    private $rootViewHelper;
    private $detailViewHelper;
    private $complexSearchViewHelper;
    private $fedora;
    private $userid;
    private $cacheModel;
    private $fedoraGlobalModDate;
    private $oeawCollectionFunc;
    private $siteLang;
    
    /**
     * Set up the necessary properties and config
     */
    public function __construct()
    {   
        $this->langConf = $this->config('oeaw.settings');
        $this->userid = \Drupal::currentUser()->id();
        $this->oeawFunctions = new OeawFunctions();
        $this->oeawStorage = new OeawStorage();
        $this->oeawDVFunctions = new DetailViewFunctions($this->langConf, $this->oeawFunctions, $this->oeawStorage);
        $this->fedora = $this->oeawFunctions->initFedora();
        (isset($_SESSION['language'])) ? $this->siteLang = strtolower($_SESSION['language'])  : $this->siteLang = "en";
        $GLOBALS['language'] = $this->siteLang;
        
        try {
            $this->cacheModel = new CacheModel();
        } catch (Exception $ex) {
            die("Cache DB is missing!");
        } catch (\Drupal\Core\Database\ConnectionNotDefinedException $ex) {
            die("Cache DB Connection is not definied");
        }
        
        try {
            $this->fedoraGlobalModDate = $this->oeawStorage->getFDLastModifDate();
        } catch (Exception $ex) {
            $this->fedoraGlobalModDate = "";
        } catch (\Drupal\Core\Database\ConnectionNotDefinedException $ex) {
            $this->fedoraGlobalModDate = "";
        }
        
        $this->oeawCollectionFunc = new CollectionFunctions($this->fedora, $this->oeawFunctions, $this->fedoraGlobalModDate, $this->cacheModel, $this->oeawStorage);
        $this->rootViewHelper = new RootViewHelper($this->siteLang, $this->oeawFunctions, $this->oeawStorage, $this->fedora);
        $this->detailViewHelper = new RootViewHelper($this->siteLang, $this->oeawFunctions, $this->oeawStorage, $this->fedora);
        $this->complexSearchViewHelper = new ComplexSearchViewHelper($this->siteLang, $this->oeawFunctions, $this->oeawStorage, $this->fedora);
    }

    /**
     *
     * The root Resources list
     *
     * @param int $limit Amount of resources to get
     * @param int $page nth Page for pagination
     * @param string $order Order resources by, usage: ASC/DESC(?property)
     *
     * @return array
     */
    public function roots_list(string $limit = "10", string $page = "1", string $order = "datedesc"): array
    {
        drupal_get_messages('error', true);
        // get the root resources
        // sparql result fields - uri, title
        $result = array();
        $datatable = array();
        $res = array();
        $limit = (int)$limit;
        $page = (int)$page;
        $page = $page-1;
        
        //count the root elements
        $countRes = $this->rootViewHelper->countRoots();
        
        $countRes = $countRes[0]["count"];
        if ($countRes == 0) {
            drupal_set_message(
                $this->langConf->get('errmsg_no_root_resources') ? $this->langConf->get('errmsg_no_root_resources') : 'You have no Root resources',
                'error',
                false
            );
            return array();
        }
        $search = array();
        //create data for the pagination
        $pageData = $this->oeawFunctions->createPaginationData($limit, $page, $countRes);
        $pagination = "";
        if ($pageData['totalPages'] > 1) {
            $pagination =  $this->oeawFunctions->createPaginationHTML($page, $pageData['page'], $pageData['totalPages'], $limit);
        }

        //Define offset for pagination
        if ($page > 0) {
            $offsetRoot = $page * $limit;
        } else {
            $offsetRoot = 0;
        }
        
        $result = $this->rootViewHelper->getRoots($limit, $offsetRoot, false, $order);
     
        $rootResources = array();
        if (count($result) > 0) {
            $rootResources = $this->rootViewHelper->createRootViewObject($result);
        } else {
            drupal_set_message(
                $this->langConf->get('errmsg_no_root_resources') ? $this->langConf->get('errmsg_no_root_resources') : 'You have no Root resources',
                'error',
                false
            );
            return array();
        }
        
        if (count($rootResources) <= 0) {
            drupal_set_message(
                $this->langConf->get('errmsg_no_root_resources') ? $this->langConf->get('errmsg_no_root_resources') : 'You have no Root resources',
                'error',
                false
            );
            return array();
        }
        
        //create the datatable values and pass the twig template name what we want to use
        $datatable = array(
            '#userid' => $this->userid,
            '#attached' => [
                'library' => [
                'oeaw/oeaw-styles',
                ]
            ]
        );
        
        if (count((array)$rootResources) > 0) {
            //$header = array_keys($res[0]);
            $datatable['#theme'] = 'oeaw_complex_search_res';
            $datatable['#result'] = $rootResources;
            $datatable['#search'] = $search;
            $datatable['#pagination'] = $pagination;
            //$datatable['#searchedValues'] = $i . ' top-level elements have been found.';
            $datatable['#totalResultAmount'] = $countRes;
            if (empty($pageData['page']) or $pageData['page'] == 0) {
                $datatable['#currentPage'] = 1;
            } else {
                $datatable['#currentPage'] = $pageData['page'] + 1;
            }
            if (empty($pageData) or $pageData['totalPages'] == 0) {
                $datatable['#totalPages'] = 1;
            } else {
                $datatable['#totalPages'] = $pageData['totalPages'];
            }
        }

        return $datatable;
    }
    
    /**
     * The detail view of the Resource
     *
     * @param string $res_data
     * @return array
     */
    public function oeaw_detail(string $res_data)
    {
        drupal_get_messages('error', true);
        $result = new \stdClass();
        $response = "html";
        $needsToCache = false;
                
        //we have the url and limit page data in the string
        if (empty($res_data)) {
            drupal_set_message(
                $this->langConf->get('errmsg_resource_not_exists') ? $this->langConf->get('errmsg_resource_not_exists') : 'Resource does not exist',
                'error'
            );
            return array();
        }
        
        //if we have ajax div reload
        if (strpos($res_data, 'ajax=1') !== false) {
            $response = "ajax";
        }
        //if recache is necessary
        if (strpos($res_data, 'recache=true') !== false) {
            $needsToCache = true;
        }
        
        //transform the url from the browser to readable uri
        $this->uuid = $this->oeawFunctions->detailViewUrlDecodeEncode($res_data, 0);
        
        if (empty($this->uuid)) {
            return $this->oeawFunctions->detailViewGuiErrosMsg($response, "Resource does not exist", "errmsg_resource_not_exists", $this->uuid);
        }
        
        $limitAndPage = $this->oeawDVFunctions->getLimitAndPageFromUrl($res_data);
        (isset($limitAndPage['page']) && !empty($limitAndPage['page'])) ? $page = $limitAndPage['page'] : $page = 1;
        (isset($limitAndPage['limit']) && !empty($limitAndPage['page'])) ? $limit = $limitAndPage['limit'] : $limit = 10;
        
        //then the cache
        if (!$this->cacheModel) {
            return $this->oeawFunctions->detailViewGuiErrosMsg($response, "External database is not exists", "errmsg_external_database_error", $this->uuid);
        }
        
        $actualCacheObj = $this->cacheModel->getCacheByUUID($this->uuid, $this->siteLang, "R");
        $fdDate = strtotime($this->fedoraGlobalModDate);
        
        
        if (isset($actualCacheObj->modify_date) && ($fdDate >  $actualCacheObj->modify_date)) {
            $needsToCache = true;
        } elseif (!isset($actualCacheObj->modify_date)) {
            $needsToCache = true;
        }
        
        //if the file with this date is exists
        if ((count((array)$actualCacheObj) > 0) && $needsToCache === false) {
            if (!empty($actualCacheObj->data)) {
                $result = unserialize($actualCacheObj->data);
                if (!is_object($result)) {
                    return $this->oeawFunctions->detailViewGuiErrosMsg($response, "Resource does not exist", "errmsg_resource_not_exists", $this->uuid);
                }
            } else {
                return $this->oeawFunctions->detailViewGuiErrosMsg($response, "Resource does not exist", "errmsg_resource_not_exists", $this->uuid);
            }
            //recache the cite
            $typesToBeCited = ["collection", "project", "resource", "publication", "metadata"];
            if (!empty($result->mainData->getType()) && in_array(strtolower($result->mainData->getType()), $typesToBeCited)) {
                //pass $rootMeta for rdf object
                $result->extraData["CiteThisWidget"] = $this->oeawFunctions->createCiteThisWidget($result->mainData);
            }
        } else {
            //run the generation scripts
            $result = $this->oeawDVFunctions->generateDetailViewMainData($this->fedora, $this->uuid, $this->siteLang);
            if (isset($result->error)) {
                return $this->oeawFunctions->detailViewGuiErrosMsg($response, $result->error, "", $this->uuid);
            }
            
            if (!$this->cacheModel->addCacheToDB($this->uuid, serialize($result), "R", $fdDate, $this->siteLang)) {
                return $this->oeawFunctions->detailViewGuiErrosMsg($response, "Database cache wasnt successful", "errmsg_db_cache_problems", $this->uuid);
            }
        }
        //get the tooltip from cache
        $cachedTooltip = $this->cacheModel->getCacheByUUID('ontology', $this->siteLang, "O");
        if (count((array)$cachedTooltip) > 0) {
            $result->extraData["tooltip"] = unserialize($cachedTooltip->data);
        }
        
        $datatable = array(
            '#theme' => 'oeaw_detail_dt',
            '#result' => (isset($result->mainData)) ? $result->mainData : array(),
            '#extras' => (isset($result->extraData)) ? $result->extraData : array(),
            '#userid' => $this->userid,
            '#attached' => [
                'library' => [
                'oeaw/oeaw-styles', //include our custom library for this response
                ]
            ]
        );
        //for the ajax oeaw_detail view page refresh we need to send a response
        //othwerwise itt will post the whole page
        if ($response == "ajax") {
            return new Response(render($datatable));
        }
        
        return $datatable;
    }
   
    /**
     * Change language session variable API
     * Because of the special path handling, the basic language selector is not working
     *
     * @param string $lng
     * @return Response
    */
    public function oeaw_change_lng(string $lng = 'en'): Response
    {
        $_SESSION['language'] = strtolower($lng);
        $response = new Response();
        $response->setContent(json_encode("language changed to: ".$lng));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
    
   
    /**
     * This API will generate the child html view.
     *
     * @param string $identifier - the UUID
     * @param string $page
     * @param string $limit
     */
    public function oeaw_child_api(string $identifier, string $limit, string $page, string $order): Response
    {
        if (strpos($identifier, RC::get('fedoraUuidNamespace')) === false) {
            $identifier = RC::get('fedoraUuidNamespace').$identifier;
        }
        $childArray = $this->oeawFunctions->generateChildAPIData($identifier, (int)$limit, (int)$page, $order);
         
        if (count($childArray['childResult']) == 0) {
            $childArray['errorMSG'] =
                $this->langConf->get('errmsg_no_child_resources') ? $this->langConf->get('errmsg_no_child_resources') : 'There are no Child resources';
        }
        
        $childArray['language'] = \Drupal::languageManager()->getCurrentLanguage()->getId();
        
        $build = [
            '#theme' => 'oeaw_child_view',
            '#result' => $childArray,
            '#attached' => [
                'library' => [
                    'oeaw/oeaw-styles', //include our custom library for this response
                ]
            ]
        ];
        
        return new Response(render($build));
    }
    
    /**
     * This API will generate the turtle file from the resource.
     *
     * @param string $identifier - the UUID
     * @param string $page
     * @param string $limit
     */
    public function oeaw_turtle_api(string $identifier): Response
    {
        $identifier = $this->oeawFunctions->detailViewUrlDecodeEncode($identifier, 0);
        $fedoraUrl = "";
        $fedoraUrl = $this->oeawStorage->getFedoraUrlByIdentifierOrPid($identifier);
        
        if (!empty($fedoraUrl)) {
            $result = $this->oeawFunctions->turtleDissService($fedoraUrl);
            return new Response($result, 200, ['Content-Type'=> 'text/turtle']);
        }
        return new Response("No data!", 400);
    }
   
    /**
     *
     * The complex search frontend function
     *
     * @param string $metavalue
     * @param string $limit
     * @param string $page
     * @param string $order
     * @return array
     * @throws \ErrorException
     */
    public function oeaw_complexsearch(string $metavalue = "root", string $limit = "10", string $page = "1", string $order = "datedesc"): array
    {
        $time = microtime();
        $time = explode(' ', $time);
        $time = $time[1] + $time[0];
        $start = $time;
        
        drupal_get_messages('error', true);
       
        if (empty($metavalue)) {
            $metavalue = "root";
        }
        
        //If the discover page calls the root resources forward to the root_list method
        if ($metavalue == 'root') {
            //If a cookie setting exists and the query is coming without a specific parameter
            if ((isset($_COOKIE["resultsPerPage"]) && !empty($_COOKIE["resultsPerPage"])) && empty($limit)) {
                $limit = $_COOKIE["resultsPerPage"];
            }
            if ((isset($_COOKIE["resultsOrder"]) && !empty($_COOKIE["resultsOrder"])) && empty($order)) {
                $order = $_COOKIE["resultsOrder"];
            }
            if (empty($page)) {
                $page = "1";
            }
            return $this->roots_list($limit, $page, $order);
        } else {
            
            //Deduct 1 from the page since the backend works with 0 and the frontend 1 for the initial page
            $page = (int)$page - 1;
            $limit = (int)$limit;
            $result = array();
            $total = 0;
            
            $result = $this->complexSearchViewHelper->search($metavalue, $page, $limit, $order);
            
            if (count((array)$result) <= 0) {
                drupal_set_message(t("Your search yielded no results."), 'error');
                return array();
            }
                        
            $pagination = $this->complexSearchViewHelper->getPagination();
            $total = $this->complexSearchViewHelper->getTotal();
            $pageData = $this->complexSearchViewHelper->getPageData();
           
            if (count($result) == 0) {
                return array();
            }
         
            $time = microtime();
            $time = explode(' ', $time);
            $time = $time[1] + $time[0];
            $finish = $time;
            $total_time = round(($finish - $start), 4);
            $datatable['#pageGeneration'] = $total_time;
            
            $datatable['#theme'] = 'oeaw_complex_search_res';
            $datatable['#userid'] = $this->userid;
            $datatable['#pagination'] = $pagination;
            $datatable['#result'] = $result;
            //$datatable['#searchedValues'] = $total . ' elements containing "' . $metavalue . '" have been found.';
            $datatable['#totalResultAmount'] = $total;

            if (empty($pageData['page']) or $pageData['page'] == 0) {
                $datatable['#currentPage'] = 1;
            } else {
                $datatable['#currentPage'] = $pageData['page'] + 1;
            }
            if (empty($pageData) or $pageData['totalPages'] == 0) {
                $datatable['#totalPages'] = 1;
            } else {
                $datatable['#totalPages'] = $pageData['totalPages'];
            }
            
            return $datatable;
        }
    }
    
    
    public function oeaw_search(string $metavalue = "root", string $limit = "10", string $page = "1", string $order = "datedesc"): array
    {
        $time = microtime();
        $time = explode(' ', $time);
        $time = $time[1] + $time[0];
        $start = $time;
        drupal_get_messages('error', true);
       
        if (empty($metavalue)) {
            $metavalue = "root";
        }
        
        //If the discover page calls the root resources forward to the root_list method
        if ($metavalue == 'root') {
            //If a cookie setting exists and the query is coming without a specific parameter
            if ((isset($_COOKIE["resultsPerPage"]) && !empty($_COOKIE["resultsPerPage"])) && empty($limit)) {
                $limit = $_COOKIE["resultsPerPage"];
            }
            if ((isset($_COOKIE["resultsOrder"]) && !empty($_COOKIE["resultsOrder"])) && empty($order)) {
                $order = $_COOKIE["resultsOrder"];
            }
            if (empty($page)) {
                $page = "1";
            }
            return $this->roots_list($limit, $page, $order);
        } else {
            
            //Deduct 1 from the page since the backend works with 0 and the frontend 1 for the initial page
            $page = (int)$page - 1;
            $limit = (int)$limit;
            $result = array();
            $total = 0;
            
            $result = $this->complexSearchViewHelper->search($metavalue, $page, $limit, $order, true);
            
            if (count((array)$result) <= 0) {
                drupal_set_message(t("Your search yielded no results."), 'error');
                return array();
            }
                        
            $pagination = $this->complexSearchViewHelper->getPagination();
            $total = $this->complexSearchViewHelper->getTotal();
            $pageData = $this->complexSearchViewHelper->getPageData();
           
            if (count($result) == 0) {
                return array();
            }
            
            $datatable['#theme'] = 'oeaw_complex_search_res';
            $datatable['#userid'] = $this->userid;
            $datatable['#pagination'] = $pagination;
            $datatable['#result'] = $result;
            //$datatable['#searchedValues'] = $total . ' elements containing "' . $metavalue . '" have been found.';
            $datatable['#totalResultAmount'] = $total;

            if (empty($pageData['page']) or $pageData['page'] == 0) {
                $datatable['#currentPage'] = 1;
            } else {
                $datatable['#currentPage'] = $pageData['page'] + 1;
            }
            if (empty($pageData) or $pageData['totalPages'] == 0) {
                $datatable['#totalPages'] = 1;
            } else {
                $datatable['#totalPages'] = $pageData['totalPages'];
            }
            $time = microtime();
            $time = explode(' ', $time);
            $time = $time[1] + $time[0];
            $finish = $time;
            $total_time = round(($finish - $start), 4);
            //"search functions time: ".$total_time = round(($finish - $start), 4);
            $datatable['#pageGeneration'] = $total_time;
            return $datatable;
        }
    }
    
    /**
     * Cache the acdh ontology inside drupal
     *
     * @return Response
     */
    public function oeaw_cache_ontology(): Response
    {
        $result = array();
        $responseTXT = "";
        $langs = array("en", "de");
        $fdDate = strtotime($this->fedoraGlobalModDate);
        
        foreach ($langs as $lng) {
            $data = $this->oeawStorage->getOntologyForCache($lng);
            if (count($data) > 0) {
                foreach ($data as $d) {
                    $shortcut = "";
                    $shortcut = $this->oeawFunctions->createPrefixesFromString($d["id"]);
                    if ($shortcut) {
                        $result[$shortcut] = array("title" => $d["title"], "desc" => $d["comment"]);
                    }
                }
            }

            if (count($result) > 0) {
                if (!$this->cacheModel->addCacheToDB('ontology', serialize($result), "O", $fdDate, $lng)) {
                    $responseTXT .= $lng.":";
                    $responseTXT .= ($this->langConf->get('errmsg_db_cache_problems') ? $this->langConf->get('errmsg_db_cache_problems') : 'Database cache wasnt successful');
                } else {
                    $responseTXT .= "Ontology - ".$lng." - Cache ready! ";
                }
            } else {
                $responseTXT .= "No data - ".$lng;
            }
        }
        
        $response = new Response();
        $response->setContent(json_encode($responseTXT));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
    
    /**
     * Download Whole Collection python script
     *
     * @param string $url
     * @return Response
     */
    public function oeaw_get_collection_dl_script(string $url): Response
    {
        $url = str_replace(":", "/", $url);
        $url = "https://".$url;
        $result = $this->oeawDVFunctions->changeCollDLScript($url);
        
        $response = new Response();
        $response->setContent($result);
        $response->headers->set('Content-Type', 'application/x-python-code');
        $response->headers->set('Content-Disposition', 'attachment; filename=collection_download_script.py');
        return $response;
    }
    
    /**
     *
     * This function is for the oeaw_detail view. to the user can get the inverse table data
     *
     * @param string $data - the resource url
     * @return Response
     */
    public function oeaw_inverse_result(string $data): Response
    {
        $invData = array();
        
        if (!empty($data)) {
            $identifier = $this->oeawFunctions->detailViewUrlDecodeEncode($data, 0);
            $res = $this->oeawStorage->getInverseViewDataByIdentifier($identifier);
            
            if (count($res) == 0) {
                $invData["data"] = array();
            } else {
                for ($index = 0; $index <= count($res) - 1; $index++) {
                    $title = $res[$index]['title'];
                    $insideUri = $res[$index]['insideUri'];
                    $invData["data"][$index] = array($res[$index]['shortcut'], "<a href='/browser/oeaw_detail/$insideUri'>$title</a>");
                }
            }
        }
        $response = new Response();
        $response->setContent(json_encode($invData));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
    
    /**
      *
      * This function is for the oeaw_detail view. It is used for the Organisations view, to get the isMembers
      *
      * @param string $data - the resource url
      * @return Response
    */
    public function oeaw_ismember_result(string $data): Response
    {
        $memberData = array();
                
        if (!empty($data)) {
            $identifier = $this->oeawFunctions->detailViewUrlDecodeEncode($data, 0);
            $fdUrlArr = array();
            $fdUrlArr = $this->oeawStorage->getTitleByIdentifier($identifier, $this->siteLang);
            if (count($fdUrlArr) > 0) {
                if (isset($fdUrlArr[0]['uri'])) {
                    $uri = $fdUrlArr[0]['uri'];
                    $res = $this->oeawStorage->getIsMembers($uri, $this->siteLang);
                }
             
                if (count($res) <= 0) {
                    $memberData["data"] = array();
                } else {
                    for ($index = 0; $index <= count($res) - 1; $index++) {
                        if (!empty($res[$index]['title']) &&
                                (isset($res[$index]['childId']) || isset($res[$index]['childUUID']) ||
                                isset($res[$index]['externalId']))) {
                            $title = $res[$index]['title'];
                            if (!empty($res[$index]['childId'])) {
                                $insideUri = $this->oeawFunctions->detailViewUrlDecodeEncode($res[$index]['childId'], 1);
                            } elseif (!empty($res[$index]['childUUID'])) {
                                $insideUri = $this->oeawFunctions->detailViewUrlDecodeEncode($res[$index]['childUUID'], 1);
                            } elseif (!empty($res[$index]['externalId'])) {
                                $insideUri = $this->oeawFunctions->detailViewUrlDecodeEncode($res[$index]['externalId'], 1);
                            }
                            
                            $memberData["data"][$index] = array("<a href='/browser/oeaw_detail/$insideUri'>$title</a>");
                        }
                    }
                }
            }
        }

        $response = new Response();
        $response->setContent(json_encode($memberData));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
    
    
    /**
     *
     * This function will download the 3d model with a guzzle async request.
     * After the download it will save the file
     * to the drupal/sites/files/file_name_dir/file_name.extension directory and
     * pass the url to the 3d viewer template
     *
     * @param string $data -> the resource pid or identifier for the 3d content
     * @return array
     */
    public function oeaw_3d_viewer(string $data): array
    {
        if (empty($data)) {
            drupal_set_message(t('No').' '.t('Data'), 'error', false);
            return array();
        }
        $templateData["insideUri"] = $data;
        $identifier = $this->oeawFunctions->detailViewUrlDecodeEncode($data, 0);
        
        $templateData = array();
        //get the title and the fedora url
        $fdUrlArr = array();
        $fdUrlArr = $this->oeawStorage->getTitleByIdentifier($identifier);
        
        if (count($fdUrlArr) > 0) {
            if (isset($fdUrlArr[0]['title'])) {
                $templateData["title"] = $fdUrlArr[0]['title'];
            }
            if (isset($fdUrlArr[0]) && isset($fdUrlArr[0]['uri'])) {
                $fdUrl = $fdUrlArr[0]['uri'];
            } else {
                drupal_set_message(t('The URL %url is not valid.', $fdUrl), 'error', false);
                return array();
            }
        } else {
            drupal_set_message(t('No').' '.t('Data'), 'error', false);
            return array();
        }
        
        //get the filename
        $fdFileName = $this->oeawStorage->getValueByUriProperty($fdUrl, "http://www.ebu.ch/metadata/ontologies/ebucore/ebucore#filename");
        $fdFileSize = $this->oeawStorage->getValueByUriProperty($fdUrl, RC::get('fedoraExtentProp'));
        //if we have a filename in the fedora
        if (isset($fdFileName[0]["value"]) && (count($fdFileName) > 0)) {
            //get the title
            $dir = str_replace(".", "_", $fdFileName[0]["value"]);
            $fileDir = $_SERVER['DOCUMENT_ROOT'].'/sites/default/files/'.$dir.'/'.$fdFileName[0]["value"];
            
            //if the filename is exists then we will not download it again from the server
            if ((file_exists($fileDir)) && (isset($fdFileSize[0]['value']) &&  $fdFileSize[0]['value'] == filesize($fileDir))) {
                $url = '/sites/default/files/'.$dir.'/'.$fdFileName[0]["value"];
                
                $result =  array(
                        '#theme' => 'oeaw_3d_viewer',
                        '#ObjectUrl' => $url,
                        '#templateData' => $templateData,
                    );
                return $result;
            }
        } else {
            drupal_set_message(t('Missing').':'.t('File information'), 'error', false);
            return array();
        }
        
        //this is a new 3d model, so we need to download it to the server.
        $client = new \GuzzleHttp\Client(['auth' => [RC::get('fedoraUser'), RC::get('fedoraPswd')], 'verify' => false]);
        
        try {
            $request = new \GuzzleHttp\Psr7\Request('GET', $fdUrl);
            //send async request
            $promise = $client->sendAsync($request)->then(function ($response) {
                if ($response->getStatusCode() == 200) {
                    //get the filename
                    if (count($response->getHeader('Content-Disposition')) > 0) {
                        $txt = explode(";", $response->getHeader('Content-Disposition')[0]);
                        $filename = "";
                        $extension = "";
                        
                        foreach ($txt as $t) {
                            if (strpos($t, 'filename') !== false) {
                                $filename = str_replace("filename=", "", $t);
                                $filename = str_replace('"', "", $filename);
                                $filename = ltrim($filename);
                                $extension = explode(".", $filename);
                                $extension = end($extension);
                                continue;
                            }
                        }

                        if ($extension == "nxs" || $extension == "ply") {
                            if (!empty($filename)) {
                                $dir = str_replace(".", "_", $filename);
                                $tmpDir = $_SERVER['DOCUMENT_ROOT'].'/sites/default/files/'.$dir.'/';
                                //if the file dir is not exists then we will create it
                                // and we will download the file
                                if (!file_exists($tmpDir) || !file_exists($tmpDir.'/'.$filename)) {
                                    mkdir($tmpDir, 0777);
                                    $file = fopen($tmpDir.'/'.$filename, "w");
                                    fwrite($file, $response->getBody());
                                    fclose($file);
                                } else {
                                    //if the file is not exists
                                    if (!file_exists($tmpDir.'/'.$filename)) {
                                        $file = fopen($tmpDir.'/'.$filename, "w");
                                        fwrite($file, $response->getBody());
                                        fclose($file);
                                    }
                                }
                                $url = '/sites/default/files/'.$dir.'/'.$filename;
                                $this->uriFor3DObj['result'] = $url;
                                $this->uriFor3DObj['error'] = "";
                            }
                        } else {
                            $this->uriFor3DObj['error'] = t('File extension').' '.t('Error');
                            $this->uriFor3DObj['result'] = "";
                        }
                    }
                } else {
                    $this->uriFor3DObj['error'] = t('No files available.');
                    $this->uriFor3DObj['result'] = "";
                }
            });
            $promise->wait();
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            $this->uriFor3DObj['error'] = $ex->getMessage();
            
            $result =
                array(
                    '#theme' => 'oeaw_3d_viewer',
                    '#errorMSG' =>  $this->uriFor3DObj['error']
                );
        
            return $result;
        }
        $result =
                array(
                    '#theme' => 'oeaw_3d_viewer',
                    '#ObjectUrl' => $this->uriFor3DObj['result'],
                    '#templateData' => $templateData,
                    '#errorMSG' =>  $this->uriFor3DObj['error']
                );
        
        return $result;
    }
        
      
    /**
      *
      * Displaying the federated login with shibboleth
      *
      * @return array
      */
    public function oeaw_shibboleth_login()
    {
        $result = array();
        $userid = \Drupal::currentUser()->id();
        if ((isset($_SERVER['HTTP_EPPN']) && $_SERVER['HTTP_EPPN'] != "(null)")
               && (isset($_SERVER['HTTP_AUTHORIZATION']) && $_SERVER['HTTP_AUTHORIZATION'] != "(null)")
                ) {
            drupal_set_message(t('You are logged in as '.$_SERVER['HTTP_EPPN']), 'status', false);
            //if we already logged in with shibboleth then login the user with the shibboleth account
            $this->oeawFunctions->handleShibbolethUser();
            return $result;
        } else {
            $result =
                array(
                    '#cache' => ['max-age' => 0,],
                    '#theme' => 'oeaw_shibboleth_login'
                );
        }
        return $result;
    }
    
    
    /**
     *
     * Displaying the iiif viewer
     *
     * @param string $uri
     * @return array
     */
    public function oeaw_iiif_viewer(string $uri): array
    {
        $resData = array();
        $identifier = "";
        if (empty($uri)) {
            drupal_set_message(
                $this->langConf->get('errmsg_url_not_valid') ? $this->langConf->get('errmsg_url_not_valid') : 'The URL is not valid!',
                'error'
            );
            return array();
        } else {
            $identifier = $this->oeawFunctions->detailViewUrlDecodeEncode($uri, 0);
            if ($identifier) {
                $fdUrl = $this->oeawStorage->getFedoraUrlByIdentifierOrPid($identifier);
                //loris url generating fucntion
                $resData = HelperFunctions::generateLorisUrl($fdUrl);
            }
            if (count($resData) == 0) {
                drupal_set_message(
                    $this->langConf->get('errmsg_image_not_valid') ? $this->langConf->get('errmsg_image_not_valid') : 'The Image is not valid!',
                    'error'
                );
                return array();
            }
            $resData['insideUri'] = $this->oeawFunctions->detailViewUrlDecodeEncode($identifier, 1);
        }
        
        $result =
            array(
                '#theme' => 'oeaw_iiif_viewer',
                '#url' => $uri,
                '#templateData' => $resData
            );
        return $result;
    }
    
    /**
     *
     * The view for the collection download with some basic information
     *
     * @param string $uri
     * @return string
     */
    public function oeaw_dl_collection_view(string $uri): array
    {
        $errorMSG = "";
        $resData = array();
        $resData['dl'] = false;
        $resData['insideUri'] = $uri;
        $encIdentifier = $uri;
        $uri = $this->oeawFunctions->detailViewUrlDecodeEncode($uri, 0);
        
        if (empty($uri)) {
            $errorMSG = "There is no valid URL";
        } else {
            $resData = $this->oeawCollectionFunc->getCollectionData($uri, true);
        
            if (count($resData) == 0) {
                drupal_set_message(
                    $this->langConf->get('errmsg_collection_not_exists') ? $this->langConf->get('errmsg_collection_not_exists') : 'The Collection does not exist!',
                    'error',
                    false
                );
                return array();
            }
        }
        
        $result =
            array(
                '#theme' => 'oeaw_dl_collection_tree',
                '#url' => $encIdentifier,
                '#resourceData' => $resData,
                '#errorMSG' =>  $errorMSG,
                '#attached' => [
                    'library' => [
                        'oeaw/oeaw-DL_collection',
                    ]
                ]
            );
         
        return $result;
    }
    
    /**
     *
     * This controller view is for the ajax collection tree view generating
     *
     * @param string $uri
     * @return Response
    */
    public function oeaw_get_collection_data(string $uri) : Response
    {
        if (empty($uri)) {
            $errorMSG = t('Missing').': Identifier';
        } else {
            $resData['insideUri'] = $uri;
            $identifier = $this->oeawFunctions->detailViewUrlDecodeEncode($uri, 0);
            $resData = $this->oeawCollectionFunc->getCollectionData($identifier, true);
        }
        
        //setup the the treeview data
        $result = array();
        //add the main Root element
        $first = array(
            "uri" => $uri,
            "uri_dl" => $resData['metadata']->data['fedoraUri'],
            "title" => $resData['metadata']->data['title'],
            "text" => $resData['metadata']->data['title'],
            "filename" => str_replace(" ", "_", $resData['metadata']->data['title']),
            "resShortId" => str_replace("id.acdh.oeaw.ac.at:uuid:", "", $uri),
            "parentId" => "");
        
        $new = array();
        foreach ($resData['binaries'] as $a) {
            $new[$a['parentId']][] = $a;
        }
        
        $result = $this->oeawFunctions->convertToTreeById($new, array($first));
        $handle = fopen($_SERVER['DOCUMENT_ROOT'].'/sites/default/files/collections/'.str_replace("id.acdh.oeaw.ac.at:uuid:", "", $uri).'.txt', "w");
        fwrite($handle, json_encode($result));
        fclose($handle);
        $response = new Response();
        $response->setContent(json_encode($result));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
    
   
    /**
     *
     * The selected files zip download func
     *
     * @param string $uri
     * @return array
     * @throws \Exception
     */
    public function oeaw_dl_collection(string $uri): Response
    {
        $result = array();
        $GLOBALS['resTmpDir'] = "";
        $dateID = date("Ymd_his");
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        
        //the binary files
        (json_decode($_POST['jsonData'], true)) ? $binaries = json_decode($_POST['jsonData'], true) : $binaries = array();
        
        if (count($binaries) == 0) {
            $response->setContent(json_encode(""));
            return $response;
        }
        
        $identifier = $this->oeawFunctions->detailViewUrlDecodeEncode($uri, 0);
        $fedoraUrl = $this->oeawStorage->getFedoraUrlByIdentifierOrPid($identifier);
        
        $tmpDirDate = $this->oeawCollectionFunc->setupDirForCollDL($dateID);
        $this->oeawCollectionFunc->downloadFiles($binaries);
        
        $ttl = "";
        if (!empty($fedoraUrl)) {
            $ttl = $this->oeawFunctions->turtleDissService($fedoraUrl);
            if (!empty($ttl)) {
                $turtleFile = fopen($tmpDirDate.'/turtle.ttl', "w");
                fwrite($turtleFile, $ttl);
                fclose($turtleFile);
                chmod($tmpDirDate.'/turtle.ttl', 0777);
            }
        }
        
        //if we have files in the directory
        $dirFiles = scandir($tmpDirDate);
        if (count($dirFiles) > 0) {
            chmod($GLOBALS['resTmpDir'], 0777);
            $archiveFile = $tmpDirDate.'/collection.tar';
            fopen($archiveFile, "w");
            fclose($archiveFile);
            chmod($archiveFile, 0777);
            
            try {
                $tar = new \Drupal\Core\Archiver\Tar($archiveFile);
                foreach ($dirFiles as $d) {
                    if ($d == "." || $d == ".." || $d == 'collection.tar') {
                        continue;
                    } else {
                        $tarFilename = $d;
                        //if the filename is bigger than 100chars, then we need
                        //to shrink it
                        if (strlen($d) > 100) {
                            $ext = pathinfo($d, PATHINFO_EXTENSION);
                            $tarFilename = str_replace($ext, '', $d);
                            $tarFilename = substr($tarFilename, 0, 90);
                            $tarFilename = $tarFilename.'.'.$ext;
                        }
                        chdir($tmpDirDate.'/');
                        $tar->add($d);
                    }
                }
            } catch (Exception $e) {
                $response->setContent(json_encode(""));
            }
            $this->oeawCollectionFunc->removeDirContent($tmpDirDate);
            $hasTar = RC::get('guiBaseUrl').'/sites/default/files/collections/'.$dateID.'/collection.tar';
        }
        
        $response->setContent(json_encode($hasTar));
        return $response;
    }
}
