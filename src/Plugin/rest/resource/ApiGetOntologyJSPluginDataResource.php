<?php

namespace Drupal\oeaw\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
// our drupal custom libraries
use Drupal\oeaw\Model\ApiModel;


//ARCHE ACDH libraries
use acdhOeaw\util\RepoConfig as RC;

/**
 * Provides resource and collection count for the js ckeditor plugin
 *
 * @RestResource(
 *   id = "api_getOntologyJSPluginData",
 *   label = @Translation("ARCHE ApiGetOntologyJSPluginData to GUI CKeditor"),
 *   uri_paths = {
 *     "canonical" = "/api/getOntologyJSPluginData/{lang}"
 *   }
 * )
 */
class ApiGetOntologyJSPluginDataResource extends ResourceBase
{
    /*
     * Usage:
     * https://domain.com/browser/api/getOntologyJSPluginData/Language?_format=json
     * https://domain.com/browser/api/getOntologyJSPluginData/en?_format=json
     *
     */
    
    /**
     * Responds to entity GET requests.
     * @param string $lang
     * @return Response|JsonResponse
     */
    public function get(string $lang)
    {
        if (empty($lang)) {
            return new JsonResponse(array("Please provide a language! For exmaple: en, de etc..."), 404, ['Content-Type'=> 'application/json']);
        }
        
        $data = array();
        $model = new ApiModel();
        
        try {
            $data = $model->getDataForJSPlugin();
        } catch (Exception $ex) {
            return new JsonResponse(array($ex->getMessage()), 404, ['Content-Type'=> 'application/json']);
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return new JsonResponse(array($ex->getMessage()), 404, ['Content-Type'=> 'application/json']);
        }
       
        $result = array();
        $result['$schema'] = "http://json-schema.org/draft-07/schema#";
        
        if (count($data) > 0) {
            $files = "";
            $collections = "";
            if (isset($data['collections']) && !empty($data['collections'])) {
                $collections = $data['collections']." ".t("collections");
            }
            if (isset($data['resources']) && !empty($data['resources'])) {
                $files = $data['resources']." ".t("files");
            }
            
            if (empty($files)) {
                $files = "0";
            }
            if (empty($collections)) {
                $collections = "0";
            }
            
            $result['text'] = $collections. " ".t("with")." ".$files;
            $response = new Response();
            $response->setContent(json_encode($result, true));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }
        return new JsonResponse(array("There is no data!"), 404, ['Content-Type'=> 'application/json']);
    }
}
