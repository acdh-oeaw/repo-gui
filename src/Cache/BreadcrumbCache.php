<?php

namespace Drupal\oeaw\Cache;

use Drupal\oeaw\Model\OeawStorage;
use Drupal\oeaw\OeawFunctions;
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
        $oeawStorage = new OeawStorage();
        $oeawFunctions = new OeawFunctions();
        $result = array();
        $cacheData = array();
        $result = $oeawStorage->createBreadcrumbData($identifier);
        if (count($result) > 0) {
            $result = $oeawFunctions->formatBreadcrumbData($result);
            $cacheData = array($identifier => $result);
            \Drupal::cache()->set('breadcrumbs', $cacheData, CacheBackendInterface::CACHE_PERMANENT);
        }
        return $result;
    }
}
