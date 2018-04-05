<?php


namespace Drupal\oeaw\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        
        $sparql = $OeawCustomSparql->createPersonsApiSparql($data);

        if($sparql){
            $spRes = $OeawStorage->runUserSparql($sparql);
            
            if(count($spRes) > 0){
                for ($x = 0; $x < count($spRes); $x++) {

                    $ids = array();
                    $ids = explode(",", $spRes[$x]['identifiers']);
                    //set the flag to false
                    $idContains = false;
                    foreach ($ids as $id){
                        $id = str_replace(RC::get('fedoraIdNamespace'), '', $id);
                        //if one of the identifier is contains the searched value
                        if (strpos(strtolower($id), strtolower($data)) !== false) {
                            $idContains = true;
                        }
                    }
                    
                    $uri = str_replace(strtolower(RC::get('fedoraVocabsNamespace')), '', strtolower($spRes[$x]['uri']) );
                    $urlContains = false;
                    if (strpos($uri, $data) !== false) {
                        $urlContains = true;
                    }
                    
                    $titleContains = false;
                    if (strpos(strtolower($spRes[$x]['title']), strtolower($data) ) !== false) {
                        
                        $titleContains = true;
                    }
                    
                    if($idContains === true || $urlContains === true || $titleContains === true){
                        $result[$x]['uri'] = $spRes[$x]['uri'];
                        $result[$x]['title'] = $spRes[$x]['title'];
                        $result[$x]['identifiers'] = explode(",", $spRes[$x]['identifiers']);
                    }
                    
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
