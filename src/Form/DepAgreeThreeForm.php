<?php

namespace Drupal\oeaw\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class DepAgreeThreeForm extends DepAgreeBaseForm{
    
    public function getFormId() {
        return 'depagree_form';
    }
    
    public function buildForm(array $form, FormStateInterface $form_state) {
        
        $form = parent::buildForm($form, $form_state);
       
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
            '#default_value' => $this->store->get('folder_name') ? $this->store->get('folder_name') : '',
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
            '#options' => \Drupal\oeaw\ConnData::getTransferMedium(),
            '#description' => $this->t('e.g. hard drive, CD, DVD, USB stick, network transfer'),    
            '#required' => TRUE,
            '#default_value' => $this->store->get('transfer_method') ? $this->store->get('transfer_method') : '',
        );
        
        
        $form['transfer']['data_validation'] = array(
            '#type' => 'radios',
            '#title' => t('Data Validation:'),
            '#options' => \Drupal\oeaw\ConnData::getDataValidation(),
            '#description' => $this->t(''),
            '#required' => TRUE,
            '#default_value' => $this->store->get('data_validation') ? $this->store->get('data_validation') : '',
        );
       
        $form['actions']['previous'] = array(
            '#type' => 'link',
            '#title' => $this->t('Previous'),
            '#attributes' => array(
                'class' => array('button'),
            ),
            '#weight' => 0,
            '#url' => Url::fromRoute('oeaw_depagree_two'),
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
    $form_state->setRedirect('oeaw_depagree_four');
   }
    
}
