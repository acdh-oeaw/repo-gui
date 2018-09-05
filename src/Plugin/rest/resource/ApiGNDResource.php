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
 *   id = "api_gnddata",
 *   label = @Translation("ARCHE GND Data"),
 *   uri_paths = {
 *     "canonical" = "/api/gnd/{order}/{limit}"
 *   }
 * )
 */
class ApiGNDResource extends ResourceBase {
    
    /*
     * Usage:
     *  https://domain.com/browser/api/gnd/{order}/{limit}?_format=json
     */
        
    /**
     * Responds to entity GET requests.
     * 
     * @param string $class
     * @param string $searchStr
     * @return Response|JsonResponse
     */
    public function get(string $order = "asc", string $limit = "10") {
        
        \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');

        if(empty($order) || empty($limit)){
            return new JsonResponse(array("Order or limit is missing"), 404, ['Content-Type'=> 'application/json']);
        }
        
        switch ($order) {
            case 'asc':
                $order = 'asc';
                break;
            case 'desc':
                $order = 'desc';
                break;
            default:
                $order = 'asc';
                break;
        }
        
        $limit = (int)$limit;
        
        if($limit < 1) { $limit = 10; } 
        elseif ($limit === 0) { $limit = 10; }

        $response = new Response();
        $OeawCustomSparql = new OeawCustomSparql();
        $OeawStorage = new OeawStorage();
        
        $sparql = $OeawCustomSparql->createGNDPersonsApiSparql($order, $limit);
        $spRes = $OeawStorage->runUserSparql($sparql);
        $host = \Drupal::request()->getSchemeAndHttpHost().'/browser/oeaw_detail/';
        $result = array();
        if(count($spRes) > 0){
                
            foreach($spRes as $key => $val) {
                $result[$key]['name'] = $val['lname']." ".$val['fname'];
                $result[$key]['lname'] = $val['lname'];
                $result[$key]['fname'] = $val['fname'];
                $result[$key]['type'] = 'Person';
                $result[$key]['dnb'] = $val['dnb'];
                $result[$key]['arche_url'] = $host.str_replace('https://', '', $val['identifier']);
            }

            if(count($result) > 0){
                $response->setContent(json_encode($result));
                $response->headers->set('Content-Type', 'application/json');
                return $response;
            }else{
                return new JsonResponse(array("There is no data"), 404, ['Content-Type'=> 'application/json']);
            }
        }else {
            return new JsonResponse(array("There is no data"), 404, ['Content-Type'=> 'application/json']);
        }
        return new JsonResponse(array("There is no data"), 404, ['Content-Type'=> 'application/json']);
    }
    
    /**
     * Generate the filters array for the sparql query
     * 
     * @param string $type
     * @return array
     */
    private static function generateFilterData(string $type): array{
        
        \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');
        $filters = array();
        
        if($type == RC::get('drupalPerson')){
            $filters[] = RC::get('drupalHasLastName'); 
            $filters[] = RC::get('drupalHasFirstName');
            $filters[] = RC::get('fedoraIdProp');
            return $filters;
        }
        
        if($type == RC::get('fedoraOrganisationClass') || $type == RC::get('drupalPlace') 
                || $type == RC::get('drupalConcept') || $type == RC::get('drupalCollection')){
            $filters[] = RC::get('fedoraTitleProp'); 
            $filters[] = RC::get('drupalHasAlternativeTitle');
            $filters[] = RC::get('fedoraIdProp');
            return $filters;
        }
        
        if($type == RC::get('drupalPublication')){
            $filters[] = RC::get('drupalHasLastName'); 
            $filters[] = RC::get('drupalHasFirstName');
            $filters[] = RC::get('fedoraIdProp');
            $filters[] = RC::get('drupalHasAuthor');
            $filters[] = RC::get('drupalHasEditor');
            return $filters;
        }
        return $filters;
    }

}
