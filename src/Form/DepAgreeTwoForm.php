<?php

namespace Drupal\oeaw\Form;


use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class DepAgreeTwoForm extends DepAgreeBaseForm{
    
    public function getFormId() {
        return 'depagree_form';
    }
    
    public function buildForm(array $form, FormStateInterface $form_state) {
        
        $form_state->disableCache();
        $form = parent::buildForm($form, $form_state);
     
        /*************** DESCRIPTION OF MATERIAL START ************************/
        
        $form['material'] = array(
            '#type' => 'fieldset',
            '#title' => t('<b>Description Of Material</b>'),
            '#collapsible' => TRUE,
            '#collapsed' => FALSE,  
        );
        
        $form['material']['material_acdh_repo_id'] = array(
            '#type' => 'textfield',
            '#title' => t('ACDH-repo ID:'),            
            '#required' => TRUE,
            '#default_value' => $this->store->get('material_acdh_repo_id'),
            '#attributes' => array("readonly" => TRUE),
            '#description' => $this->t('string used as an internal identifier for the deposited resources'),
        );
        /*
        $form['material']['material_preview'] = array(
            '#type' => 'managed_file',
            '#title' => t('Preview:'),
            '#upload_location' => 'public://'.$this->store->get('material_acdh_repo_id').'/',
            '#default_value' => $this->store->get('material_preview') ? $this->store->get('material_preview') : '',
            '#upload_validators' => array(
                'file_validate_extensions' => array('xml doc txt simplified docx pdf jpg png tiff gif bmp'),
             ),
            '#description' => $this->t('A reduced size or length audio and/or visual representation of Content, in the form of one or more images, text files, audio files and/or moving image files.'),
            '#required' => TRUE,            
        );
        */
        $form['material']['material_title'] = array(
            '#type' => 'textfield',
            '#title' => t('Title:'),
            '#required' => TRUE,
            '#default_value' => $this->store->get('material_title') ? $this->store->get('material_title') : '',
            '#description' => $this->t(''),
        );
        
        $form['material']['material_scope_content_statement'] = array(
            '#type' => 'textarea',
            '#title' => t('Scope and content statement:'),
            '#required' => TRUE,
            '#default_value' => $this->store->get('material_scope_content_statement') ? $this->store->get('material_scope_content_statement') : '',
            '#description' => $this->t('Provide a description of genres, purpose, and content of the resources being deposited.'),
        );
        
        $form['material']['material_metadata'] = array(
            '#type' => 'textarea',
            '#title' => t('Metadata:'),
            '#required' => TRUE,
            '#default_value' => $this->store->get('material_metadata') ? $this->store->get('material_metadata') : '',
            '#description' => $this->t('is the information that may serve to identify, discover, interpret, manage, and describe content and structure.'),
        );
        
        $form['material']['material_metadata_file'] = array(
            '#type' => 'managed_file',
            '#title' => t('Metadata Resource:'),
            '#upload_location' => 'public://'.$this->store->get('material_acdh_repo_id').'/',
            '#default_value' => $this->store->get('material_metadata_file') ? $this->store->get('material_metadata_file') : '',
            '#upload_validators' => array(
                'file_validate_extensions' => array('xml doc txt simplified docx pdf jpg png tiff gif bmp'),
             ),
            '#description' => $this->t(''),
            '#required' => FALSE,            
        );
        
        $form['material']['material_mat_licence'] = array(
            '#type' => 'select',            
            '#options' => \Drupal\oeaw\DepAgreeConstants::getMaterialLicences(),            
            '#title' => t('Licence:'),
            '#required' => TRUE,
            '#default_value' => $this->store->get('material_mat_licence') ? $this->store->get('material_mat_licence') : 'CC-BY',
            '#description' => $this->t(''),
        );
                
        $form['material']['material_ipr'] = array(
            '#type' => 'textarea',
            '#title' => t('Intellectual Property Rights (IPR):'),
            '#required' => TRUE,
            '#default_value' => $this->store->get('material_ipr') ? $this->store->get('material_ipr') : '',
            '#description' => $this->t('Intellectual property rights including, but not limited to copyrights, related (or neighbouring) rights and database rights'),
        );
        
        /*************** DESCRIPTION OF MATERIAL END ************************/
        
        /*************** EMBARGO START ************************/
        
        $form['embargo'] = array(
            '#type' => 'fieldset',
            '#title' => t('<b>Embargo</b>'),
            '#collapsible' => TRUE,
            '#collapsed' => FALSE,  
        );
       
        
        $form['embargo']['embargo_question'] = array(
            '#type' => 'radios',
            '#title' => t('List of file formats included:'),
            '#required' => TRUE,
            '#default_value' => $this->store->get('embargo_question') ? $this->store->get('embargo_question') : 0,
            '#options' => array(0 => $this->t('No'), 1 => $this->t('Yes')),
            '#description' => $this->t(''),
        );
        
        
        $form['embargo']['embargo_date'] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => 'embargo_date',
          ],
          '#states' => [
            'invisible' => [
              'input[name="embargo_question"]' => ['value' => 0],
            ],
          ],
        ];
        
        $form['embargo']['embargo_date']['embargo_date'] = array(
            '#type' => 'date',
            '#title' => t('Embargo Date:'),          
            '#default_value' => $this->store->get('embargo_date') ? $this->store->get('embargo_date') : "",
            '#date_label_position' => 'within', // See other available attributes and what they do in date_api_elements.inc            
            '#date_increment' => 15, // Optional, used by the date_select and date_popup elements to increment minutes and seconds.
            '#date_year_range' => '-3:+3', // Optional, used to set the year range (back 3 years and forward 3 years is the default).
        );
        
        
        
        /*************** EMBARGO end ************************/
        
        /*************** Dissemination material start ************************/
        
        $form['diss_material'] = array(
            '#type' => 'fieldset',
            '#title' => t('<b>Dissemination Material</b>'),
            '#collapsible' => TRUE,
            '#collapsed' => FALSE,  
        );
        
        $form['diss_material']['diss_material_title'] = array(
            '#type' => 'managed_file',
            '#title' => t('Title image:'),
            '#upload_location' => 'public://'.$this->store->get('material_acdh_repo_id').'/',
            '#default_value' => $this->store->get('diss_material_title') ? $this->store->get('diss_material_title') : '',
            '#upload_validators' => array(
                'file_validate_extensions' => array('xml doc txt simplified docx pdf jpg png tiff gif bmp'),
             ),
            '#description' => $this->t('Please provide an image to be used for resource presentation '),
            '#required' => TRUE,            
        );
        
        $form['diss_material']['diss_material_sub_images'] = array(
            '#type' => 'managed_file',
            '#title' => t('Subordinate images:'),
            '#upload_location' => 'public://'.$this->store->get('material_acdh_repo_id').'/',
            '#default_value' => $this->store->get('diss_material_sub_images') ? $this->store->get('diss_material_sub_images') : '',
            '#upload_validators' => array(
                'file_validate_extensions' => array('xml doc txt simplified docx pdf jpg png tiff gif bmp'),
             ),
            '#description' => $this->t('Please provide images to be used for presentation of subordinated resources (if any)'),
            '#required' => FALSE,            
        );
        
        $form['diss_material']['diss_material_logos'] = array(
            '#type' => 'managed_file',
            '#title' => t('Logos:'),
            '#upload_location' => 'public://'.$this->store->get('material_acdh_repo_id').'/',
            '#default_value' => $this->store->get('diss_material_logos') ? $this->store->get('diss_material_logos') : '',
            '#upload_validators' => array(
                'file_validate_extensions' => array('xml doc txt simplified docx pdf jpg png tiff gif bmp'),
             ),
            '#description' => $this->t('Please provide logos to be displayed along the resources'),
            '#required' => FALSE,            
        );
        
        /*************** Dissemination material end ************************/
        
        /*************** EXTENT START ************************/
        $form['extent'] = array(
            '#type' => 'fieldset',
            '#title' => t('<b>Extent</b>'),
            '#collapsible' => TRUE,
            '#collapsed' => FALSE,  
        );
        
        
        $form['extent']['bagit_question'] = array(
            '#type' => 'radios',
            '#title' => t('Do you have a bagit file?'),
            '#required' => TRUE,
            '#default_value' => $this->store->get('bagit_question') ? $this->store->get('bagit_question') : 0,
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
            '#upload_location' => 'public://'.$this->store->get('material_acdh_repo_id').'/',
            '#default_value' => $this->store->get('material_bagit_file') ? $this->store->get('material_bagit_file') : '',
            '#upload_validators' => array(
                'file_validate_extensions' =>array('xml doc txt simplified docx pdf jpg png tiff gif bmp'),
             ),
            '#description' => $this->t('Please provide logos to be displayed along the resources'),
            '#required' => FALSE,                
        );
                
        $form['extent']['bagit_container_fields']['material_file_size_byte'] = array(
            '#type' => 'number',
            '#min' => 0,
            '#title' => t('Overall file size in bytes:'),
            '#required' => FALSE,
            '#default_value' => $this->store->get('material_file_size_byte') ? $this->store->get('material_file_size_byte') : '',
            '#description' => $this->t(''),
        );

        $form['extent']['bagit_container_fields']['material_file_number'] = array(
            '#type' => 'number',
            '#title' => t('Number of files:'),
            '#min' => 0,
            '#required' => FALSE,
            '#default_value' => $this->store->get('material_file_number') ? $this->store->get('material_file_number') : '',
            '#description' => $this->t(''),
        );

        $form['extent']['bagit_container_fields']['material_folder_number'] = array(
            '#type' => 'number',
            '#min' => 0,
            '#title' => t('Number of folders:'),
            '#required' => FALSE,
            '#default_value' => $this->store->get('material_folder_number') ? $this->store->get('material_folder_number') : '',
            '#description' => $this->t(''),
        );
                
        $form['extent']['material_soft_req'] = array(
            '#type' => 'textfield',
            '#title' => t('Software requirements:'),            
            '#description' => $this->t('list any software programs formats that are not typically used in a standard office environment, that are required to access content being transferred'),
            '#required' => TRUE,
            '#default_value' => $this->store->get('material_soft_req') ? $this->store->get('material_soft_req') : '',
        );
        
        $form['extent']['material_arrangement'] = array(
            '#type' => 'textarea',
            '#title' => t('Arrangement:'),    
            '#required' => TRUE,
            '#default_value' => $this->store->get('material_arrangement') ? $this->store->get('material_arrangement') : '',
            '#description' => $this->t('The aim is to give a logical and coherent overall view of the whole set of objects, describe folder structure, nature of relationship between objects and metadata, etc.  If necessary, attach diagrams or screenshots from the original system'),
        );
        
        $form['extent']['material_arrangement_file'] = array(
            '#type' => 'managed_file',
            '#title' => t('Arrangement File:'),
            '#upload_location' => 'public://'.$this->store->get('material_acdh_repo_id').'/',
            '#upload_validators' => array(
                'file_validate_extensions' => array('xml doc txt simplified docx pdf jpg png tiff gif bmp'),
             ),            
            '#required' => FALSE,
            '#default_value' => $this->store->get('material_arrangement_file') ? $this->store->get('material_arrangement_file') : '',
        );
        
        
        $form['extent']['material_name_scheme'] = array(
            '#type' => 'managed_file',
            '#title' => t('Naming scheme:'),
            '#upload_location' => 'public://'.$this->store->get('material_acdh_repo_id').'/',
            '#upload_validators' => array(
                'file_validate_extensions' => array('xml doc txt simplified docx pdf jpg png tiff gif bmp'),
             ),
            '#description' => $this->t('Provide if one exists'),
            '#required' => TRUE,
            '#default_value' => $this->store->get('material_name_scheme') ? $this->store->get('material_name_scheme') : '',
        );
        
        /*************** EXTENT END ************************/
        
        if($this->store->get('material_file_types')){
            $form['material_file_types']['#default_value'] =  $this->store->get('material_file_types');
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
            '#default_value' => $this->store->get('material_other_file_type') ? $this->store->get('material_other_file_type') : '',
            '#description' => $this->t('If your file type is not in the list, then please add it here.'),
        );
        
        if($this->store->get('material_file_formats')){
            $form['material_file_formats']['#default_value'] =  $this->store->get('material_file_formats');
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
            '#default_value' => $this->store->get('material_other_file_formats') ? $this->store->get('material_other_file_formats') : '',
            '#description' => $this->t('If your file format is not in the list, then please add it here.'),
            '#state' => 'hidden'
        );
        
        $form['actions']['previous'] = array(
            '#type' => 'link',
            '#title' => $this->t('Previous'),
            '#attributes' => array(
                'class' => array('btn'),
                'style' => 'margin:10px; color:white;'
            ),
            '#weight' => 0,
            '#url' => Url::fromRoute('oeaw_depagree_one'),
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
        $form2Val['material_mat_licence'] = $form_state->getValue('material_mat_licence');
        $form2Val['material_ipr'] = $form_state->getValue('material_ipr');
        $form2Val['embargo_question'] = $form_state->getValue('embargo_question');
        $form2Val['embargo_date'] = $form_state->getValue('embargo_date');
        $form2Val['diss_material_title'] = $form_state->getValue('diss_material_title');
        $form2Val['diss_material_sub_images'] = $form_state->getValue('diss_material_sub_images');
        $form2Val['diss_material_logos'] = $form_state->getValue('diss_material_logos');
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
        $this->store->set('material_ipr', $form_state->getValue('material_ipr'));
        $this->store->set('material_metadata', $form_state->getValue('material_metadata'));
        $this->store->set('material_metadata_file', $form_state->getValue('material_metadata_file'));
        //$this->store->set('material_preview', $form_state->getValue('material_preview'));
        $this->store->set('material_mat_licence', $form_state->getValue('material_mat_licence'));
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
        $this->store->set('diss_material_logos', $form_state->getValue('diss_material_logos'));
        $this->store->set('diss_material_sub_images', $form_state->getValue('diss_material_sub_images'));
        $this->store->set('diss_material_title', $form_state->getValue('diss_material_title'));
        $this->store->set('embargo_date', $form_state->getValue('embargo_date'));
        $this->store->set('embargo_question', $form_state->getValue('embargo_question'));
        $this->store->set('material_bagit_file', $form_state->getValue('material_bagit_file'));
        $this->store->set('material_arrangement_file', $form_state->getValue('material_arrangement_file'));
        $this->store->set('bagit_question', $form_state->getValue('bagit_question'));
     
        $this->store->set('form2Val', $form2Val);        
        
        $form_state->setRedirect('oeaw_depagree_three');
    }
    
}
