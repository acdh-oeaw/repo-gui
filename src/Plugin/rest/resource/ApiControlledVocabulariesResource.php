<?php

namespace Drupal\oeaw\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;

/**
 * U
 *
 * @RestResource(
 *   id = "api_controlledvocabs",
 *   label = @Translation("ARCHE Update Controlled Vocabs on GUI"),
 *   uri_paths = {
 *     "canonical" = "/api/controlledvocabs/{lng}"
 *   }
 * )
 */
class ApiControlledVocabulariesResource extends ResourceBase
{
    /**
    * Responds to entity GET requests.
     *
    * @return \Drupal\rest\ResourceResponse
    */
    public function get(string $lng)
    {
        ini_set('max_execution_time', 3600);
        ini_set('max_input_time', 360);
        $response = array();
                 
        $helper = new \Drupal\oeaw\Helper\CacheVocabsHelper($lng);
        $vocabs = array();
        $vocabs = $helper->getControlledVocabStrings();
       
        if (isset($vocabs['error'])) {
            $response = json_encode($vocabs['error'], true)." not cached/generated";
        } else {
            $response = json_encode("Update is ready!");
        }
        return new ResourceResponse($response);
    }
}
