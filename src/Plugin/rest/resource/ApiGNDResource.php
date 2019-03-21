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
 *     "canonical" = "/api/gnd"
 *   }
 * )
 */
class ApiGNDResource extends ResourceBase
{
    
    /*
     * Usage:
     *  https://domain.com/browser/api/gnd?_format=json
     */
        
    /**
     * Responds to entity GET requests.
     *
     * @param string $class
     * @param string $searchStr
     * @return Response|JsonResponse
     */
    public function get()
    {
        \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');

        

        $response = new Response();
        $OeawCustomSparql = new OeawCustomSparql();
        $OeawStorage = new OeawStorage();
        
        $sparql = $OeawCustomSparql->createGNDPersonsApiSparql();
        $spRes = $OeawStorage->runUserSparql($sparql);
        $host = \Drupal::request()->getSchemeAndHttpHost().'/browser/oeaw_detail/';
        $fileLocation = \Drupal::request()->getSchemeAndHttpHost().'/browser/sites/default/files/beacon.txt';
        $result = array();
        if (count($spRes) > 0) {
            $resTxt = "";
            foreach ($spRes as $key => $val) {
                $resTxt .= $val['dnb']."|".$host.str_replace('https://', '', $val['identifier'])." \n";
            }

            if (!empty($resTxt)) {
                $resTxt = "#FORMAT: BEACON \n".$resTxt;
                file_save_data($resTxt, "public://beacon.txt", FILE_EXISTS_REPLACE);
                $response->setContent(json_encode(array("status" => "File created", "url" => $fileLocation)));
                $response->headers->set('Content-Type', 'application/json');
                return $response;
            } else {
                return new JsonResponse(array("status" => "There is no data"), 404, ['Content-Type'=> 'application/json']);
            }
        } else {
            return new JsonResponse(array("status" => "There is no data"), 404, ['Content-Type'=> 'application/json']);
        }
        return new JsonResponse(array("status" => "There is no data"), 404, ['Content-Type'=> 'application/json']);
    }
    
    /**
     * Generate the filters array for the sparql query
     *
     * @param string $type
     * @return array
     */
    private static function generateFilterData(string $type): array
    {
        \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');
        $filters = array();
        
        if ($type == RC::get('drupalPerson')) {
            $filters[] = RC::get('drupalHasLastName');
            $filters[] = RC::get('drupalHasFirstName');
            $filters[] = RC::get('fedoraIdProp');
            return $filters;
        }
        
        if ($type == RC::get('fedoraOrganisationClass') || $type == RC::get('drupalPlace')
                || $type == RC::get('drupalConcept') || $type == RC::get('drupalCollection')) {
            $filters[] = RC::get('fedoraTitleProp');
            $filters[] = RC::get('drupalHasAlternativeTitle');
            $filters[] = RC::get('fedoraIdProp');
            return $filters;
        }
        
        if ($type == RC::get('drupalPublication')) {
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
