<?php

namespace Drupal\oeaw\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;


/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "my_rest_resource",
 *   label = @Translation("My rest resource"),
 *   serialization_class = "",
 *   uri_paths = {
 *     "canonical" = "/my_rest_resource/{id}"
 *   }
 * )
 */
class MyRestResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   */
    public function __construct(
        array $configuration,
        $plugin_id,
        $plugin_definition,
        array $serializer_formats,
        LoggerInterface $logger,
        AccountProxyInterface $current_user) {
        parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

        $this->currentUser = $current_user;
    }

    /**
    * {@inheritdoc}
    */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->getParameter('serializer.formats'),
            $container->get('logger.factory')->get('my_module'),
            $container->get('current_user')
        );
    }
 
    
    /**
    * Responds to GET requests.
    *
    * Returns a node entry for the specified Node ID.
    *
    * @param int $id
    *   The ID of the node entry.
    *
    * @return \Drupal\rest\ResourceResponse
    *   The response containing the log entry.
    *
    * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
    *   Thrown when the log entry was not found.
    * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
    *   Thrown when no log entry was provided.
    */
    public function get($id = NULL) {
        if ($id) {            
            $record = db_query("SELECT * FROM {node} WHERE nid = :id", array(':id' => $id))
            ->fetchAssoc();
            if (!empty($record)) {
                return new ResourceResponse($record);
            }

            throw new NotFoundHttpException(t('Log entry with ID @id was not found', array('@id' => $id)));
        }

        throw new BadRequestHttpException(t('No log entry ID was provided'));
  }
 
    
    public function post(array $data = []) {
        
        error_log(print_r($data, true));
        
        $response = array(
            "hello_world" => $data,
        );
        
        return new JsonResponse( $response );
        //return new ResourceResponse($response);
    }

  
}