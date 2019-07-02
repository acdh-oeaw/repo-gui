<?php

namespace Drupal\oeaw\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

// our drupal custom libraries
use Drupal\oeaw\Model\OeawStorage;
use Drupal\oeaw\OeawFunctions;

//ARCHE ACDH libraries
use acdhOeaw\util\RepoConfig as RC;
use acdhOeaw\fedora\Fedora;
use EasyRdf\Graph;


/**
 * Provides a turtle file from the actual resource
 *
 * @RestResource(
 *   id = "api_turtle_diss",
 *   label = @Translation("ARCHE Resource turtle"),
 *   uri_paths = {
 *     "canonical" = "/api/turtle_diss/{identifier}"
 *   }
 * )
 */
class ApiTurtleDissServResource extends ResourceBase {
    
    /**
     * Responds to entity GET requests.
     * 
     * @param string $data
     * @return Response|JsonResponse
     */
    public function get(string $identifier) {
      
        
        
        if(empty($data)){
            return new JsonResponse(array("Please provide a link"), 404, ['Content-Type'=> 'application/json']);
        }        
        $data = strtolower($data);
                
        $OeawStorage = new OeawStorage();                
        
        $fedoraUrl = "https://fedora.hephaistos.arz.oeaw.ac.at/rest/b5/14/9f/90/b5149f90-869d-43af-a545-b33683607f10";
        
        $result = array();
        $client = new \GuzzleHttp\Client();
        try{
            $request = $client->request('GET',  $fedoraUrl.'/fcr:metadata',  ['Accept' => ['application/n-triples']]);
            if($request->getStatusCode() == 200) {
                $body = "";
                $body = $request->getBody()->getContents();
                if(!empty($body)) {
                    $graph = new \EasyRdf_Graph();
                    $graph->parse($body);
                    $graph->serialise('turtle');
                }
                            
            }
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return array();
        }  catch (\Exception $ex) {
            return array();
        }
        
    
        $response->setContent(json_encode($result));
        $response->headers->set('Content-Type', 'application/text');
        return $response;
        
    }

}
