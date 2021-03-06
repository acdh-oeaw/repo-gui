<?php

namespace Drupal\oeaw\Helper;

use acdhOeaw\util\RepoConfig as RC;
use Drupal\oeaw\OeawFunctions;
use Drupal\oeaw\Model\OeawStorage;
use Drupal\oeaw\Model\RootViewModel;
use Drupal\oeaw\Helper\HelperFunctions as HF;

/**
 * Description of ComplexSearchViewHelper
 *
 * @author nczirjak
 */
class ComplexSearchViewHelper
{
    private $siteLang;
    private $oeawFunctions;
    private $oeawStorage;
    private $metadata;
    private $searchStr;
    private $solrData;
    private $sparqlData;
    private $pageData;
    private $currentPage;
    private $pagination = "";
    private $results = array();
    private $total = 0;
    private $model;
    private $fedora;
    
    public function __construct(
        $siteLang,
        \Drupal\oeaw\OeawFunctions $oeawFunctions,
        \Drupal\oeaw\Model\OeawStorage $oeawStorage,
        $fedora
    ) {
        $this->siteLang = $siteLang;
        $this->oeawFunctions = $oeawFunctions;
        $this->oeawStorage = $oeawStorage;
        $this->fedora = $fedora;
        $this->model = new \Drupal\oeaw\Model\ComplexSearchViewModel($this->fedora);
    }
    
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }
    
    public function getPagination(): string
    {
        return $this->pagination;
    }
    
    public function getTotal(): int
    {
        return $this->total;
    }
    
    public function getPageData()
    {
        return $this->pageData;
    }
    
    /**
    *
    * Prepare the searchString for the sparql Query
    *
    * @param string $string
    * @return array
    */
    public function explodeSearchString(string $string): array
    {
        $filters = array("type", "dates", "words", "mindate", "maxdate", "years", "solrsearch");
        //$operands = array("and" => "+", "not" => "-");
        $positions = array();
        
        $res = array();
        
        $strArr = explode('&', $string);
                
        foreach ($filters as $f) {
            foreach ($strArr as $arr) {
                if (strpos($arr, $f) !== false) {
                    $arr = str_replace($f.'=', '', $arr);
                    if (($f == "mindate") || ($f == "maxdate")) {
                        $arr = str_replace('+', '', $arr);
                    }
                    $res[$f] = $arr;
                }
            }
        }
        return $res;
    }
    
    /**
     * create the searchstring
     *
     * @param type $metadata
     */
    private function setUpMetadata($metadata)
    {
        $this->metadata = urldecode($metadata);
        $this->metadata = str_replace(' ', '+', $this->metadata);
        $this->searchStr = $this->explodeSearchString($this->metadata);
    }
    
    /**
     * paging html for the gui
     *
     * @param int $limit
     * @param int $page
     * @param int $total
     */
    public function handlePaging(int $limit, int $page, int $total)
    {
        //get the current page for the pagination
        $this->currentPage = $this->oeawFunctions->getCurrentPageForPagination();
        
        //create data for the pagination
        $this->pageData = $this->oeawFunctions->createPaginationData($limit, $page, $total);
        
        if ($this->pageData['totalPages'] >= 1) {
            $this->pagination =  $this->oeawFunctions->createPaginationHTML($this->currentPage, $this->pageData['page'], $this->pageData['totalPages'], $limit);
        }
    }
    
    /**
     * run the search sparql
     *
     * @param int $limit
     * @param int $page
     * @param string $order
     * @return array
     */
    private function runSparql(int $limit, int $page, string $order, bool $blazegraph = false): array
    {
        try {
            if (!$blazegraph) {
                $sparql = $this->model->createFullTextSparql($this->searchStr, $limit, $this->pageData['end'], false, $order);
            } else {
                $sparql = $this->model->createBGFullTextSparql($this->searchStr, $limit, $this->pageData['end'], false, $order);
            }
            $this->sparqlData = $this->oeawStorage->runUserSparql($sparql);
            return $this->sparqlData;
        } catch (\ErrorException $ex) {
            drupal_set_message($ex->getMessage(), 'error');
            return array();
        }
    }
    
    /**
     * Create the object from the search result
     *
     * @return type
     */
    private function createComplexSearchObject()
    {
        if (count($this->sparqlData) > 0) {
            foreach ($this->sparqlData as $r) {
                if ((isset($r['title']) && !empty($r['title']))
                        && (isset($r['uri']) && !empty($r['uri']))
                        && (isset($r['identifier']) && !empty($r['identifier']))
                        && (isset($r['acdhType']) && !empty($r['acdhType']))) {
                    $tblArray = array();

                    $arrayObject = new \ArrayObject();
                    $arrayObject->offsetSet('title', $r['title']);
                    $resourceIdentifier = $this->oeawFunctions->createDetailViewUrl($r);
                    $arrayObject->offsetSet('uri', $resourceIdentifier);
                    $arrayObject->offsetSet('fedoraUri', $r['uri']);
                    $arrayObject->offsetSet('insideUri', $this->oeawFunctions->detailViewUrlDecodeEncode($resourceIdentifier, 1));
                    $arrayObject->offsetSet('identifiers', $r['identifier']);
                    $arrayObject->offsetSet('pid', (isset($r['pid'])) ? $r['pid'] : "");
                    $arrayObject->offsetSet('type', str_replace(RC::get('fedoraVocabsNamespace'), '', $r['acdhType']));
                    $arrayObject->offsetSet('typeUri', $r['acdhType']);

                    if (isset($r['availableDate']) && !empty($r['availableDate'])) {
                        $arrayObject->offsetSet('availableDate', $r['availableDate']);
                    }
                    if (isset($r['accessRestriction']) && !empty($r['accessRestriction'])) {
                        $arrayObject->offsetSet('accessRestriction', $r['accessRestriction']);
                        
                        if (strpos($r['accessRestriction'], '/public') !== false) {
                            //check the title or normal image
                            
                            if (isset($r['image']) && !empty($r['image'])) {
                                $arrayObject->offsetSet('imageUrl', $r['image']);
                            } elseif (isset($r['hasTitleImage']) && !empty($r['hasTitleImage'])) {
                                $imageUrl = $this->oeawStorage->getImageByIdentifier($r['hasTitleImage']);
                                if ($imageUrl) {
                                    $arrayObject->offsetSet('imageUrl', $imageUrl);
                                    //setup the thumbnail services
                                    (!empty(HF::createThumbnailUrl($r['hasTitleImage']))) ? $arrayObject->offsetSet('imageThumbUrl', HF::createThumbnailUrl($r['hasTitleImage'])) : "";
                                }
                            }
                            //if the thumbnail is available
                            if (strpos(strtolower($r['acdhType']), 'image') !== false) {
                                $arrayObject->offsetSet('imageThumbUrl', HF::createThumbnailUrl($resourceIdentifier));
                            } elseif (
                                isset($r['category'])
                                    &&
                                strpos(strtolower($r['category']), 'image') !== false) {
                                $arrayObject->offsetSet('imageThumbUrl', HF::createThumbnailUrl($resourceIdentifier));
                            }
                        }
                    }
                    
                    if (isset($r['label']) && !empty($r['label'])) {
                        $arrayObject->offsetSet('bz_search', $r['label']);
                    }
                    if (isset($r['resultProp']) && !empty($r['resultProp'])) {
                        $arrayObject->offsetSet('resultProp', $r['resultProp']);
                    }
                    if (isset($r['highlighting']) && !empty($r['highlighting'])) {
                        $arrayObject->offsetSet('highlighting', $r['highlighting']);
                    }

                    if (count($tblArray) == 0) {
                        $tblArray['title'] = $r['title'];
                    }
                    

                    if (isset($r['description']) && !empty($r['description'])) {
                        $tblArray['description'] = $r['description'];
                    }
                    $arrayObject->offsetSet('table', $tblArray);
                    try {
                        $obj = new \Drupal\oeaw\Model\OeawResource($arrayObject, null, $this->siteLang);
                        $this->results[] = $obj;
                    } catch (ErrorException $ex) {
                        //throw new \ErrorException(t('Error').':'.__FUNCTION__, 'error');
                        return array();
                    }
                }
            }
        }
        
        return $this->results;
    }
    
    /**
     * Run the actual search
     *
     * @param string $metavalue
     * @param int $page
     * @param int $limit
     * @param string $order
     * @return array
     */
    public function search(string $metavalue, int $page, int $limit, string $order, bool $blazegraph = false): array
    {
        $this->setUpMetadata($metavalue);
        
        if (count((array)$this->searchStr) <= 0) {
            return array();
        }
        
        //get the solr data
        $this->getSolrData();
        //var_dump($this->solrData);
        //get the total resources
        $this->total = $this->getTotalResources($blazegraph);
        
        if ($this->total < 1) {
            return array();
        }
        
        //do the paging stuff
        $this->handlePaging($limit, $page, $this->total);
        //execute the sparql
        if (!isset($this->searchStr['solrsearch']) && empty($this->searchStr['solrsearch'])) {
            $this->runSparql($limit, $page, $order, $blazegraph);
        }

        //if we have solrdata then we will merge
        if (count((array)$this->solrData) > 0) {
            $this->sparqlData = array_merge((array)$this->sparqlData, (array)$this->solrData);
        }
        
        $this->createComplexSearchObject();
        return $this->results;
    }
    
    /**
     * Count the sparql resources
     *
     * @return int
     */
    private function countSparqlResources(bool $blazegraph = false): int
    {
        //custom sparql search
        try {
            if ($blazegraph === false) {
                $countSparql = $this->model->createFullTextSparql($this->searchStr, 0, 0, true, "titleasc", false);
            } else {
                $countSparql = $this->model->createBGFullTextSparql($this->searchStr, 0, 0, true, "titleasc", true);
            }
        } catch (\ErrorException $ex) {
            return 0;
        }
        $count = $this->oeawStorage->runUserSparql($countSparql);
        
        return (int)$count[0]['count'];
    }
    
    /**
     * Sum the sparql and solr resources
     *
     * @return int
     */
    private function getTotalResources(bool $blazegraph = false): int
    {
        $solrCount = count((array)$this->solrData);
        //if the user needs just the solr data
        if (isset($this->searchStr['solrsearch']) && !empty($this->searchStr['solrsearch'])) {
            $count = 0;
        } else {
            $count = $this->countSparqlResources($blazegraph);
        }
        return (int)$count + (int)$solrCount;
    }
    
    /**
     * get the data from the solr, based on the search metadata
     *
     * @return type
     */
    private function getSolrData()
    {
        //solr search
        if (!in_array("", $this->searchStr) === false) {
            drupal_set_message(t("Your search yielded no results."), 'error');
            return array();
        }
        /** Check the the of the search, it is necessary for the solr search **/
        if (
            isset($this->searchStr['words']) &&
            (
                (!isset($this->searchStr['type']))
                    ||
                (isset($this->searchStr['type']) && strtolower($this->searchStr['type']) == "resource")
            )
        ) {
            $this->solrData = $this->oeawFunctions->getDataFromSolr($this->searchStr['words']);
        }
    }
}
