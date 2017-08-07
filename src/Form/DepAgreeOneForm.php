<?php

namespace Drupal\oeaw\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

class DepAgreeOneForm extends DepAgreeBaseForm{
    
    private $formData = array();
    
    public function getFormId() {
        return 'depagree_form';
    }
    
    private function getFormFromDB(string $repoid):array{
        $res = array();
        
        $query = db_select('oeaw_forms', 'of');
        $query->fields('of',array('data'));
        $query->condition('of.userid', \Drupal::currentUser()->id());
        $query->condition('of.repoid', $repoid);        
        $query->condition('of.status', 'open');
        $query->orderBy('of.date', 'DESC');
        $query->range(0, 1);
        $result = $query->execute();
        $result = $result->fetchAssoc();
        
        if($result != false) {
            $res = $result;
        }
        
        return $res;
    }
    //, AccountInterface $user = NULL
    public function buildForm(array $form, FormStateInterface $form_state, $formid = NULL) {
        
        $form = parent::buildForm($form, $form_state);
        
        $l_name = "";
        $f_name = "";
        $title = "";
        $institution = "";
        $city = "";
        $address = "";
        $zipcode = "";
        $email = "";
        $phone = "";

        if(empty($this->store->get('material_acdh_repo_id'))){
            $this->store->set('material_acdh_repo_id',substr( md5(rand()), 0, 20));
        }

        $repoid = $this->store->get('material_acdh_repo_id');
        if(!empty($this->getFormFromDB($repoid))){
            $this->formData = $this->getFormFromDB($repoid);
        }
        
        
        if(count($this->formData) > 0){            
            $dataField = json_decode($this->formData["data"]);
            
            if(count($dataField) > 0 && isset($dataField)){
                if(isset($dataField->l_name)){
                    $l_name = $dataField->l_name;
                }
                if(isset($dataField->f_name)){
                    $f_name = $dataField->f_name;
                }
                if(isset($dataField->title)){
                    $title = $dataField->title;
                }
                if(isset($dataField->institution)){
                    $institution = $dataField->institution;
                }
                if(isset($dataField->city)){
                    $city = $dataField->city;
                }
                if(isset($dataField->address)){
                    $address = $dataField->address;
                }
                if(isset($dataField->zipcode)){
                    $zipcode = $dataField->zipcode;
                }
                if(isset($dataField->email)){
                    $email = $dataField->email;
                }
                if(isset($dataField->phone)){
                    $phone = $dataField->phone;
                }
            }
        }
        
        $form['depositor'] = array(
            '#type' => 'fieldset',
            '#title' => t('<b>Depositor</b>'),
            '#collapsible' => TRUE,
            '#collapsed' => FALSE,  
        );
        
        $form['depositor']['l_name'] = array(
            '#type' => 'textfield',
            '#title' => t('Last Name:'),
            '#required' => TRUE,
            '#default_value' => $l_name,
        );
        
        $form['depositor']['f_name'] = array(
            '#type' => 'textfield',
            '#title' => t('First Name:'),
            '#required' => TRUE,
            '#default_value' => $f_name,
        );
        
        $form['depositor']['title'] = array(
            '#type' => 'textfield',
            '#title' => t('Title:'),
            '#required' => FALSE,
            '#default_value' => $title,
        );
        
        $form['depositor']['institution'] = array(
            '#type' => 'textfield',
            '#title' => t('Institution:'),
            '#required' => TRUE,
            '#default_value' => $institution,
        );
        
        $form['depositor']['city'] = array(
            '#type' => 'textfield',
            '#title' => t('City:'),
            '#required' => TRUE,
            '#default_value' => $city,
        );
        
        $form['depositor']['address'] = array(
            '#type' => 'textfield',
            '#title' => t('Address:'),
            '#required' => TRUE,
            '#default_value' => $address,
        );
        
        $form['depositor']['zipcode'] = array(
            '#type' => 'textfield',
            '#title' => t('Zipcode:'),
            '#required' => TRUE,
            '#default_value' => $zipcode,
        );
        
        $form['depositor']['email'] = array(
            '#type' => 'email',
            '#title' => t('Email:'),
            '#required' => TRUE,
            '#default_value' => $email,
        );
        
        $form['depositor']['phone'] = array (
            '#type' => 'tel',
            '#title' => t('Phone'),
            '#required' => TRUE,
            '#default_value' => $phone,
        );
        
        //create the next button to the form second page
        $form['actions']['submit']['#value'] = $this->t('Next');
        
        return $form;
  }
  
   public function submitForm(array &$form, FormStateInterface $form_state) {
   // drupal_set_message($this->t('@can_name ,Your application is being submitted!', array('@can_name' => $form_state->getValue('candidate_name'))));
    
    $form1Val = array();
    //get the class and root values from the form    
    $form1Val['l_name'] = $form_state->getValue('l_name');
    $form1Val['f_name'] = $form_state->getValue('f_name');
    $form1Val['title'] = $form_state->getValue('title');
    $form1Val['institution'] = $form_state->getValue('institution');
    $form1Val['city'] = $form_state->getValue('city');
    $form1Val['address'] = $form_state->getValue('address');
    $form1Val['zipcode'] = $form_state->getValue('zipcode');
    $form1Val['email'] = $form_state->getValue('email');
    $form1Val['phone'] = $form_state->getValue('phone');
    $this->store->set('form1Val', $form1Val);
    
    $this->store->set('title', $form_state->getValue('title'));
    $this->store->set('l_name', $form_state->getValue('l_name'));
    $this->store->set('f_name', $form_state->getValue('f_name'));
    $this->store->set('institution', $form_state->getValue('institution'));
    $this->store->set('city', $form_state->getValue('city'));
    $this->store->set('address', $form_state->getValue('address'));
    $this->store->set('zipcode', $form_state->getValue('zipcode'));
    $this->store->set('email', $form_state->getValue('email'));
    $this->store->set('phone', $form_state->getValue('phone'));
        
    $json_obj = json_encode($form1Val);
    
    if(count($this->formData) > 0){
        
        $num_updated = db_update('oeaw_forms')
            ->fields(array(
                    'data' => $json_obj,
                    'date'=>  date("d-m-Y H:i:s")
            ))
            ->condition('userid', \Drupal::currentUser()->id(), '=')
            ->condition('repoid', $this->store->get('material_acdh_repo_id'), '=')
            ->execute();
        
    }else {
        
        $field = array(
            'userid' => \Drupal::currentUser()->id(),
            'repoid' => $this->store->get('material_acdh_repo_id'),
            'form' =>  'depagree_form_one',
            'data' => $json_obj,
            'status' =>  'open',
            'date'=>  date("d-m-Y H:i:s")
        );
        db_insert('oeaw_forms')
            ->fields($field)
            ->execute();
    }
    
        
    $form_state->setRedirect('oeaw_depagree_two', array('formid' => $this->store->get('material_acdh_repo_id')));
   }
    
}
