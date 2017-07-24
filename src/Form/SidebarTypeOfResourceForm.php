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
	    
	    
	    
	    /*
        $propertys = array();
        $searchTerms = array();
                
        $propertys = $this->OeawStorage->getAllPropertyForSearch();
  
        if(empty($propertys)){
             drupal_set_message($this->t('Your DB is EMPTY! There are no Propertys -> SidebarTypeOfResourceForm '), 'error');
             return $form;
        }else {
            $fields = array();
            // get the fields from the sparql query 
            $fields = array_keys($propertys[0]);        
            $searchTerms = $this->OeawFunctions->createPrefixesFromArray($propertys, $fields);

            $searchTerms = $searchTerms["p"];
            asort($searchTerms);

            if(count($searchTerms) > 0) {

                foreach($searchTerms as $terms){
                    $select[$terms] = t($terms);
                }
                
                // If there was a keyword search already, use it in the input as value
                $fullpath = $_SERVER['REQUEST_URI'];
                $fullpath = explode("/", $fullpath);
                if (count($fullpath) == 3) {
	                $defaultterm = end($fullpath);
	                $actionterm = prev($fullpath);
	                if ($actionterm != "oeaw_keywordsearch") {
		               $defaultterm = "";
	                }
                } else {
	                $defaultterm = "";
                }			    




			    $form['checkbox-1'] = array(
			      '#type' => 'container',
			      '#attributes' => array(
			        'class' => array('form-checkbox-custom')
			      ),
			    );

                $form['checkbox-1']['checkbox'] = array(
                  '#type' => 'checkbox',
                  '#title' => $this->t('First Choice'),
                  '#attributes' => array(
                    'class' => array('checkbox-custom'),
                    'id' => array('checkbox-1')
				  )
                );


			    $form['checkbox-2'] = array(
			      '#type' => 'container',
			      '#attributes' => array(
			        'class' => array('form-checkbox-custom')
			      ),
			    );

                $form['checkbox-2']['checkbox'] = array(
                  '#type' => 'checkbox',
                  '#title' => $this->t('Second Choice'),
                  '#attributes' => array(
                    'class' => array('checkbox-custom'),
                    'id' => array('checkbox-2')
				  )
                );







                return $form;
            } else {            
                drupal_set_message($this->t('Your DB is EMPTY! There are no Propertys -> SidebarKeywordSearchForm'), 'error');
                return $form;
            }
        }
        
        */
        
        
        
        
        
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
            
           

            $i = 0;
            $lbl = "";
            $count = "";

            foreach($searchClasses as $value){
                foreach($value as $k => $v){

                    if($k == "type"){ $lbl = $v; }
                    if($k == "value"){ $count = $v; }
                    
                    if (preg_match("/^acdh:/", $lbl)) {
	                    $label = explode('acdh:', $lbl)[1];               
                    
					    $form['checkbox-'.$label] = array(
					      '#type' => 'container',
					      '#attributes' => array(
					        'class' => array('form-checkbox-custom')
					      ),
					    );
		
		                $form['checkbox-'.$label]['checkbox'] = array(
		                  '#type' => 'checkbox',
		                  '#title' => $this->t($label." (".$count.")"),
		                  '#attributes' => array(
		                    'class' => array('checkbox-custom'),
		                    'id' => array('checkbox-'.$label)
						  )
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

