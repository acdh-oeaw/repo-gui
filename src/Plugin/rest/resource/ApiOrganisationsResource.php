<?php


namespace Drupal\oeaw\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
// our drupal custom libraries
use Drupal\oeaw\OeawStorage;
use Drupal\oeaw\OeawFunctions;

//ARCHE ACDH libraries
use acdhOeaw\util\RepoConfig as RC;
use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\FedoraResource;
use EasyRdf\Graph;
use EasyRdf\Resource;


/**
 * Provides an Organisations Checker Resource
 *
 * @RestResource(
 *   id = "api_organisations",
 *   label = @Translation("ARCHE Organisations Checker"),
 *   uri_paths = {
 *     "canonical" = "/api/organisations/{data}"
 *   }
 * )
 */
class ApiOrganisationsResource extends ResourceBase {
    
    
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
        
        $OeawFunctions = new OeawFunctions();
        $OeawStorage = new OeawStorage();
        
        $sparql = $OeawFunctions->createBasicApiSparql($data, RC::get('fedoraOrganisationClass'));

        if($sparql){
            $spRes = $OeawStorage->runUserSparql($sparql);
            
            if(count($spRes) > 0){
                for ($x = 0; $x < count($spRes); $x++) {
                    $result[$x]['uri'] = $spRes[$x]['uri'];
                    $result[$x]['title'] = $spRes[$x]['title'];
                    $result[$x]['altTitle'] = $spRes[$x]['altTitle'];
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
