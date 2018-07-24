<?php

namespace Drupal\oeaw;

use Drupal\oeaw\Model\OeawStorage;
use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\FedoraResource;
use acdhOeaw\util\RepoConfig as RC;
use EasyRdf\Graph;
use EasyRdf\Resource;
use Drupal\Core\Cache\CacheBackendInterface;


/**
 * Description of PropertyTableCache
 *
 * @author nczirjak
 */
class PropertyTableCache {
    
    
    //\Drupal\Core\Cache\Cache::PERMANENT means cacheable forever,
    //\Drupal::cache()->set('cache_demo_posts', $posts, CacheBackendInterface::CACHE_PERMANENT);
    
    
    /**
     * 
     * Get the actually cached property data by the expert view table array
     * 
     * @param array $data
     * @return type
     */
    public function getCachedData(array $data): array{
        //get the keys from the array which are the propertys. fe: acdh:hasContact
        $keys = array_keys($data);

        $result = array();
            
        if(count($keys) > 0){
            foreach ($keys as $k){
                if(\Drupal::cache()->get($k)){
                    $obj = \Drupal::cache()->get($k);
                    if(!empty($obj->data["title"]) && !empty($obj->data["desc"])){
                        $result[$k] = array("title" => $obj->data["title"], "desc" => $obj->data["desc"]);
                    }
                }
            }
        }
        return $result;
    }
    
    /**
     * Set the DRUPAL cache based on the acdh ontology
     * 
     * @return boolean
     */
    public function setCacheData(){
        $OS = new OeawStorage();
        $data = $OS->getOntologyForCache();
        $OF = new OeawFunctions();
        
        $result = false;
        
        if( count($data) > 0 ){
            foreach($data as $d){
                $shortcut = "";
                $shortcut = $OF->createPrefixesFromString($d["id"]);
                if($shortcut){
                    \Drupal::cache()->set($shortcut, array("title" => $d["title"], "desc" => $d["comment"]), CacheBackendInterface::CACHE_PERMANENT);
                    $result = true;
                }
            }
        }
        return $result;
    }
}
