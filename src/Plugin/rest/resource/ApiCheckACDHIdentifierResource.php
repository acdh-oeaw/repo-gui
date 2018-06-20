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



/**
 * Check the acdh identifier in the database
 *
 * @RestResource(
 *   id = "api_checkIdentifier",
 *   label = @Translation("ARCHE Identifier checker"),
 *   uri_paths = {
 *     "canonical" = "/api/checkIdentifier/{identifier}"
 *   }
 * )
 */
class ApiCheckACDHIdentifierResource extends ResourceBase {
    
    /*
     * Usage:
     * 
     *  https://domain.com/browser/api/checkIdentifier/MYVALUE?_format=json
     * 
     * F.e.:identifier: "https://id.acdh.oeaw.ac.at/pub-calvetrobin1997" -> remove http/https and urlencode
     * the rest of it: id.acdh.oeaw.ac.at%20pub-calvetrobin1997
     * 
     * https://fedora.localhost/browser/api/checkIdentifier/id.acdh.oeaw.ac.at%20pub-calvetrobin1997?_format=json
     * 
     */
    
    /**
    * Responds to entity GET requests.
    * @return \Drupal\rest\ResourceResponse
    */
    public function get(string $identifier) {
        
        if(empty($identifier)){
            return new JsonResponse(array("Please provide an identifier!"), 404, ['Content-Type'=> 'application/json']);
        }
        
        $oeawFunctions = new OeawFunctions();
        $oeawStorage = new OeawStorage();
        
        //transform the url from the browser to readable uri
        $identifier = $oeawFunctions->detailViewUrlDecodeEncode($identifier, 0);
        
//if the browser url contains handle url then we need to get the acdh:hasIdentifier
        if (strpos($identifier, 'hdl.handle.net') !== false) {
            return new JsonResponse(array("This is not a valid ACDH identifier1"), 404, ['Content-Type'=> 'application/json']);
        }
        if (strpos($identifier, RC::get('fedoraIdNamespace')) === false) {
            return new JsonResponse(array("This is not a valid ACDH identifier2"), 404, ['Content-Type'=> 'application/json']);
        }
        
        try {
            $classMeta = $oeawStorage->getDataByProp(RC::idProp(), $identifier);
        } catch (Exception $ex) {
            return new JsonResponse(array($ex->getMessage()), 404, ['Content-Type'=> 'application/json']);
        } catch (\GuzzleHttp\Exception\ClientException $ex){
            return new JsonResponse(array($ex->getMessage()), 404, ['Content-Type'=> 'application/json']);
        }
        if(count($classMeta) > 0){
            return new JsonResponse(array("The Identifier already used!"), 404, ['Content-Type'=> 'application/json']);
        }        
        return new JsonResponse(array("The identifier is free"), 200, ['Content-Type'=> 'application/json']);
    }
}
