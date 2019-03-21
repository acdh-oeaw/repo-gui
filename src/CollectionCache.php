<?php

namespace Drupal\oeaw;

use Drupal\oeaw\Model\OeawStorage;
use Drupal\oeaw\Model\OeawCustomSparql;
use Drupal\oeaw\ConnData;
use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\FedoraResource;
use acdhOeaw\util\RepoConfig as RC;
use EasyRdf\Graph;
use EasyRdf\Resource;
use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Description of CollectionCache
 *
 * @author nczirjak
 */
class CollectionCache
{
    
    //\Drupal\Core\Cache\Cache::PERMANENT means cacheable forever,
    //\Drupal::cache()->set('cache_demo_posts', $posts, CacheBackendInterface::CACHE_PERMANENT);
    
    /**
     * Get the actually cached property data by the expert view table array
     *
     * @param string $uri
     * @return array
     */
    public function getCachedData(string $uri): array
    {
        $result = array();
        if (\Drupal::cache()->get($uri)) {
            $obj = \Drupal::cache()->get($uri);
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
    public function setCacheData(string $uri): array
    {
        $oeawCustSparql = new OeawCustomSparql();
        $collBinSql = $oeawCustSparql->getCollectionBinaries($uri);
        $result = array();
        if (!empty($collBinSql)) {
            $OeawStorage = new OeawStorage();
            $bin = $OeawStorage->runUserSparql($collBinSql);
            if (count($bin) > 0) {
                \Drupal::cache()->set($uri, $bin, CacheBackendInterface::CACHE_PERMANENT);
                $result = $bin;
            }
        }
        return $result;
    }
}
