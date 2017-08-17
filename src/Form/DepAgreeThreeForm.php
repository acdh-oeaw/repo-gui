<?php

namespace Drupal\oeaw\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class DepAgreeThreeForm extends DepAgreeBaseForm{
    
    private $formDataThree = array();
    private $folder_name = "";
    private $transfer_method = "";
    private $data_validation = "";
            
    public function getFormId() {
        return 'depagree_form';
    }
    
    private function setupFields(){
    
        $dataField = array();
        $dataThree = json_decode($this->dbData["data"]);
        if(isset($dataThree->three)){
            $dataField = $dataThree->three; 
        }

        if(count($dataField) > 0 && isset($dataField)){

            if(isset($dataField->folder_name)){
                $this->folder_name = $dataField->folder_name;
            }
            if(isset($dataField->transfer_method)){
                $this->transfer_method = $dataField->transfer_method;
            }
            if(isset($dataField->data_validation)){
                $this->data_validation = $dataField->data_validation;
            }           
        }        
    }
    
    public function buildForm(array $form, FormStateInterface $form_state, $formid = NULL) {
                
        $form = parent::buildForm($form, $form_state);
       
        $repoFormID = \Drupal::routeMatch()->getParameter("formid");
        
        $this->checkRepoId($repoFormID);
        
        if(!empty($this->repoid) && !empty($this->dbData)){
            if(count($this->dbData) > 0){
                $this->setupFields();
            }            
        }
        
        $form['transfer'] = array(
            '#type' => 'fieldset',
            '#title' => t('<b>Transfer Procedures</b>'),
            '#collapsible' => TRUE,
            '#collapsed' => FALSE,  
        );       
        
        $form['transfer']['folder_name'] = array(
            '#type' => 'textfield',
            '#title' => t('Folder name or BagIt name:'),
            '#required' => TRUE,
            '#default_value' => $this->folder_name,
        );
        
        $form['transfer']['transfer_date'] = array(
            '#type' => 'textfield',
            '#title' => t('Transfer date:'),
            '#attributes' => array("readonly" => TRUE),
            '#default_value' => date("d-m-Y")            
        );
       
        $form['transfer']['transfer_method'] = array(
            '#type' => 'radios',
            '#title' => t('Transfer medium and method:'),
            '#options' => \Drupal\oeaw\DepAgreeConstants::getTransferMedium(),
            '#description' => $this->t('e.g. hard drive, CD, DVD, USB stick, network transfer'),    
            '#required' => TRUE,
            '#default_value' => $this->transfer_method,
        );
        
        
        $form['transfer']['data_validation'] = array(
            '#type' => 'radios',
            '#title' => t('Data Validation:'),
            '#options' => \Drupal\oeaw\DepAgreeConstants::getDataValidation(),
            '#description' => $this->t(''),
            '#required' => TRUE,
            '#default_value' => $this->data_validation,
        );
       
        $form['actions']['previous'] = array(
            '#type' => 'link',
            '#title' => $this->t('Previous'),
            '#attributes' => array(
                'class' => array('btn'),
                'style' => 'margin:10px; color:white;'
            ),
            '#weight' => 0,
            '#url' => Url::fromRoute('oeaw_depagree_two', array('formid' => $this->repoid))
        );
        
        $form['actions']['submit']['#value'] = $this->t('Next');       
        
        
        return $form;
  }
  
   public function submitForm(array &$form, FormStateInterface $form_state) {
       
    $form3Val = array();
    //get the class and root values from the form
    $form3Val['folder_name'] = $form_state->getValue('folder_name');
    $form3Val['transfer_date'] = $form_state->getValue('transfer_date');
    $form3Val['transfer_method'] = $form_state->getValue('transfer_method');
    $form3Val['data_validation'] = $form_state->getValue('data_validation');
        
    $this->store->set('folder_name', $form_state->getValue('folder_name'));
    $this->store->set('transfer_date', $form_state->getValue('transfer_date'));
    $this->store->set('transfer_method', $form_state->getValue('transfer_method'));
    $this->store->set('data_validation', $form_state->getValue('data_validation'));
        
    $this->store->set('form3Val', $form3Val);
    
    $DBData = array();
    
    //we have record in the DB, so it will be an DB update
    if(isset($this->dbData["data"]) && !empty($this->dbData["data"])){        
        $DBData = json_decode($this->dbData["data"], TRUE);        
        //if we dont have a key then we creating one
        if(array_key_exists('three', $DBData) == null){
            $DBData["three"] = $form3Val;
        }else {
            //if we have it then it will be a modification
            foreach($DBData as $key => $value){
                if($key == "three"){
                    $DBData["three"] = $form3Val;
                }
            }
        }
        //we jsut creating the json encode data
        $jsonObj = json_encode($DBData);

        $num_updated = db_update('oeaw_forms')
            ->fields(array(
                    'data' => $jsonObj,
                    'date'=>  date("d-m-Y H:i:s")
            ))
            ->condition('userid', \Drupal::currentUser()->id(), '=')
            ->condition('repoid', $this->repoid, '=')
            ->condition('status', "open", '=')
            ->execute();        

    }else {
        $DBData["three"] = $form3Val;
        $jsonObj = json_encode($DBData);
        //this will be a new DB insert
        $field = array(
            'userid' => \Drupal::currentUser()->id(),
            'repoid' => $this->repoid,        
            'data' => $jsonObj,
            'status' =>  'open',
            'date'=>  date("d-m-Y H:i:s")
        );
        db_insert('oeaw_forms')
            ->fields($field)
            ->execute();
    }
    
    $form_state->setRedirect('oeaw_depagree_four', array('formid' => $this->repoid));
   }
    
}
