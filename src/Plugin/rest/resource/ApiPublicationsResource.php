<?php

namespace Drupal\oeaw\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;

// our drupal custom libraries
use Drupal\oeaw\Model\OeawStorage;
use Drupal\oeaw\Model\OeawCustomSparql;
use Drupal\oeaw\Helper\Helper;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

//ARCHE ACDH libraries
use acdhOeaw\util\RepoConfig as RC;

/**
 * Provides an Publications Checker Resource
 *
 * @RestResource(
 *   id = "api_publications",
 *   label = @Translation("ARCHE Publications Checker"),
 *   uri_paths = {
 *     "canonical" = "/api/publications/{data}"
 *   }
 * )
 */
class ApiPublicationsResource extends ResourceBase
{
    
    /**
     * Responds to entity GET requests.
     *
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
        
        $OeawCustomSparql = new OeawCustomSparql();
        $OeawStorage = new OeawStorage();
        
        $sparql = $OeawCustomSparql->createPublicationsApiSparql($data);

        if ($sparql) {
            $spRes = $OeawStorage->runUserSparql($sparql, true);
            
            if (count($spRes) > 0) {
                $spRes = Helper::formatApiSparqlResult($spRes);
                
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
                    
                    $altTitleContains = false;
                    if (strpos(strtolower($spRes[$x]['altTitle']), strtolower($data)) !== false) {
                        $altTitleContains = true;
                    } elseif (is_array($spRes[$x]['altTitle'])) {
                        foreach ($spRes[$x]['altTitle'] as $d) {
                            if (strpos(strtolower($d), strtolower($data)) !== false) {
                                $altTitleContains = true;
                            }
                        }
                    }
                    
                    if ($idContains === true || $urlContains === true || $titleContains === true || $altTitleContains === true) {
                        $result[$x]['uri'] = $spRes[$x]['uri'];
                        $result[$x]['title'] = $spRes[$x]['title'];
                        $result[$x]['altTitle'] = $spRes[$x]['altTitle'];
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
