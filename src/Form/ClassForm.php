<?php

namespace Drupal\oeaw\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\oeaw\Model\OeawStorage;
use Drupal\oeaw\OeawFunctions;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ClassForm extends FormBase
{
  
    private $oeawStorage;    
    private $oeawFunctions;
    
    /**
     * Set up the necessary properties
     */
    public function __construct() 
    {
        $this->oeawStorage = new OeawStorage();
        $this->oeawFunctions = new OeawFunctions();
    }
    
    /**
     * Set up the form id
     * @return string
     */
    public function getFormId()
    {
        return "class_form";
    }
    
    /**
     * Class form 
     * 
     * @param array $form
     * @param FormStateInterface $form_state
     * @return array
     */
    public function buildForm(array $form, FormStateInterface $form_state) 
    {
        try{
            $data = $this->oeawStorage->getClassesForSideBar();
        } catch (\ErrorException $ex) {
            drupal_set_message($ex->getMessage(), 'error');
            return array();
        }
        
        $searchClasses = array();
        
        if(count($data) == 0){
            drupal_set_message($this->t('Your DB is EMPTY! There are no Propertys'), 'error');
            return $form;            
        } else {
            // get the fields from the sparql query 
            $fields = array_keys($data[0]);

            $searchTerms = $this->oeawFunctions->createPrefixesFromArray($data, $fields);        

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
                        '#type' => 'link',
                        '#attributes' => array(
                            'class' => array('btn'),
                            'style' => 'margin:10px; color:white;'
                        ),
                        '#title' => $this->t($lbl." (".$count.")"),
                        '#name' => 'class',
                        '#value' => $this->t($lbl." (".$count.")"),
                        '#url' => Url::fromRoute('oeaw_classes_result', ['data' => base64_encode($lbl)])
                    );
                }            
                $i++;
            }
            return $form;
        }
    }
    
    /**
     * Class Form submit
     * @param array $form
     * @param FormStateInterface $form_state
     */
    public function submitForm(array &$form, FormStateInterface $form_state) 
    {   
    }
  
}

