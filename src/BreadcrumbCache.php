<?php

namespace Drupal\oeaw;

use Drupal\oeaw\Model\OeawStorage;
use acdhOeaw\util\RepoConfig as RC;
use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Description of BreadcrumbCache
 *
 * @author nczirjak
 */
class BreadcrumbCache
{
    
    /**
     * Get the cached breadcrumb by the resource identifier
     * @param string $identifier
     * @return array
     */
    public function getCachedData(string $identifier): array
    {
        $result = array();
        
        if (\Drupal::cache()->get('breadcrumbs')) {
            $obj = \Drupal::cache()->get('breadcrumbs');
            if (isset($obj->data[$identifier])) {
                $result = (array)$obj->data[$identifier];
            }
        }
        return $result;
    }
    
    /**
     * Create and set the breadcrumb cache based on the identifier
     *
     * @param string $identifier
     * @return array
     */
    public function setCacheData(string $identifier): array
    {
        \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');
        $oeawStorage = new OeawStorage();
        $result = array();
        $cacheData = array();
        $result = $oeawStorage->createBreadcrumbData($identifier);
        if (count($result) > 0) {
            $cacheData = array($identifier => $result);
            \Drupal::cache()->set('breadcrumbs', $cacheData, CacheBackendInterface::CACHE_PERMANENT);
        }
        return $result;
    }
}
