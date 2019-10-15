<?php


namespace Drupal\oeaw\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

// our drupal custom libraries

use Drupal\oeaw\Model\ApiModel;
use Drupal\oeaw\Helper\ApiHelper;
use Drupal\oeaw\Helper\HelperFunctions as HF;

//ARCHE ACDH libraries
use acdhOeaw\util\RepoConfig as RC;

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
class ApiPersonsResource extends ResourceBase
{
    
    /*
     * Usage:
     *
     *  https://domain.com/browser/api/persons/MYVALUE?_format=json
     */
    
    
    /**
     * Responds to entity GET requests.
     * @param string $data
     * @return Response|JsonResponse
     */
    public function get(string $data)
    {
        $response = new Response();
        
        if (empty($data)) {
            return new JsonResponse(array("Please provide a link"), 404, ['Content-Type'=> 'application/json']);
        }
        $data = strtolower($data);
                
        $sparql = "";
        $spRes = array();
        $result = array();
        
        $model = new ApiModel();
        $sparql = $model->createPersonsApiSparql($data);
        $helper = new ApiHelper();

        if ($sparql) {
            $spRes = $helper->runUserSparql($sparql, true);
            
            if (count($spRes) > 0) {
                $spRes = HF::formatApiSparqlResult($spRes);
                
                for ($x = 0; $x < count($spRes); $x++) {
                    $ids = array();
                    $ids = explode(",", $spRes[$x]['identifiers']);
                    //set the flag to false
                    $idContains = false;
                    foreach ($ids as $id) {
                        $id = str_replace(RC::get('fedoraIdNamespace'), '', $id);
                        //if one of the identifier is contains the searched value
                        if (strpos(strtolower($id), strtolower($data)) !== false) {
                            $idContains = true;
                        }
                    }
                    $uri = str_replace(strtolower(RC::get('fedoraVocabsNamespace')), '', strtolower($spRes[$x]['uri']));
                    $urlContains = false;
                    
                    if (strpos($uri, $data) !== false) {
                        $urlContains = true;
                    }
                    
                    $titleContains = false;
                    if (strpos(strtolower($spRes[$x]['title']), strtolower($data)) !== false) {
                        $titleContains = true;
                    } elseif (is_array($spRes[$x]['title'])) {
                        foreach ($spRes[$x]['title'] as $d) {
                            if (strpos(strtolower($d), strtolower($data)) !== false) {
                                $titleContains = true;
                            }
                        }
                    }
                    if ($idContains === true || $urlContains === true || $titleContains === true) {
                        $result[$x]['uri'] = $spRes[$x]['uri'];
                        $result[$x]['title'] = $spRes[$x]['title'];
                        $result[$x]['identifiers'] = explode(",", $spRes[$x]['identifiers']);
                    }
                }
                
                if (count($result) > 0) {
                    $response->setContent(json_encode($result));
                    $response->headers->set('Content-Type', 'application/json');
                    return $response;
                } else {
                    return new JsonResponse(array("There is no resource"), 404, ['Content-Type'=> 'application/json']);
                }
            } else {
                return new JsonResponse(array("There is no resource"), 404, ['Content-Type'=> 'application/json']);
            }
        } else {
            return new JsonResponse(array("There is no resource"), 404, ['Content-Type'=> 'application/json']);
        }
    }
}
