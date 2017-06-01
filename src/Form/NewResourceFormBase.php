<?php

/**
 * @file
 * Contains \Drupal\demo\Form\Multistep\MultistepFormBase.
 */

namespace Drupal\oeaw\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\user\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\FedoraResource;
use acdhOeaw\util\RepoConfig as RC;
use Drupal\oeaw\OeawStorage;
use Drupal\oeaw\OeawFunctions;
use Symfony\Component\HttpFoundation\RedirectResponse;


abstract class NewResourceFormBase extends FormBase {
   
    /**
    * @var \Drupal\user\PrivateTempStoreFactory
    */
    protected $tempStoreFactory;

    /**
    * @var \Drupal\Core\Session\SessionManagerInterface
    */
    private $sessionManager;

    /**
    * @var \Drupal\Core\Session\AccountInterface
    */
    private $currentUser;

    /**
    * @var \Drupal\user\PrivateTempStore
    */
    protected $store;
    protected $OeawStorage;
    protected $OeawFunctions;
    
    /**
   * Constructs a Multi step form Base.
   *
   * @param \Drupal\user\PrivateTempStoreFactory $temp_store_factory
   * @param \Drupal\Core\Session\SessionManagerInterface $session_manager
   * @param \Drupal\Core\Session\AccountInterface $current_user
   */
    
    public function __construct(PrivateTempStoreFactory $temp_store_factory, SessionManagerInterface $session_manager, AccountInterface $current_user) {    
        $this->tempStoreFactory = $temp_store_factory;
        $this->sessionManager = $session_manager;
        $this->currentUser = $current_user;
        \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');
        $this->store = $this->tempStoreFactory->get('multistep_data');
        $this->OeawStorage = new OeawStorage();
        $this->OeawFunctions = new OeawFunctions();        
    }
    
    public static function create(ContainerInterface $container){
        return new static(
                $container->get('user.private_tempstore'),
                $container->get('session_manager'),
                $container->get('current_user')
        );
    }
    
    public function buildForm(array $form, FormStateInterface $form_state)
    {        
        //start a manual session for anonymus user
        if($this->currentUser->isAnonymous() && !isset($_SESSION['multistep_form_holds_session'])) {
            $_SESSION['multistep_form_holds_session'] = true;
            $this->sessionManager->start();
        }
        
        $form = array();
        
        $form['actions']['#type'] = 'actions';
        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Submit'),
            '#button_type' => 'primary',
            '#weight' => 10,
        );

        return $form;        
    }
    
    /*
     * Saves data from the multistep form
    */
    
    protected function saveData()
    {
        //get the values from the forms
        $root = $this->store->get('form1Elements')['root'];        
        $metadata = $this->store->get('form2Elements');        
        $fileName = $this->store->get('fileName');
        $uriAndValue = $this->store->get('uriAndValue');
        $ontologyClassIdentifier = $this->store->get('ontologyClassIdentifier');
  
        $uid = \Drupal::currentUser()->id();
        
        $graph = array();
        $meta = array();
        //create an easyrdf_graph instance
        $graph = new \EasyRdf\Graph();            
        $meta = $graph->resource('acdh');
        
        // creating the resources for the Fedora class
        foreach($uriAndValue as $key => $value){
            if(!empty($value)){
                foreach($value as $v){
                    if(!empty($v)){
                        if (strpos(substr($v,0,4), 'http') !== false) {
                            //$meta->addResource("http://vocabs.acdh.oeaw.ac.at/#represents", "http://dddd-value2222");
                            $meta->addResource($key, $v);
                        } else {
                            //$meta->addLiteral("http://vocabs.acdh.oeaw.ac.at/#depositor", "dddd-value");
                            $meta->addLiteral($key, $v);
                        }
                    }
                }
            }
        }
      
        // add the ontologyClass dct:identifier to the new resource rdf:type, to we can
        // recognize the ontologyclass and required fields to the editing form
        $meta->addResource("http://www.w3.org/1999/02/22-rdf-syntax-ns#type", $ontologyClassIdentifier);  
        
        //load the config file
        $fedora = new Fedora();
        $fedora->begin();

        try{
            
            if(empty($fileName)){
                $res = $fedora->createResource($meta);
            } else {
                $res = $fedora->createResource($meta, $fileName);
            }            
            
            $fedora->commit();
            //create the new reosurce uri for the user
            $uri = $res->getUri();
            $uri = preg_replace('|/tx:[-a-zA-Z0-9]+/|', '/', $uri);
            $uri = $uri.'/fcr:metadata';
            
            $encodeUri = $this->OeawFunctions->createDetailsUrl($uri, 'encode');
            
            if (strpos($encodeUri, 'fcr:metadata') !== false) {
                $encodeUri = $encodeUri.'/fcr:metadata';
            }
            
            $datatable = array(
                '#theme' => 'oeaw_success_resource',
                '#result' => $encodeUri,
                '#userid' => $uid,
                '#attached' => [
                    'library' => [
                    'oeaw/oeaw-styles', //include our custom library for this response
                    ]
                ]
            );    

            $response = new RedirectResponse(\Drupal::url('oeaw_new_success', ['uri' => $encodeUri]));
            $response->send();
            return;
            
        } catch (Exception $ex) {
            
            $fedora->rollback();            
            
            return drupal_set_message($this->t('Error during the saving process'), 'error');
        }
    }
    
}
