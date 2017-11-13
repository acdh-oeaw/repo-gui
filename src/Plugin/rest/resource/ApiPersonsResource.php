<?php


namespace Drupal\oeaw\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
// our drupal custom libraries
use Drupal\oeaw\OeawStorage;
use Drupal\oeaw\OeawCustomSparql;

//ARCHE ACDH libraries
use acdhOeaw\util\RepoConfig as RC;
use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\FedoraResource;
use EasyRdf\Graph;
use EasyRdf\Resource;


/**
 * Provides a Persons Checker Resource
 *
 * @RestResource(
 *   id = "api_persons",
 *   label = @Translation("ARCHE Persons Checker"),
 *   uri_paths = {
 *     "canonical" = "/api/persons/{data}"
 *   }
 * )
 */
class ApiPersonsResource extends ResourceBase {
    
    /*
     * Usage:
     * 
     *  https://domain.com/browser/api/persons/MYVALUE?_format=json
     */
    
    /**
    * Responds to entity GET requests.
    * @return \Drupal\rest\ResourceResponse
    */
    public function get(string $data) {
        
        $response = array();
        
        if(empty($data)){
            $response = json_encode('Please provide a string for the search');
            return new ResourceResponse($response);
        }
        $data = strtolower($data);
                
        $sparql = "";
        $spRes = array();
        $result = array();
        
        $OeawCustomSparql = new OeawCustomSparql();
        $OeawStorage = new OeawStorage();
        
        $sparql = $OeawCustomSparql->createPersonsApiSparql($data);

        if($sparql){
            $spRes = $OeawStorage->runUserSparql($sparql);
            
            if(count($spRes) > 0){
                for ($x = 0; $x < count($spRes); $x++) {
                    $result[$x]['uri'] = $spRes[$x]['uri'];
                    $result[$x]['title'] = $spRes[$x]['title'];
                    $result[$x]['identifiers'] = $spRes[$x]['identifiers'];
                }
                $response = $result;
                return new ResourceResponse($response);
            }else {
                $response = json_encode('There is no value!');
                return new ResourceResponse($response);
            }
        }else {
            $response = json_encode('There is no value!');
            return new ResourceResponse($response);
        }
    }

}
