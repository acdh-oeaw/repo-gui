<?php

namespace Drupal\oeaw\Helper;

use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\metadataQuery\SimpleQuery;
use Drupal\oeaw\Helper\ModelFunctions;

/**
 * Description of ApiHelper
 *
 * @author nczirjak
 */
class ApiHelper
{
    private $fedora;
    private $modelFunctions;
    
    public function __construct()
    {
        $this->fedora = new Fedora();
        $this->modelFunctions = new ModelFunctions();
    }
    
    /**
     * Run users sparql from the resource views
     * @param string $string
     * @param bool $multilang
     * @return array
     */
    public function runUserSparql(string $string, bool $multilang = false): array
    {
        $result = array();
        
        try {
            $q = new SimpleQuery($string);
            $query = $q->getQuery();
            $res = $this->fedora->runSparql($query);
            $fields = $res->getFields();
            return $result = $this->modelFunctions->createSparqlResult($res, $fields, $multilang);
        } catch (\Exception $ex) {
            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return $result;
        }
    }
}
