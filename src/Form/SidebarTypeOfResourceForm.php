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

class SidebarTypeOfResourceForm extends FormBase
{
    
    private $OeawStorage;
    private $OeawFunctions;
    
    public function __construct() {    
        $this->OeawStorage = new OeawStorage();
        $this->OeawFunctions = new OeawFunctions();
    }
    
    public function getFormId()
    {
        return "stor_form";
    }
    
    /*
    * {@inheritdoc}.
    */
    public function buildForm(array $form, FormStateInterface $form_state) 
    {   
      
        
        $data = $this->OeawStorage->getClassesForSideBar();
        $searchClasses = array();
        
        if(count($data) == 0){
            drupal_set_message($this->t('Your DB is EMPTY! There are no Propertys'), 'error');
            return $form;            
        } else {
            // get the fields from the sparql query 
            $fields = array_keys($data[0]);

            $searchTerms = $this->OeawFunctions->createPrefixesFromArray($data, $fields);        

            $i = 0;
            foreach($searchTerms["type"] as $v){
	            $searchClasses[$i]["type"] = $v;
	            $searchClasses[$i]["value"] = $searchTerms["typeCount"][$i];
                $i++;
            }
            asort($searchClasses);
            
            // If there was a keyword search already, use it in the input as value
            $fullpath = $_SERVER['REQUEST_URI'];
            $fullpath = explode("/", $fullpath);
            if (count($fullpath) == 3) {
                $defaultterm = end($fullpath);
                $actionterm = prev($fullpath);
                if ($actionterm != "oeaw_classes_result") {
	               $defaultterm = "";
                }
            } else {
                $defaultterm = "";
            }	
           

            $i = 0;
            $lbl = "";
            $count = "";

            foreach($searchClasses as $value){
                foreach($value as $k => $v){

                    if($k == "type"){ $lbl = $v; }
                    if($k == "value"){ $count = $v; }
                    
                    if (preg_match("/^acdh:/", $lbl)) {
	                    $label = explode('acdh:', $lbl)[1];
	                    $labelReadable = preg_replace('/(?<! )(?<!^)[A-Z]/',' $0', $label);               
						
						$termencoded = base64_encode($lbl);
						if ($defaultterm == $termencoded) {
							$checked = TRUE;
						} else {
							$checked = FALSE;
						}
                    
					    $form['checkbox-'.$label] = array(
					      '#type' => 'container',
					      '#attributes' => array(
					        'class' => array('form-checkbox-custom'),
					        'onClick' => 'window.location = "/oeaw_classes_result/'.base64_encode($lbl).'";'
					      ) 
					    );
		
		                $form['checkbox-'.$label]['checkbox'] = array(
		                  '#type' => 'checkbox',
		                  '#title' => $this->t($labelReadable." (".$count.")"),
		                  '#attributes' => array(
		                    'class' => array('checkbox-custom'),
		                    'id' => array('checkbox-'.$label)
						  ),
						  '#default_value' => $checked
		                );                    
                    
                    }
                    
                    
                }            
                $i++;
            }

            return $form;       
        
        }
        
        
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

