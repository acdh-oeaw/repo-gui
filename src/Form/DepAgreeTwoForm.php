<?php

namespace Drupal\oeaw\Form;


use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class DepAgreeTwoForm extends DepAgreeBaseForm{
    
    private $formDataTwo = array();
    private $material_title = "";
    private $material_scope_content_statement = "";
    private $material_metadata = "";
    private $material_metadata_file = array();
    private $bagit_question = "";
    private $material_bagit_file = array();
    private $material_file_size_byte = "";
    private $material_file_number = "";
    private $material_folder_number = "";
    private $material_soft_req = "";
    private $material_arrangement = "";
    private $material_arrangement_file = array();
    private $material_name_scheme = "";
    private $material_file_types = "";
    private $material_other_file_type = "";
    private $material_file_formats = "";
    private $material_other_file_formats = "";
    
    public function getFormId() {
        return 'depagree_form';
    }
    
    private function setupFields(){
    
        $dataField = array();
        $dataTwo = json_decode($this->dbData["data"]);
        if(isset($dataTwo->two)){
            $dataField = $dataTwo->two; 
        }
        
        if(count($dataField) > 0 && isset($dataField)){

            if(isset($dataField->material_title)){
                $this->material_title = $dataField->material_title;
            }
            if(isset($dataField->material_scope_content_statement)){
                $this->material_scope_content_statement = $dataField->material_scope_content_statement;
            }
            if(isset($dataField->material_metadata)){
                $this->material_metadata = $dataField->material_metadata;
            }
            if(isset($dataField->material_metadata_file)){
                $this->material_metadata_file = $dataField->material_metadata_file;
            }
            if(isset($dataField->bagit_question)){
                $this->bagit_question = $dataField->bagit_question;
            }
            if(isset($dataField->material_bagit_file)){
                $this->material_bagit_file = $dataField->material_bagit_file;
            }
            if(isset($dataField->material_file_size_byte)){
                $this->material_file_size_byte = $dataField->material_file_size_byte;
            }
            if(isset($dataField->material_file_number)){
                $this->material_file_number = $dataField->material_file_number;
            }
            if(isset($dataField->material_folder_number)){
                $this->material_folder_number = $dataField->material_folder_number;
            }
            if(isset($dataField->material_soft_req)){
                $this->material_soft_req = $dataField->material_soft_req;
            }
            if(isset($dataField->material_arrangement)){
                $this->material_arrangement = $dataField->material_arrangement;
            }
            if(isset($dataField->material_arrangement_file)){
                $this->material_arrangement_file = $dataField->material_arrangement_file;
            }
            if(isset($dataField->material_name_scheme)){
                $this->material_name_scheme = $dataField->material_name_scheme;
            }
            if(isset($dataField->material_file_types)){
                $this->material_file_types = $dataField->material_file_types;
            }
            if(isset($dataField->material_other_file_type)){
                $this->material_other_file_type = $dataField->material_other_file_type;
            }
            if(isset($dataField->material_file_formats)){
                $this->material_file_formats = $dataField->material_file_formats;
            }
            if(isset($dataField->material_other_file_formats)){
                $this->material_other_file_formats = $dataField->material_other_file_formats;
            }
        }        
    }
    
    
    public function buildForm(array $form, FormStateInterface $form_state, $formid = NULL) {
        
        $form_state->disableCache();
        $form = parent::buildForm($form, $form_state);
     
        $repoFormID = \Drupal::routeMatch()->getParameter("formid");
        
        $this->checkRepoId($repoFormID);
        
        if(!empty($this->repoid) && !empty($this->dbData)){
            if(count($this->dbData) > 0){
                $this->setupFields();
            }            
        }
        
        
        /*************** DESCRIPTION OF MATERIAL START ************************/
        
        $form['material'] = array(
            '#type' => 'fieldset',
            '#title' => t('<b>Description Of Material</b>'),
            '#collapsible' => TRUE,
            '#collapsed' => FALSE,  
        );
        
        $form['material']['material_acdh_repo_id'] = array(
            '#type' => 'textfield',
            '#title' => t('ARCHE ID:'),            
            '#required' => TRUE,
            '#default_value' => $this->repoid,
            '#attributes' => array("readonly" => TRUE),
            '#description' => $this->t('Automatically generated string used as internal identifier for the deposited resources'),
        );
        
        $form['material']['material_title'] = array(
            '#type' => 'textfield',
            '#title' => t('Title of resource collection:'),
            '#required' => TRUE,
            '#default_value' => $this->material_title,
            '#description' => $this->t(''),
        );
        
        $form['material']['material_scope_content_statement'] = array(
            '#type' => 'textarea',
            '#title' => t('Scope and content statement:'),
            '#required' => TRUE,
            '#default_value' => $this->material_scope_content_statement,
            '#description' => $this->t('Provide a description of genres, purpose, and content of the resources being deposited.'),
        );
        
        $form['material']['material_metadata'] = array(
            '#type' => 'textarea',
            '#title' => t('Metadata:'),
            '#required' => TRUE,
            '#default_value' => $this->material_metadata,
            '#description' => $this->t('Insert metadata as described in the <a href="/formats-filenames-and-metadata" target="_blank">metadata requirements</a> or upload metadata description. '),
        );
        
        $form['material']['material_metadata_file'] = array(
            '#type' => 'managed_file',
            '#title' => t('Metadata Resource:'),
            '#upload_location' => 'public://'.$this->repoid.'/',
            '#default_value' => $this->material_metadata_file,
            '#upload_validators' => array(
                'file_validate_extensions' => array('xml doc txt simplified docx pdf jpg png tiff gif bmp'),
             ),
            '#description' => $this->t(''),
            '#required' => FALSE,            
        );
        
        
        
        /*************** DESCRIPTION OF MATERIAL END ************************/
        
       
        
        
        
        /*************** EXTENT START ************************/
        $form['extent'] = array(
            '#type' => 'fieldset',
            '#title' => t('<b>Extent</b>'),
            '#collapsible' => TRUE,
            '#collapsed' => FALSE,
            '#field_prefix' => 'Provide information about the extent of the resources. If a BagIt will be submitted the Payload Manifest (manifest-<algorithm>.txt) can be uploaded instead'
        );
        
        
        $form['extent']['bagit_question'] = array(
            '#type' => 'radios',
            '#title' => t('Do you have a BagIt file?'),
            '#required' => TRUE,
            '#default_value' => $this->bagit_question ? $this->bagit_question : 0,
            '#options' => array(0 => $this->t('No'), 1 => $this->t('Yes')),
            '#description' => $this->t(''),
        );
                
        $form['extent']['bagit_container'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => 'bagit_container',
          ],
          '#states' => [
            'invisible' => [
              'input[name="bagit_question"]' => ['value' => 0],
            ],
          ],
        ];
                
        $form['extent']['bagit_container_fields'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => 'bagit_container_fields',
          ],
          '#states' => [
            'invisible' => [
              'input[name="bagit_question"]' => ['value' => 1],
            ],
          ],
        ];
             
        $form['extent']['bagit_container']['material_bagit_file'] = array(
            '#type' => 'managed_file',
            '#title' => t('Bagit File:'),
            '#upload_location' => 'public://'.$this->repoid.'/',
            '#default_value' => $this->material_bagit_file,
            '#upload_validators' => array(
                'file_validate_extensions' =>array('txt'),
             ),
            '#description' => $this->t('Please upload the Payload Manifest (manifest-<algorithm>.txt)'),
            '#required' => FALSE,                
        );
                
        $form['extent']['bagit_container_fields']['material_file_size_byte'] = array(
            '#type' => 'number',
            '#min' => 0,
            '#title' => t('Overall file size in bytes:'),
            '#required' => FALSE,
            '#default_value' => $this->material_file_size_byte,
            '#description' => $this->t(''),
        );

        $form['extent']['bagit_container_fields']['material_file_number'] = array(
            '#type' => 'number',
            '#title' => t('Number of files:'),
            '#min' => 0,
            '#required' => FALSE,
            '#default_value' => $this->material_file_number,
            '#description' => $this->t(''),
        );

        $form['extent']['bagit_container_fields']['material_folder_number'] = array(
            '#type' => 'number',
            '#min' => 0,
            '#title' => t('Number of folders:'),
            '#required' => FALSE,
            '#default_value' => $this->material_folder_number,
            '#description' => $this->t(''),
        );
                
        $form['extent']['material_arrangement'] = array(
            '#type' => 'textarea',
            '#title' => t('Arrangement:'),    
            '#required' => TRUE,
            '#default_value' => $this->material_arrangement,
            '#description' => $this->t('Give a logical and coherent overall view of the whole set of objects. Here the description of folder structure, nature of relationship between objects and metadata etc. are expected. If necessary, attach diagrams or screenshots from the original system.'),
        );
        
        $form['extent']['material_arrangement_file'] = array(
            '#type' => 'managed_file',
            '#title' => t('Arrangement File:'),
            '#upload_location' => 'public://'.$this->repoid.'/',
            '#upload_validators' => array(
                'file_validate_extensions' => array('xml doc txt simplified docx pdf jpg png tiff gif bmp'),
             ),            
            '#required' => FALSE,
            '#default_value' => $this->material_arrangement_file,
        );
        
        
        $form['extent']['material_name_scheme'] = array(
            '#type' => 'managed_file',
            '#title' => t('Naming scheme:'),
            '#upload_location' => 'public://'.$this->repoid.'/',
            '#upload_validators' => array(
                'file_validate_extensions' => array('xml doc txt simplified docx pdf jpg png tiff gif bmp'),
             ),
            '#description' => $this->t('If any, provide explanation of naming conventions used'),
            '#required' => TRUE,
            '#default_value' => $this->material_name_scheme,
        );
        
        /*************** EXTENT END ************************/
        
        if($this->store->get('material_file_types')){
            $form['material_file_types']['#default_value'] =  $this->material_file_types;
        }
        
        $form['material_file_types'] = array(
            '#type' => 'checkboxes',
            '#title' => t('List of file types included:'),            
            '#options' => \Drupal\oeaw\DepAgreeConstants::getFileTypes(),
            '#description' => $this->t(''),
            '#required' => TRUE        
        );
        
        $form['material_other_file_type'] = array(
            '#type' => 'textarea',
            '#title' => t('Other File types:'),               
            '#default_value' => $this->material_other_file_type,
            '#description' => $this->t('If your file type is not in the list, then please add it here.'),
        );
        
        if($this->store->get('material_file_formats')){
            $form['material_file_formats']['#default_value'] =  $this->material_file_formats;
        }
        $form['material_file_formats'] = array(
            '#type' => 'checkboxes',
            '#title' => t('List of file formats included:'),
            '#required' => TRUE,
            '#options' => \Drupal\oeaw\DepAgreeConstants::getFileFormats(),
            '#description' => $this->t(''),
        );
        
        $form['material_other_file_formats'] = array(
            '#type' => 'textarea',
            '#title' => t('Other File formats:'),            
            '#default_value' => $this->material_other_file_formats,
            '#description' => $this->t('If your file format is not in the list, then please add it here.'),
            '#state' => 'hidden'
        );
        
        $form['extent']['material_soft_req'] = array(
            '#type' => 'textarea',
            '#title' => t('Software requirements:'),            
            '#description' => $this->t('list any software programs formats that are not typically used in a standard office environment, that are required to access content being transferred'),
            '#required' => TRUE,
            '#default_value' => $this->material_soft_req,
        );
        
        $form['actions']['previous'] = array(
            '#type' => 'link',
            '#title' => $this->t('Previous'),
            '#attributes' => array(
                'class' => array('btn'),
                'style' => 'margin:5px; background-color:#88DBDF;'
            ),
            '#weight' => 0,
            '#url' => Url::fromRoute('oeaw_depagree_one', array('formid' => $this->repoid))
        );
        
        //create the next button to the form second page
        $form['actions']['submit']['#value'] = $this->t('Next');
        
        return $form;
    } 
  
   
    
    public function submitForm(array &$form, FormStateInterface $form_state) {   
                
        $form2Val = array();
        //get the class and root values from the form
        $form2Val['material_acdh_repo_id'] = $form_state->getValue('material_acdh_repo_id');
        $form2Val['material_title'] = $form_state->getValue('material_title');
        $form2Val['material_scope_content_statement'] = $form_state->getValue('material_scope_content_statement');
        $form2Val['material_metadata'] = $form_state->getValue('material_metadata');
        $form2Val['material_metadata_file'] = $form_state->getValue('material_metadata_file');
        $form2Val['bagit_question'] = $form_state->getValue('bagit_question');     
        $form2Val['material_bagit_file'] = $form_state->getValue('material_bagit_file');
        $form2Val['material_file_size_byte'] = $form_state->getValue('material_file_size_byte');
        $form2Val['material_file_number'] = $form_state->getValue('material_file_number');
        $form2Val['material_folder_number'] = $form_state->getValue('material_folder_number');                
        $form2Val['material_soft_req'] = $form_state->getValue('material_soft_req');
        $form2Val['material_arrangement'] = $form_state->getValue('material_arrangement');
        $form2Val['material_arrangement_file'] = $form_state->getValue('material_arrangement_file');     
        $form2Val['material_name_scheme'] = $form_state->getValue('material_name_scheme');
        $form2Val['material_other_file_type'] = $form_state->getValue('material_other_file_type');
        $form2Val['material_other_file_formats'] = $form_state->getValue('material_other_file_formats');
        $form2Val['material_file_formats'] = $form_state->getValue('material_file_formats');
        $form2Val['material_file_types'] = $form_state->getValue('material_file_types');
        
        
        $this->store->set('material_title', $form_state->getValue('material_title'));        
        $this->store->set('material_metadata', $form_state->getValue('material_metadata'));
        $this->store->set('material_metadata_file', $form_state->getValue('material_metadata_file'));
        //$this->store->set('material_preview', $form_state->getValue('material_preview'));        
        $this->store->set('material_scope_content_statement', $form_state->getValue('material_scope_content_statement'));
        $this->store->set('material_file_size_byte', $form_state->getValue('material_file_size_byte'));
        $this->store->set('material_file_number', $form_state->getValue('material_file_number'));
        $this->store->set('material_folder_number', $form_state->getValue('material_folder_number'));
        $this->store->set('material_soft_req', $form_state->getValue('material_soft_req'));
        $this->store->set('material_arrangement', $form_state->getValue('material_arrangement'));
        $this->store->set('material_name_scheme', $form_state->getValue('material_name_scheme'));
        $this->store->set('material_other_file_type', $form_state->getValue('material_other_file_type'));
        $this->store->set('material_other_file_formats', $form_state->getValue('material_other_file_formats'));
        $this->store->set('material_file_formats', $form_state->getValue('material_file_formats'));
        $this->store->set('material_file_types', $form_state->getValue('material_file_types'));        
        $this->store->set('material_bagit_file', $form_state->getValue('material_bagit_file'));
        $this->store->set('material_arrangement_file', $form_state->getValue('material_arrangement_file'));
        $this->store->set('bagit_question', $form_state->getValue('bagit_question'));
        $this->store->set('form2Val', $form2Val);
        
        $DBData = array();
    
        //we have record in the DB, so it will be an DB update
        if(isset($this->dbData["data"]) && !empty($this->dbData["data"])){        
            $DBData = json_decode($this->dbData["data"], TRUE);        
            //if we dont have a key then we creating one
            if(array_key_exists('two', $DBData) == null){
                $DBData["two"] = $form2Val;
            }else {
                //if we have it then it will be a modification
                foreach($DBData as $key => $value){
                    if($key == "two"){
                        $DBData["two"] = $form2Val;
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
            $DBData["two"] = $form2Val;
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
       
        $form_state->setRedirect('oeaw_depagree_three', array('formid' => $this->repoid));
        
    }
    
}
