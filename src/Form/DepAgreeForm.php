<?php

namespace Drupal\oeaw\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class DepAgreeForm extends FormBase{
    
    public function getFormId() {
        return 'depagree_form';
    }
    
    public function buildForm(array $form, FormStateInterface $form_state) {
        
        $form['depositor_agreement_title'] = array(
            '#markup' => '<h1><b>Deposition agreement</b></h1>',
        );
        
        $form['depositor'] = array(
            '#type' => 'fieldset',
            '#title' => t('<h2><b>Depositor</b></h2>'),
            '#collapsible' => TRUE,
            '#collapsed' => FALSE,  
        );
              
        
        $form['depositor']['title'] = array(
            '#type' => 'textfield',
            '#title' => t('Name Title:'),
            '#attributes' => array(
              'class' => array('form-control')
		    ),                     
            '#required' => TRUE,
        );
        
        $form['depositor']['l_name'] = array(
            '#type' => 'textfield',
            '#title' => t('Last Name:'),
            '#required' => TRUE,
        );
        
        $form['depositor']['f_name'] = array(
            '#type' => 'textfield',
            '#title' => t('First Name:'),
            '#required' => TRUE,
        );
        
        $form['depositor']['institution'] = array(
            '#type' => 'textfield',
            '#title' => t('Institution:'),
            '#required' => TRUE,
        );
        
        $form['depositor']['city'] = array(
            '#type' => 'textfield',
            '#title' => t('City:'),
            '#required' => TRUE,
        );
        
        $form['depositor']['address'] = array(
            '#type' => 'textfield',
            '#title' => t('Address:'),
            '#required' => TRUE,
        );
        
        $form['depositor']['zipcode'] = array(
            '#type' => 'textfield',
            '#title' => t('Zipcode:'),
            '#required' => TRUE,
        );
        
        $form['depositor']['email'] = array(
            '#type' => 'email',
            '#title' => t('Email:'),
            '#required' => TRUE,
        );
        
        $form['depositor']['phone'] = array (
            '#type' => 'tel',
            '#title' => t('Phone'),
            '#required' => TRUE,
        );
        /*
        $form['depositor_agreement_title'] = array(
            '#markup' => '',
        );
        */
        
        
        $form['creators_title3'] = array(
            '#markup' => '<br><br>',
        );
        
        $form['material'] = array(
            '#type' => 'fieldset',
            '#title' => t('<h2><b>Description Of Material</b></h2>'),
            '#collapsible' => TRUE,
            '#collapsed' => FALSE,  
        );
        
        $form['material']['acdh_repo_id'] = array(
            '#type' => 'textfield',
            '#title' => t('ACDH-repo ID:'),
            '#required' => TRUE,
            '#default_value' => substr( md5(rand()), 0, 20),
            '#attributes' => array("readonly" => TRUE),
            '#description' => $this->t('string used as an internal identifier for the deposited resources'),
        );
        
        $form['material']['title'] = array(
            '#type' => 'textfield',
            '#title' => t('Title:'),
            '#required' => TRUE,
            '#description' => $this->t(''),
        );
        
        $form['material']['ipr'] = array(
            '#type' => 'textarea',
            '#title' => t('Intellectual Property Rights (IPR):'),
            '#required' => TRUE,
            '#description' => $this->t('Intellectual property rights including, but not limited to copyrights, related (or neighbouring) rights and database rights'),
        );
        
        $form['material']['metadata'] = array(
            '#type' => 'textarea',
            '#title' => t('Metadata:'),
            '#required' => TRUE,
            '#description' => $this->t('is the information that may serve to identify, discover, interpret, manage, and describe content and structure.'),
        );
        
        $form['material']['file'] = array(
            '#type' => 'managed_file',
            '#title' => t('Metadata Resource:'),                
            '#upload_validators' => array(
                'file_validate_extensions' => array('xml doc txt simplified docx pdf jpg png tiff gif bmp'),
             ),
            '#description' => $this->t(''),
        );
        
        $form['material']['preview'] = array(
            '#type' => 'managed_file',
            '#title' => t('Preview:'),
            '#upload_validators' => array(
                'file_validate_extensions' => array('xml doc txt simplified docx pdf jpg png tiff gif bmp'),
             ),
            '#description' => $this->t('A reduced size or length audio and/or visual representation of Content, in the form of one or more images, text files, audio files and/or moving image files.'),
        );    
        
        $form['material']['licence'] = array(
            '#type' => 'select',
            '#default_value' => 'CC-BY',
            '#options' => array(
                'Public Domain Mark' => t('Public Domain Mark'),
                'No Copyright - non commercial re-use only' => t('No Copyright - non commercial re-use only'),
                'No Copyright - other known legal restrictions ' => t('No Copyright - other known legal restrictions '),
                'CC0' => t('CC0'),
                'CC-BY' => t('CC-BY'),
                'CC-BY-SA' => t('CC-BY-SA'),
                'CC-BY-ND' => t('CC-BY-ND'),
                'CC-BY-NC' => t('CC-BY-NC'),
                'CC-BY-NC-SA' => t('CC-BY-NC-SA'),
                'CC-BY-NC-ND' => t('CC-BY-NC-ND'),
                'In Copyright' => t('In Copyright'),
                'In Copyright - Educational Use Permitted' => t('In Copyright - Educational Use Permitted'),
                'In Copyright - EU Orphan Work' => t('In Copyright - EU Orphan Work'),
                'Copyright Not Evaluated' => t('Copyright Not Evaluated'),                
            ),
            '#title' => t('Licence:'),
            '#required' => TRUE,
            '#description' => $this->t(''),
        );
        
        $form['material']['scope_content_statement'] = array(
            '#type' => 'textarea',
            '#title' => t('Scope and content statement:'),
            '#required' => TRUE,
            '#description' => $this->t('Provide a description of genres, purpose, and content of the resources being deposited.'),
        );
        
        
        
        $form['creators_title3'] = array(
            '#markup' => '<br><br>',
        );
        
        
        $form['extent'] = array(
            '#type' => 'fieldset',
            '#title' => t('<h2><b>Extent</b></h2>'),
            '#collapsible' => TRUE,
            '#collapsed' => FALSE,  
        );
              
        $form['extent']['file_size_byte'] = array(
            '#type' => 'textfield',
            '#title' => t('Overall file size in bytes:'),
            '#required' => TRUE,
            '#description' => $this->t(''),
        );
        
        $form['extent']['file_number'] = array(
            '#type' => 'textfield',
            '#title' => t('Number of files:'),
            '#required' => TRUE,
            '#description' => $this->t(''),
        );
        
        $form['extent']['folder_number'] = array(
            '#type' => 'textfield',
            '#title' => t('Number of folders:'),
            '#required' => TRUE,
            '#description' => $this->t(''),
        );
        
        
        $fileTypes = array();
        $fileTypes["1"] = "One";
        $fileTypes["2"] = "Two";
        $fileTypes["3"] = "Three";
        $fileTypes["4"] = "Four";
        
        $form['file_types'] = array(
            '#type' => 'checkboxes',
            '#title' => t('List of file types included:'),
            '#required' => TRUE,
            '#options' => $fileTypes,
            '#description' => $this->t(''),
        );
        
        $fileFormats = array();
        $fileFormats["1"] = "One";
        $fileFormats["2"] = "Two";
        $fileFormats["3"] = "Three";
        $fileFormats["4"] = "Four";
        
        $form['file_formats'] = array(
            '#type' => 'checkboxes',
            '#title' => t('List of file formats included:'),
            '#required' => TRUE,
            '#options' => $fileFormats,
            '#description' => $this->t(''),
        );
        
       
        $form['creators_title2'] = array(
            '#markup' => '<br><br>',
        );
        
        $form['creators'] = array(
            '#type' => 'fieldset',
            '#title' => t('<h2><b>Creators</b></h2>'),
            '#collapsible' => TRUE,
            '#collapsed' => FALSE,  
        );       
     
        
        $form['creators']['title'] = array(
            '#type' => 'textfield',
            '#title' => t('Name Title:'),
            '#required' => TRUE,
        );
        
        $form['creators']['l_name'] = array(
            '#type' => 'textfield',
            '#title' => t('Last Name:'),
            '#required' => TRUE,
        );
        
        $form['creators']['f_name'] = array(
            '#type' => 'textfield',
            '#title' => t('First Name:'),
            '#required' => TRUE,
        );
        
        $form['creators']['institution'] = array(
            '#type' => 'textfield',
            '#title' => t('Institution:'),
            '#required' => TRUE,
        );
        
        $form['creators']['city'] = array(
            '#type' => 'textfield',
            '#title' => t('City:'),
            '#required' => TRUE,
        );
        
        $form['creators']['address'] = array(
            '#type' => 'textfield',
            '#title' => t('Address:'),
            '#required' => TRUE,
        );
        
        $form['creators']['zipcode'] = array(
            '#type' => 'textfield',
            '#title' => t('Zipcode:'),
            '#required' => TRUE,
        );
        
        $form['creators']['email'] = array(
            '#type' => 'email',
            '#title' => t('Email:'),
            '#required' => TRUE,
        );
        
        $form['creators']['phone'] = array (
            '#type' => 'tel',
            '#title' => t('Phone'),
            '#required' => TRUE,
        );
        
         $form['creators_add'] = array(
            '#markup' => '<a href="#">Add more creators</a>',
        );
         
        
         
        $form['creators_title2'] = array(
            '#markup' => '<br><br>',
        );
         
        $form['candidate_confirmation'] = array (
            '#type' => 'radios',
            '#required' => TRUE,
            '#title' => ('I read and agree the ....'),
            '#options' => array(
                'Yes' =>t('Yes'),
                'No' =>t('No')
            ),
        );
        
       
        
        
        $form['actions']['#type'] = 'actions';
        $form['actions']['submit'] = array(
            '#type' => 'submit',
            '#value' => $this->t('Save'),
            '#button_type' => 'primary',
        );
        
        return $form;
  }
  
   public function submitForm(array &$form, FormStateInterface $form_state) {
   // drupal_set_message($this->t('@can_name ,Your application is being submitted!', array('@can_name' => $form_state->getValue('candidate_name'))));
    foreach ($form_state->getValues() as $key => $value) {
      drupal_set_message($key . ': ' . $value);
    }
   }
    
}
