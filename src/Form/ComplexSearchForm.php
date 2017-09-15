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

class ComplexSearchForm extends FormBase
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
        $this->createSearchInput($form);
        
        //$this->createTypeData();
        
        $resData["title"] = "Type of Resource";
        $resData["type"] = "searchbox_types";
        $resFields = $this->OeawStorage->getACDHTypes(true);
        
        $rs = array();
        foreach($resFields as $val){            
            $type = str_replace('https://vocabs.acdh.oeaw.ac.at/#', '', $val['type']);
            $count = str_replace('https://vocabs.acdh.oeaw.ac.at/#', '', $val['type'])." (".$val['typeCount'].")";
            $rs[$type] = $count;
        }
        $resData["fields"] = $rs;
        if(count($resData["fields"]) > 0){
            $this->createBox($form, $resData);
        }
        
        $formatData["title"] = "Format";
        $formatData["type"] = "searchbox_format";
        $formatFields = $this->OeawStorage->getMimeTypes();
        $frm = array();
        foreach($formatFields as $val){            
            $type = $val['mime'];
            $count = $val['mime']." (".$val['mimeCount'].")";
            $frm[$type] = $count;
        }
        $formatData["fields"] = $frm;
        
        if(count($formatData["fields"]) > 0){
            $this->createBox($form, $formatData);
        }
        
        
        $form['date_start_date'] = [
            '#type' => 'date',           
            '#date_format' => 'd-m-Y',
            '#title' => t('Start date'),
        ];
        
        $form['date_end_date'] = [
            '#type' => 'date',
            '#date_format' => 'd-m-Y',
            '#title' => t('End date'),
        ];
        
        return $form;
        
    }
    
    /**
     * 
     * Create the checbox templates
     * 
     * @param array $form
     * @param array $data
     * 
     */
    private function createBox(array &$form, array $data){
        
        $form['search'][$data["type"]] = array(
            '#type' => 'checkboxes',            
            '#title' => $this->t($data["title"]),
            '#attributes' => array(
                'class' => array('checkbox-custom'),
                //'onClick' => 'window.location = "'.base_path().'browser/oeaw_complexsearch/q=type'.$data["title"].'/10/0";'
            ),
            '#options' =>
                $data["fields"]
        );
    }
    
    
    /**
     * 
     * this function creates the search input field 
     * 
     * @param array $form
     * @return array
     */
    private function createSearchInput(array &$form){
        
        $propertys = array();
        $searchTerms = array();
        $basePath = base_path();
        $propertys = $this->OeawStorage->getAllPropertyForSearch();
        
        if(empty($propertys)){
             drupal_set_message($this->t('Your DB is EMPTY! There are no Propertys -> CustomSearchForm '), 'error');
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
                
                $form['metavalue'] = array(
                    '#type' => 'textfield',
                    '#attributes' => array(             
                        'class' => array('form-control')
                    ),                            
                    #'#required' => TRUE,
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

                
            } else {            
                drupal_set_message($this->t('Your DB is EMPTY! There are no Propertys -> CustomSearchForm'), 'error');
                
            }
        }
    }
    
    public function validateForm(array &$form, FormStateInterface $form_state) 
    {
        
    }
    
  
    
    
    public function submitForm(array &$form, FormStateInterface $form_state) {
        
        $metavalue = $form_state->getValue('metavalue');
        
        $extras = array();
        
        $types = $form_state->getValue('searchbox_types');
        $types = array_filter($types);
        $formats = $form_state->getValue('searchbox_format');
        $formats = array_filter($formats);
        
        $startDate = $form_state->getValue('date_start_date');
        $endDate = $form_state->getValue('date_end_date');
                
        if(count($types) > 0){
            foreach ($types as $t){
                $extras["type"][] = strtolower($t);
            }
        }
        
        if(count($formats) > 0){
            foreach ($formats as $f){
                $extras["formats"][] = strtolower($f);
            }
        }
        
        if(!empty($startDate) && !empty($endDate)){
            $extras["start_date"] = $startDate;
            $extras["end_date"] = $endDate;
        }
        
        $metaVal = $this->OeawFunctions->convertSearchString($metavalue, $extras);
        $metaVal = urlencode($metaVal);
        $form_state->setRedirect('oeaw_complexsearch', ["metavalue" => $metaVal, "limit" => 10]); 
        
    }
  
}
 
