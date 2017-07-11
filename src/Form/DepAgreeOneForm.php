<?php

namespace Drupal\oeaw\Form;


use Drupal\Core\Form\FormStateInterface;

class DepAgreeOneForm extends DepAgreeBaseForm{
    
    public function getFormId() {
        return 'depagree_form';
    }
    
    
    
    public function buildForm(array $form, FormStateInterface $form_state) {
        
        $form = parent::buildForm($form, $form_state);

        if(empty($this->store->get('material_acdh_repo_id'))){
            $this->store->set('material_acdh_repo_id',substr( md5(rand()), 0, 20));
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
            '#attributes' => array(
		        'class' => array('form-control')
			),
            '#required' => TRUE,
            '#default_value' => $this->store->get('l_name') ? $this->store->get('l_name') : '',
        );
        
        $form['depositor']['f_name'] = array(
            '#type' => 'textfield',
            '#title' => t('First Name:'),
            '#attributes' => array(
		        'class' => array('form-control')
			),
            '#required' => TRUE,
            '#default_value' => $this->store->get('f_name') ? $this->store->get('f_name') : '',
        );
        
        $form['depositor']['title'] = array(
            '#type' => 'textfield',
            '#title' => t('Title:'),
            '#attributes' => array(
		        'class' => array('form-control')
			),
            '#required' => FALSE,
            '#default_value' => $this->store->get('title') ? $this->store->get('title') : '',
        );
        
        $form['depositor']['institution'] = array(
            '#type' => 'textfield',
            '#title' => t('Institution:'),
            '#attributes' => array(
		        'class' => array('form-control')
			),
            '#required' => TRUE,
            '#default_value' => $this->store->get('institution') ? $this->store->get('institution') : '',
        );
        
        $form['depositor']['city'] = array(
            '#type' => 'textfield',
            '#title' => t('City:'),
            '#attributes' => array(
		        'class' => array('form-control')
			),
            '#required' => TRUE,
            '#default_value' => $this->store->get('city') ? $this->store->get('city') : '',
        );
        
        $form['depositor']['address'] = array(
            '#type' => 'textfield',
            '#title' => t('Address:'),
            '#attributes' => array(
		        'class' => array('form-control')
			),
            '#required' => TRUE,
            '#default_value' => $this->store->get('address') ? $this->store->get('address') : '',
        );
        
        $form['depositor']['zipcode'] = array(
            '#type' => 'textfield',
            '#title' => t('Zipcode:'),
            '#attributes' => array(
		        'class' => array('form-control')
			),
            '#required' => TRUE,
            '#default_value' => $this->store->get('zipcode') ? $this->store->get('zipcode') : '',
        );
        
        $form['depositor']['email'] = array(
            '#type' => 'email',
            '#title' => t('Email:'),
            '#attributes' => array(
		        'class' => array('form-control')
			),
            '#required' => TRUE,
            '#default_value' => $this->store->get('email') ? $this->store->get('email') : '',
        );
        
        $form['depositor']['phone'] = array (
            '#type' => 'tel',
            '#title' => t('Phone'),
            '#attributes' => array(
		        'class' => array('form-control')
			),
            '#required' => TRUE,
            '#default_value' => $this->store->get('phone') ? $this->store->get('phone') : '',
        );
       
        
        //create the next button to the form second page
        $form['actions']['#type'] = 'actions';
        $form['actions']['submit'] = array(
          '#type' => 'submit',
          '#value' => $this->t('Next'),
          '#attributes' => array(
            'class' => array('btn')
		  ),                   
          '#button_type' => 'primary',
        );

        
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
    $asd = $this->store->set('form1Val', $form1Val);
    
    $this->store->set('title', $form_state->getValue('title'));
    $this->store->set('l_name', $form_state->getValue('l_name'));
    $this->store->set('f_name', $form_state->getValue('f_name'));
    $this->store->set('institution', $form_state->getValue('institution'));
    $this->store->set('city', $form_state->getValue('city'));
    $this->store->set('address', $form_state->getValue('address'));
    $this->store->set('zipcode', $form_state->getValue('zipcode'));
    $this->store->set('email', $form_state->getValue('email'));
    $this->store->set('phone', $form_state->getValue('phone'));
        
    $form_state->setRedirect('oeaw_depagree_two');
   }
    
}
