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
        $response = array();
                 
        $helper = new \Drupal\oeaw\Helper\CacheVocabsHelper($lng);
        if ($helper->getControlledVocabStrings() === false) {
            $response = json_encode("Update failed!");
        } else {
            $response = json_encode("Update is ready!");
        }
        return new ResourceResponse($response);
    }
}
