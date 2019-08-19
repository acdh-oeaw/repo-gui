<?php


namespace Drupal\oeaw\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
// our drupal custom libraries
use Drupal\oeaw\Model\OeawStorage;
use Drupal\oeaw\Model\OeawCustomSparql;

//ARCHE ACDH libraries
use acdhOeaw\util\RepoConfig as RC;
use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\FedoraResource;
use EasyRdf\Graph;
use EasyRdf\Resource;

/**
 * Provides Metadata Table to GUI
 *
 * @RestResource(
 *   id = "api_getMetadataGui",
 *   label = @Translation("ARCHE Metadata to GUI provider"),
 *   uri_paths = {
 *     "canonical" = "/api/getMetadataGui/{lang}"
 *   }
 * )
 */
class ApiGetMetadataGuiResource extends ResourceBase
{
    
    private $data;
    /*
     * Usage:
     *
     *  https://domain.com/browser/api/getMetadataGui/Language?_format=json
     *
     *
     * https://domain.com/browser/api/getMetadataGui/en?_format=json
     *
     */
    
    /**
     * Responds to entity GET requests.
     * @param string $lang
     * @return Response|JsonResponse
     */
    public function get(string $lang)
    {
        \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');
        
        if (empty($lang)) {
            return new JsonResponse(array("Please provide a language! For exmaple: en, de etc..."), 404, ['Content-Type'=> 'application/json']);
        }
        $this->data = array();
        $projectData = array();
        $collectionData = array();
        $resourceData = array();
        $oeawStorage = new OeawStorage();
        
        try {
            $projectData = $oeawStorage->getMetadataForGuiTable("Project", $lang);
            $collectionData = $oeawStorage->getMetadataForGuiTable("Collection", $lang);
            $resourceData = $oeawStorage->getMetadataForGuiTable("Resource", $lang);
        } catch (Exception $ex) {
            return new JsonResponse(array($ex->getMessage()), 404, ['Content-Type'=> 'application/json']);
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return new JsonResponse(array($ex->getMessage()), 404, ['Content-Type'=> 'application/json']);
        }
       
        $result = array();
        $result['$schema'] = "http://json-schema.org/draft-07/schema#";
        
        if ( count($projectData) > 0 || count($collectionData) > 0 || count($resourceData) > 0 ) {
            $this->summarizeData($projectData, "project");
            $this->summarizeData($collectionData, "collection");
            $this->summarizeData($resourceData, "resource");
            
            if (count($this->data) > 0) {
                $result['properties'] = $this->data;
            }
            $response = new Response();
            $response->setContent(json_encode($result, true));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }
        return new JsonResponse(array("There is no data!"), 404, ['Content-Type'=> 'application/json']);
    }
    
    
    /**
     * Merge the data arrays
     * 
     * @param array $resData
     * @param string $class
     */
    private function summarizeData(array $resData, string $class) {
        if(count($resData) > 0) {
            foreach($resData as $data) {
                $this->data[$data['property']]['basic_info'] = array("property" => $data['property'],
                    "machine_name" => $data['machine_name'],
                    "ordering" => $data['ordering']);
                $this->data[$data['property']][$class] = (!empty($this->checkCardinality($data))) ? $this->checkCardinality($data) : "-";
            }
        }
    }
    
    /**
     * Check the property cardinalities
     * 
     * @param array $data
     * @return string
     */
    private function checkCardinality(array $data): string {
        $cardinalities = "";
        
        //mandatory
        ( isset($data['cardinality']) && !empty($data['cardinality']) ) ? $cardinalities .= "m" : "";
        
        //optional
        ( (isset($data['minCardinality']) && empty($data['minCardinality'])) 
            && (isset($data['maxCardinality']) && empty($data['maxCardinality']) ) 
            && (isset($data['cardinality']) && empty($data['cardinality'])) ) ? $cardinalities .= "o" : "";
        
        //recommended
        ( (isset($data['minCardinality']) && !empty($data['minCardinality']) ) ) ? $cardinalities .= "r" : "";
        ( (isset($data['minCardinality']) && !empty($data['minCardinality']) && (int)$data['minCardinality'] > 1 ) ) ? $cardinalities .= "*" : "";
        ( (isset($data['maxCardinality']) && !empty($data['maxCardinality']) )) ? $cardinalities .= "r" : "";
        ( (isset($data['maxCardinality']) && !empty($data['maxCardinality']) && (int)$data['maxCardinality'] > 1 )) ? $cardinalities .= "*" : "";
        
        return $cardinalities;
    }
   
}
