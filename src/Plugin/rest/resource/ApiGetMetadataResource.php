<?php


namespace Drupal\oeaw\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

// our drupal custom libraries
use Drupal\oeaw\Model\OeawStorage;

//ARCHE ACDH libraries
use acdhOeaw\util\RepoConfig as RC;


/**
 * Provides Metadata by Class 
 *
 * @RestResource(
 *   id = "api_getMetadata",
 *   label = @Translation("ARCHE Metadata provider"),
 *   uri_paths = {
 *     "canonical" = "/api/getMetadata/{type}/{lang}"
 *   }
 * )
 */
class ApiGetMetadataResource extends ResourceBase {
    
    /*
     * Usage:
     * 
     *  https://domain.com/browser/api/getMetadata/MYVALUE/Language?_format=json
     * 
     * F.e.: https://vocabs.acdh.oeaw.ac.at/schema#AgentOrPlace -> AgentOrPlace
     * 
     * https://domain.com/browser/api/getMetadata/AgentOrPlace/en?_format=json
     * 
     */
    
    public function __construct(){
        \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');
    }
    
    /**
    * Responds to entity GET requests.
    * @return \Drupal\rest\ResourceResponse
    */
    public function get(string $type, string $lang) {
        
        if(empty($type)){
            return new JsonResponse(array("Please provide a type! For exmaple: person, collection, etc..."), 404, ['Content-Type'=> 'application/json']);
        }
        
        if(empty($lang)){
            return new JsonResponse(array("Please provide a language! For exmaple: en, de etc..."), 404, ['Content-Type'=> 'application/json']);
        }
        
        $classMeta = array();
        $oeawStorage = new OeawStorage();
        
        try {
            $classMeta = $oeawStorage->getClassMetaForApi(ucfirst($type), $lang);
        } catch (Exception $ex) {
            return new JsonResponse(array($ex->getMessage()), 404, ['Content-Type'=> 'application/json']);
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            return new JsonResponse(array($ex->getMessage()), 404, ['Content-Type'=> 'application/json']);
        }
       
        if(count($classMeta) > 0){
            
            $result = array();
            $result['$schema'] = "http://json-schema.org/draft-07/schema#";
            $result['id'] = RC::get('fedoraVocabsNamespace').$type;
            $result['type'] = "object";
            $result['title'] = $type;
           
            $res = array();
            $res = $this->transformProperties($classMeta);
            if(count($res) > 0){
                $result['properties'] = $res;
            }
           
            $response = new Response();
            $response->setContent(json_encode($result, true));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }
        return new JsonResponse(array("There is no data!"), 404, ['Content-Type'=> 'application/json']);
    }
    
    /**
     * 
     * Transform the API properties result for the JSON response
     * 
     * @param array $properties
     * @return array
     */
    private function transformProperties(array $properties): array{
        $result = array();
        $required = array();
                
        if(count($properties) > 0){
            foreach($properties as $prop){
                if($prop['propID']){
                    $propID = "";
                    $propIDArr = explode(RC::get('fedoraVocabsNamespace'), $prop['propID']);
                    $propID = $propIDArr[1];
                    $result[$propID]['type'] = "string";
                    
                    if(isset($prop['comment']) && $prop['comment']){
                        $result[$propID]['attrs']['placeholder'] = $prop['comment']; 
                        $result[$propID]['description'] = $prop['comment']; 
                    }
                    
                    if(isset($prop['propTitle']) && $prop['propTitle']){
                        $result[$propID]['title'] = $prop['propTitle']; 
                    }
                    
                    if(isset($prop['minCardinality'])){
                        $result[$propID]['minItems'] = (int)$prop['minCardinality']; 
                        if( $prop['minCardinality'] > 1){
                            $result[$propID]['type'] = "array";
                        }
                        if( $prop['minCardinality'] == 1){
                            $result[$propID]['uniqueItems'] = true;
                        }
                    }else{
                        $result[$propID]['minItems'] = 0; 
                    }
                    
                    if(isset($prop['maxCardinality'])){
                        $result[$propID]['maxItems'] = (int)$prop['maxCardinality']; 
                        if( $prop['maxCardinality'] > 1){
                            $result[$propID]['type'] = "array";
                        }
                    }
                    if( isset($prop['minCardinality']) && $prop['minCardinality'] >= 1 ){
                        $required[] = $propID;
                    }
                                        
                    if( isset($prop['range']) && $prop['range'] ){
                        if( $result[$propID]['type'] == "array"){
                            $result[$propID]['items']['type'] = "string"; 
                            $result[$propID]['items']['range'] = $prop['range']; 
                            continue;
                        }
                        if( $result[$propID]['type'] == "string"){
                            $result[$propID]['range'] = $prop['range']; 
                        }
                    }
                    if( isset($prop['vocabs']) && $prop['vocabs'] ){
                        $result[$propID]['vocabs'] = $prop['vocabs']; 
                    }
                    
                    if( isset($prop['order']) && $prop['order'] ){
                        $result[$propID]['order'] = (int)$prop['order']; 
                    }else {
                        $result[$propID]['order'] = 0; 
                    }
                    
                    if( isset($prop['recommendedClass']) && $prop['recommendedClass'] ){
                        $result[$propID]['recommendedClass'] = $prop['recommendedClass']; 
                    }
                }
            }
        }
        
        if(count($required) > 0){
            $result['required'] = $required;
        }
        
        return $result;
    }

}
