<?php


namespace Drupal\oeaw\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
// our drupal custom libraries
use Drupal\oeaw\OeawStorage;
use Drupal\oeaw\OeawFunctions;
use Drupal\oeaw\OeawCustomSparql;

//ARCHE ACDH libraries
use acdhOeaw\util\RepoConfig as RC;
use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\FedoraResource;
use EasyRdf\Graph;
use EasyRdf\Resource;


/**
 * Provides Metadata by Class 
 *
 * @RestResource(
 *   id = "api_getMetadata",
 *   label = @Translation("ARCHE Metadata provider"),
 *   uri_paths = {
 *     "canonical" = "/api/getMetadata/{type}"
 *   }
 * )
 */
class ApiGetMetadataResource extends ResourceBase {
    
    /*
     * Usage:
     * 
     *  https://domain.com/browser/api/getMetadata/MYVALUE?_format=json
     */
    
    /**
    * Responds to entity GET requests.
    * @return \Drupal\rest\ResourceResponse
    */
    public function get(string $type) {
        
        if(empty($type)){
            return new JsonResponse(array("Please provide a type! For exmaple: person, collection, etc..."), 404, ['Content-Type'=> 'application/json']);
        }
        
        $classes = array();
        $classMeta = array();
        
        $oeawStorage = new OeawStorage();
        $oeawFunctions = new OeawFunctions();
        
        //get the actual classes from the DB
        $classes = $oeawStorage->getClass();
        
        if(count($classes) == 0){
            return new JsonResponse(array("There are no classes"), 404, ['Content-Type'=> 'application/json']);
        }
        
        $typeAvailable = false;
        //check that we have a class like the user requires
        $typeAvailable = $oeawFunctions->checkMultiDimArrayForValue(strtolower($type), $classes);
        
        if($typeAvailable == true){
            
            foreach($classes as $class){
                if( (isset($class['title']) && $class['title'] == strtolower($type)) 
                        && 
                    (isset($class['uri']) && !empty($class['uri']) ) 
                ){
                    $classMeta = $oeawStorage->getClassMeta($class['uri']);
                }
            }
        }else {
            return new JsonResponse(array("There is no type like this: ".$type), 404, ['Content-Type'=> 'application/json']);
        }
        
        if(count($classMeta) > 0){
            $response = new Response();
            $response->setContent(json_encode($classMeta));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
            
        }
        
        return new JsonResponse(array("There is no data!"), 404, ['Content-Type'=> 'application/json']);
    }

}
