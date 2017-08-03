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

class SidebarDateForm extends FormBase
{
    
    private $OeawStorage;
    private $OeawFunctions;
    
    public function __construct() {    
        $this->OeawStorage = new OeawStorage();
        $this->OeawFunctions = new OeawFunctions();
    }
    
    public function getFormId()
    {
        return "sdate_form";
    }
    
    /*
    * {@inheritdoc}.
    */
    public function buildForm(array $form, FormStateInterface $form_state) 
    {   
        
	  	$form['pickup_date'] = array(
		  	'#type' => 'date', 
		  	'#prefix' => '<div class="container-inline">',
			'#suffix' => '</div>',	  
		);


        return $form;       

        
    }
    
    
    public function validateForm(array &$form, FormStateInterface $form_state) 
    {
        
    }
    
  
    public function submitForm(array &$form, FormStateInterface $form_state) {
        
        $metavalue = $form_state->getValue('metavalue');
        //$metavalue = base64_encode($metavalue);
        $metavalue = urlencode($metavalue);        
        $form_state->setRedirect('oeaw_keywordsearch', ["metavalue" => $metavalue]); 
    
    }
  
}

