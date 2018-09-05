<?php

namespace Drupal\oeaw;

use Drupal\oeaw\Model\OeawStorage;
use acdhOeaw\util\RepoConfig as RC;
use Drupal\Core\Cache\CacheBackendInterface;


/**
 * Description of SearchBoxCache
 *
 * @author nczirjak
 */
class SearchBoxCache {
    
    //\Drupal\Core\Cache\Cache::PERMANENT means cacheable forever,
    //\Drupal::cache()->set('cache_demo_posts', $posts, CacheBackendInterface::CACHE_PERMANENT);
    /**
     * Types: 1/acdhTypes. 2/entities. 3/formats
     * 
     * 
     */
    
    
    /**
     * Get the actually cached data by the type
     * 
     * @param string $type
     * @return array
     */
    public function getCachedData(string $type): array{
        
        $result = array();
        
        if(\Drupal::cache()->get($type)){
            $obj = \Drupal::cache()->get($type);
            $result = (array)$obj->data;
            
        }
        return $result;
    }
    
    /**
     * Set the DRUPAL cache based on the acdh ontology
     * 
     * @param string $uri
     * @return array
     */
    public function setCacheData(string $type): array{
        
        \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');
        $oeawStorage = new OeawStorage();
        $sqlRes = array();
        $result = array();
        if($type == "acdhTypes"){
            $sqlRes = $oeawStorage->getACDHTypes(true, true);
            $rs = array();
            //create the resource type data
            if(count($sqlRes) > 0){
                foreach($sqlRes as $val){
                    $type = str_replace(RC::get('fedoraVocabsNamespace'), '', $val['type']);
                    $count = str_replace(RC::get('fedoraVocabsNamespace'), '', $val['type'])." (".$val['typeCount'].")";
                    $rs[$type] = $count;
                }
                if(count($rs) > 0){
                    \Drupal::cache()->set('acdhTypes', $rs, CacheBackendInterface::CACHE_PERMANENT);
                    $result = $rs;
                }
            }else {
                return array();
            }
        } elseif($type == "entities"){
            $sqlRes = $oeawStorage->getDateForSearch();
            $ds = array();
            if(count($sqlRes) > 0) {
                foreach ($sqlRes as $df){
                    $ds[$df['year']] = $df['year']." (".$df['yearCount'].")";
                }

                if(count($ds) > 0){
                    \Drupal::cache()->set('entities', $ds, CacheBackendInterface::CACHE_PERMANENT);
                    $result = $ds;
                }
            }
            
        } elseif($type == "formats"){
            $sqlRes = $oeawStorage->getMimeTypes();
            $frm = array();
            if(count($sqlRes) > 0) {
                foreach($sqlRes as $val){            
                    $type = $val['mime'];
                    $count = $val['mime']." (".$val['mimeCount'].")";
                    $frm[$type] = $count;
                }
                if(count($frm) > 0){
                    \Drupal::cache()->set('formats', $frm, CacheBackendInterface::CACHE_PERMANENT);
                    $result = $frm;
                }
            }
        }
        return $result;
    }
}
