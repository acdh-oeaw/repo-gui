<?php


namespace Drupal\oeaw\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
// our drupal custom libraries
use Drupal\oeaw\Model\OeawStorage;
use Drupal\oeaw\Model\OeawCustomSparql;

//ARCHE ACDH libraries
use acdhOeaw\util\RepoConfig as RC;


/**
 * Search for data by the Class
 *
 * @RestResource(
 *   id = "api_getdata",
 *   label = @Translation("ARCHE Get Data"),
 *   uri_paths = {
 *     "canonical" = "/api/getData/{class}/{querystring}"
 *   }
 * )
 */
class ApiGetDataResource extends ResourceBase {
    
    /*
     * Usage:
     * 
     *  https://domain.com/browser/api/getData/{class}/{querystring}?_format=json
     */
    
    /**
    * Responds to entity GET requests.
    * @return \Drupal\rest\ResourceResponse
    */
    public function get(string $class, string $searchStr) {
        
        $response = new Response();
        
        if(empty($class) || empty($searchStr)){
            return new JsonResponse(array("Please provide a link"), 404, ['Content-Type'=> 'application/json']);
        }
        
        $class = "https://vocabs.acdh.oeaw.ac.at/schema#".$class;
                
        $sparql = "";
        $spRes = array();
        $result = array();
        
        $OeawCustomSparql = new OeawCustomSparql();
        $OeawStorage = new OeawStorage();
        
        $sparql = $OeawCustomSparql->createBasicApiSparql($searchStr, $class);
        
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
                        if (strpos(strtolower($id), strtolower($searchStr)) !== false) {
                            $idContains = true;
                        }
                    }
                    
                    $uri = str_replace(strtolower(RC::get('fedoraVocabsNamespace')), '', strtolower($spRes[$x]['uri']) );
                    $urlContains = false;
                    if (strpos($uri, $searchStr) !== false) {
                        $urlContains = true;
                    }
                    
                    $titleContains = false;
                    if (strpos(strtolower($spRes[$x]['title']), strtolower($searchStr) ) !== false) {
                        $titleContains = true;
                    }
                    
                    $altTitleContains = false;
                    if (strpos(strtolower($spRes[$x]['altTitle']), strtolower($searchStr) ) !== false) {
                        $altTitleContains = true;
                    }
                    
                    if($idContains === true || $urlContains === true || $titleContains === true || $altTitleContains === true){
                        $result[$x]['uri'] = $spRes[$x]['uri'];
                        $result[$x]['title'] = $spRes[$x]['title'];
                        $result[$x]['altTitle'] = $spRes[$x]['altTitle'];
                        $result[$x]['identifiers'] = explode(",", $spRes[$x]['identifiers']);
                    }
                }
                
                if(count($result) > 0){
                    $response->setContent(json_encode($result));
                    $response->headers->set('Content-Type', 'application/json');
                    return $response;
                }else{
                    return new JsonResponse(array("There is no resource"), 404, ['Content-Type'=> 'application/json']);
                }
            }else {
                return new JsonResponse(array("There is no resource"), 404, ['Content-Type'=> 'application/json']);
            }
        }else {
            return new JsonResponse(array("There is no resource"), 404, ['Content-Type'=> 'application/json']);
        }
    }

}
