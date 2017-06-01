<?php

namespace Drupal\oeaw\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\user\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\oeaw\OeawStorage;
use Drupal\oeaw\OeawFunctions;

class ClassForm extends FormBase
{
    
    
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
    
    private $OeawStorage;    
    private $OeawFunctions;
    
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
        
        $this->store = $this->tempStoreFactory->get('class_search_data');
        
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
    
    public function getFormId()
    {
        return "class_form";
    }
    
    /*
    * {@inheritdoc}.
    */
    public function buildForm(array $form, FormStateInterface $form_state) 
    {
        $data = $this->OeawStorage->getClassesForSideBar();
        $searchClasses = array();
        
        if(empty($data)){
            drupal_set_message($this->t('Your DB is EMPTY! There are no Propertys'), 'error');
            return $form;            
        }
        
        /* get the fields from the sparql query */
        $fields = array_keys($data[0]);
        
        $searchTerms = $this->OeawFunctions->createPrefixesFromArray($data, $fields);        
        
        $i = 0;
        foreach($searchTerms["type"] as $v){
            $searchClasses[$i]["type"] = $v;
            $searchClasses[$i]["value"] = $searchTerms["typeCount"][$i];
            $i++;
        }
        asort($searchClasses);
        
        $i = 0;
        $lbl = "";
        $count = "";

        foreach($searchClasses as $value){
            foreach($value as $k => $v){
                
                if($k == "type"){ $lbl = $v; }                
                if($k == "value"){ $count = $v; }
                
                $form[$lbl] = array(
                    '#type' => 'submit',
                    '#name' => 'class',
                    '#value' => $this->t($lbl." (".$count.")"),                    
                    '#button_type' => 'primary',
                    '#prefix' => "<br/>",
                );
            }            
            $i++;
        }
       
        return $form;
    }
    
    
    public function validateForm(array &$form, FormStateInterface $form_state) 
    {
        /*
        if (strlen($form_state->getValue('metavalue')) < 1) {
            $form_state->setErrorByName('metavalue', $this->t(''));
        }*/
        
    }
  
  
  
    public function submitForm(array &$form, FormStateInterface $form_state) {
        
        $classes = $form_state->getValue('class');
        $classes = urlencode($classes);
        $form_state->setRedirect('oeaw_classes_result', ["search_classes" => $classes]); 
        //$form_state->setRedirectUrl($url);
    }
  
}

