<?php


namespace Drupal\oeaw\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
// our drupal custom libraries
use Drupal\oeaw\OeawStorage;
use Drupal\oeaw\OeawCustomSparql;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

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
        
        $response = new Response();
        
        if(empty($data)){
            return new JsonResponse(array("Please provide a link"), 404, ['Content-Type'=> 'application/json']);
        }
        
        $data = strtolower($data);
                
        $sparql = "";
        $spRes = array();
        $result = array();
        
        $OeawCustomSparql = new OeawCustomSparql();
        $OeawStorage = new OeawStorage();
        
        $sparql = $OeawCustomSparql->createBasicApiSparql($data, RC::get('fedoraOrganisationClass'));
        
        if($sparql){
            $spRes = $OeawStorage->runUserSparql($sparql);
            
            if(count($spRes) > 0){
                for ($x = 0; $x < count($spRes); $x++) {
                    $result[$x]['uri'] = $spRes[$x]['uri'];
                    $result[$x]['title'] = $spRes[$x]['title'];
                    $result[$x]['altTitle'] = $spRes[$x]['altTitle'];
                    $result[$x]['identifiers'] = explode(",", $spRes[$x]['identifiers']);
                }
                $response->setContent(json_encode($result));
                $response->headers->set('Content-Type', 'application/json');
                return $response;
            }else {
                return new JsonResponse(array("There is no resource"), 404, ['Content-Type'=> 'application/json']);
            }
        }else {
            return new JsonResponse(array("There is no resource"), 404, ['Content-Type'=> 'application/json']);
        }
    }

}
