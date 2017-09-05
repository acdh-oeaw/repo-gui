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

class SidebarKeywordSearchForm extends FormBase
{
    
    private $OeawStorage;
    private $OeawFunctions;
    
    public function __construct() {    
        $this->OeawStorage = new OeawStorage();
        $this->OeawFunctions = new OeawFunctions();
    }
    
    public function getFormId()
    {
        return "sks_form";
    }
    
    /*
    * {@inheritdoc}.
    */
    public function buildForm(array $form, FormStateInterface $form_state) 
    {   
        $propertys = array();
        $searchTerms = array();
        $basePath = base_path();
        $propertys = $this->OeawStorage->getAllPropertyForSearch();
  
        if(empty($propertys)){
             drupal_set_message($this->t('Your DB is EMPTY! There are no Propertys -> SidebarKeywordSearchForm '), 'error');
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

                $form['metavalue'] = array(
                  '#type' => 'textfield',
                  '#attributes' => array(
	                'value' => $defaultterm, 
                    'class' => array('form-control')
				  ),                            
                  '#required' => TRUE,
                );

                $form['actions']['#type'] = 'actions';
                $form['actions']['submit'] = array(
                  '#type' => 'submit',
                  '#value' => $this->t('search'),
                  '#attributes' => array(
                    'class' => array('keywordsearch-btn')
				  ),                   
                  '#button_type' => 'primary',
                );


                $form['examples'] = array(
                  '#type' => 'container',
                  '#attributes' => array(
                    'class' => array('form-examples')
                  ),
                  '#markup' => $this->t('Keyword examples for a quick-start:'),
                );
			    
                $form['examples']['example-1'] = array(
                  '#type' => 'container',
                  '#markup' => $this->t('Austria'),
                  '#attributes' => array(
                    'class' => array('form-example-btn'),
                    'onClick' => 'window.location = "'.$basePath.'oeaw_keywordsearch/Austria";'
				  ),                   
                  '#button_type' => 'primary',
                );

                $form['examples']['example-2'] = array(
                  '#type' => 'container',
                  '#markup' => $this->t('Media'),
                  '#attributes' => array(
                    'class' => array('form-example-btn'),
                    'onClick' => 'window.location = "'.$basePath.'oeaw_keywordsearch/Media";'
				  ),                   
                  '#button_type' => 'primary',
                );
                

                $form['examples']['example-3'] = array(
                  '#type' => 'container',
                  '#markup' => $this->t('History'),
                  '#attributes' => array(
                    'class' => array('form-example-btn'),
                    'onClick' => 'window.location = "'.$basePath.'oeaw_keywordsearch/History";'
				  ),                   
                  '#button_type' => 'primary',
                );
                

                return $form;
            } else {            
                drupal_set_message($this->t('Your DB is EMPTY! There are no Propertys -> SidebarKeywordSearchForm'), 'error');
                return $form;
            }
        }
        
        
    }
    
    
    public function validateForm(array &$form, FormStateInterface $form_state) 
    {
        
    }
    
  
    
    
    public function submitForm(array &$form, FormStateInterface $form_state) {
        
        $metavalue = $form_state->getValue('metavalue');
        // Data AND thun NOT editions type:Collection NOT Person date:[20170501 TO 20171020]
        $metavalue = str_replace('+', '%2B', $metavalue);
        
        $metavalue = str_replace('%253A', ':', $metavalue);
        $metavalue = str_replace('%255B', '[', $metavalue);
        $metavalue = str_replace('%255D', ']', $metavalue);        
        $metavalue = str_replace('+TO+', '_-_', $metavalue);
                
        $metaVal = $this->OeawFunctions->convertSearchString($metavalue);        
        $metaVal = urlencode($metaVal);
     
        $form_state->setRedirect('oeaw_keywordsearch', ["metavalue" => $metaVal]); 
    
    }
  
}

