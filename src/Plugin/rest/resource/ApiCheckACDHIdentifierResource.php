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
class ApiCheckACDHIdentifierResource extends ResourceBase
{
    
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
     *
     * @param string $identifier
     * @return JsonResponse
     */
    public function get(string $identifier)
    {
        if (empty($identifier)) {
            return new JsonResponse(array("Please provide an identifier!"), 404, ['Content-Type'=> 'application/json']);
        }
        \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');
        
        $oeawFunctions = new OeawFunctions();
        $oeawStorage = new OeawStorage();
        
        //transform the url from the browser to readable uri
        $identifier = $oeawFunctions->detailViewUrlDecodeEncode($identifier, 0);
        
        //if the browser url contains handle url then we need to get the acdh:hasIdentifier
        if (strpos($identifier, 'hdl.handle.net') !== false) {
            return new JsonResponse(array("This is not a valid ACDH identifier"), 404, ['Content-Type'=> 'application/json']);
        }
        if (strpos($identifier, RC::get('fedoraIdNamespace')) === false) {
            return new JsonResponse(array("This is not a valid ACDH identifier"), 404, ['Content-Type'=> 'application/json']);
        }
        
        try {
            $classMeta = $oeawStorage->getDataByProp(RC::idProp(), $identifier);
        } catch (Exception $ex) {
            return new JsonResponse(array($ex->getMessage()), 404, ['Content-Type'=> 'application/json']);
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return new JsonResponse(array($ex->getMessage()), 404, ['Content-Type'=> 'application/json']);
        }
        if (count($classMeta) > 0) {
            $result = array();
            if (isset($classMeta[0]['title'])) {
                $result['title'] = $classMeta[0]['title'];
            }
            if (isset($classMeta[0]['rdfTypes'])) {
                $result["rdfTypes"] = explode(",", $classMeta[0]['rdfTypes']);
            }
            if (isset($classMeta[0]['creationdate']) && !empty($classMeta[0]['creationdate'])) {
                $result["creationDate"] = date("Y-m-d", strtotime($classMeta[0]['creationdate']));
            }
            if (isset($classMeta[0]['fdCreated']) && !empty($classMeta[0]['fdCreated'])) {
                $result["fedoraCreateDate"] = date("Y-m-d", strtotime($classMeta[0]['fdCreated']));
            }
            return new JsonResponse($result, 200, ['Content-Type'=> 'application/json']);
        }
        return new JsonResponse(array("The identifier is free"), 200, ['Content-Type'=> 'application/json']);
    }
}
