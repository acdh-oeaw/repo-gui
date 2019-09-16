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
        
        if (count($projectData) > 0 || count($collectionData) > 0 || count($resourceData) > 0) {
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
    private function summarizeData(array $resData, string $class)
    {
        if (count($resData) > 0) {
            foreach ($resData as $data) {
                $this->data[$data['property']]['basic_info'] = array("property" => $data['property'],
                    "machine_name" => $data['machine_name'],
                    "ordering" => $data['ordering']);
                
                if (!isset($this->data[$data['property']]['Project'])) {
                    $this->data[$data['property']]['Project'] = "-" ;
                }
                if (!isset($this->data[$data['property']]['Collection'])) {
                    $this->data[$data['property']]['Collection'] = "-" ;
                }
                if (!isset($this->data[$data['property']]['Resource'])) {
                    $this->data[$data['property']]['Resource'] = "-" ;
                }
                
                (!empty($data['maxCardinality'])) ? $this->data[$data['property']]['cardinalities'][$class]['maxCardinality'] = $data['maxCardinality'] : $this->data[$data['property']]['cardinalities'][$class]['maxCardinality'] = "-";
                (!empty($data['minCardinality'])) ? $this->data[$data['property']]['cardinalities'][$class]['minCardinality'] = $data['minCardinality'] : $this->data[$data['property']]['cardinalities'][$class]['minCardinality'] = "-";
                (!empty($data['cardinality'])) ? $this->data[$data['property']]['cardinalities'][$class]['cardinality'] = $data['cardinality'] : $this->data[$data['property']]['cardinalities'][$class]['cardinality'] = "-";
                (!empty($data['recommendedClass'])) ? $this->data[$data['property']]['cardinalities'][$class]['recommendedClass'] = $data['recommendedClass'] : $this->data[$data['property']]['cardinalities'][$class]['recommendedClass'] = "-";
                //(!empty($data['recommendedClass'])) ? $this->data[$data['property']][$class]['recommendedClass'] = $data['recommendedClass'] : $this->data[$data['property']]['cardinalities'][$class]['recommendedClass'] = "-";
                
                $this->data[$data['property']][ucfirst($class)] = (!empty($this->checkCardinality($data, ucfirst($class)))) ? $this->checkCardinality($data, ucfirst($class)) : "-";
            }
        }
    }
    
    /**
     * Check the property cardinalities
     *
     * @param array $data
     * @return string
     */
    private function checkCardinality(array $data, string $class = ""): string
    {
        $cardinalities = "";
        
        //Mandatory: min cardinality is at least one
        (isset($data['minCardinality']) && !empty($data['minCardinality']) && ($data['minCardinality'] >= 1)) ? $cardinalities = "m" : "";
                
        //Optional: no min cardinality set
        ((isset($data['minCardinality']) && (empty($data['minCardinality']) || $data['minCardinality'] == 0))
            || ((!isset($data['minCardinality'])))) ? $cardinalities = "o" : "";
        
          
        //Mandatory: cardinality is at least one
        (isset($data['cardinality']) && (!empty($data['cardinality']) && ($data['cardinality'] == 1)))? $cardinalities = "m" : "";
        
        //recommended
        if (isset($data['recommendedClass']) && !empty($data['recommendedClass'])) {
            if (!empty($class)) {
                if (strpos($data['recommendedClass'], $class) !== false) {
                    $cardinalities = "r";
                }
            }
        }
        
        //recommended
        if (isset($data['recommendedClass']) && !empty($data['recommendedClass'])) {
            if (!empty($class)) {
                if (strpos($data['recommendedClass'], $class) !== false) {
                    $cardinalities = "r";
                }
            }
        }
        
        //Multiple (*): no max cardinality set
        ((isset($data['maxCardinality']) && empty($data['maxCardinality']))
            && ((isset($data['cardinality'])) && $data['cardinality'] != 1)) ? $cardinalities .= "*" : "";
        
                
        return $cardinalities;
    }
}
