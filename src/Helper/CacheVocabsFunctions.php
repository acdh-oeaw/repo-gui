<?php

namespace Drupal\oeaw\Helper;

use Drupal\oeaw\Model\CacheVocabsModel;

/**
 * Description of CacheVocabsFunctions
 *
 * @author nczirjak
 */
class CacheVocabsFunctions {
    
    public $customCache = array();
    private $cacheVocabs;
    private $vocabs = array(
        "acdh:hasAccessRestriction" => "https://vocabs.acdh.oeaw.ac.at/download/archeaccessrestrictions.rdf",
        "acdh:hasLifeCycleStatus" => "https://vocabs.acdh.oeaw.ac.at/download/archelifecyclestatus.rdf",
        "acdh:hasCategory" => "https://vocabs.acdh.oeaw.ac.at/download/archecategory.rdf"
    );
    
    
    public function __construct() {
        $this->cacheVocabs = new \Drupal\oeaw\Model\CacheVocabsModel();
    }
    
    public function getVocabsTitle(string $lang = "en"): array {
        $lang = strtolower($lang);
        $this->getVocabsFromDB($lang);
        
        if(count((array)$this->customCache) < 1) {
            //get the actual and save them
            $this->getControlledVocabStrings($lang);
        }
        
        
        return $this->customCache;
        
    }
    
    /**
     * get the vocabs values
     */
    private function getControlledVocabStrings(string $lang = "en"): void
    {
        $result = array();
        
        try {
            $graph = new \EasyRdf\Graph();
            foreach($this->vocabs as $k => $v){
                if ($graph->parse(file_get_contents($v))) {
                    foreach ($graph->allOfType('http://www.w3.org/2004/02/skos/core#Concept') as $i) {
                        $uri = $i->getUri();
                        $label = $i->getLiteral('http://www.w3.org/2004/02/skos/core#prefLabel', $lang);
                        if ((isset($label) && !empty($label))
                                &&
                            (isset($uri) && !empty($uri))) {
                            $label = (string)$label;
                            $uri = (string)$uri;
                            $obj = new \stdClass();
                            $obj->label = $label;
                            $obj->uri = $uri;
                            $obj->lng = $lang;
                            $this->cacheVocabs->addCacheToDB($k, $label, $uri, $lang);
                            $this->customCache[$lang][$k][] = $obj;
                        }
                    }
                }
            }       
        } catch (Exception $ex) {
            $this->customCache = array();
        } catch (\EasyRdf\Exception $ex) {
            $this->customCache = array();
        }
    }
    
    /**
     * Get ALL vocabs cache from db
     * 
     * @param type $lang
     */
    private function getVocabsFromDB($lang) {
        
        try {
            foreach($this->vocabs as $key => $vocab) {
                $val = $this->cacheVocabs->getAllCacheByProperty($key, $lang);
                if(count($val) > 0) {
                    $this->customCache[$lang][$key] = $val;
                }
            }
        } catch (Exception $ex) {
            error_log("Cache DB is missing!");
            $this->customCache = array();
        } catch (\Drupal\Core\Database\ConnectionNotDefinedException $ex) {
            error_log("Cache DB Connection is not definied");
            $this->customCache = array();
        }
    }
    
}
