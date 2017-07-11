<?php

namespace Drupal\oeaw\Form;

use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class NewResourceOneForm extends NewResourceFormBase {

    /* 
     *
     * drupal core formid
     *     
     * @return string : form id
    */
    public function getFormId() {
        return 'newresource_form_one';
    }
    
    /* 
     *
     * drupal core buildForm function, to create the form what the user will see
     *
     * @param array $form : it will contains the form elements
     * @param FormStateInterface $form_state : form object
     *
     * @return void
    */
    public function buildForm(array $form, FormStateInterface $form_state) {
                
        $roots = array();
        $rootSelect = array();
        // get the root resources to we can show it on the select element
        $roots = $this->OeawStorage->getRootFromDB();
        
        if(count($roots) > 0 ){
            //create the root option values
            foreach($roots as $r){
                $rootSelect[$r["uri"]] = t($r["title"]);
            }
        }else{
            drupal_set_message($this->t('There is no root element!'), 'error');                
            return false;
        }       
        
        $classes = array();
        //get the class resources to we can show it on the select element        
        $classes = $this->OeawStorage->getClass();
        
        
        $form = parent::buildForm($form, $form_state);
        // we need to add this attribute because of the file uploading
        $form['#attributes']['enctype'] = "multipart/form-data";
        
        
        
        // create the root form element with the values        
        $form["roots"] = array(
            "#type" => "select",
            "#title" => t("SELECT YOUR ROOT ELEMENT"),
            '#required' => TRUE,
            '#attributes' => array(
		        'class' => array('form-control')
			),
            "#options" =>
            $rootSelect,
            '#default_value' => $this->store->get('roots') ? $this->store->get('roots') : '',
        );
        
        if(count($classes) > 0){
            foreach($classes as $c){
                $classesSelect[$c["uri"]] = t($c["title"]);
            }
        }else {
            drupal_set_message($this->t('There is no class element!'), 'error');    
            return;
        }
        
        //create the class form element with the values
        $form['class'] = array(
            '#type' => 'select',
            '#title' => $this->t('SELECT YOUR CLASS'),
            '#attributes' => array(
		        'class' => array('form-control')
			),            
            '#required' => TRUE,
            "#options" =>
            $classesSelect,
            '#default_value' => $this->store->get('class') ? $this->store->get('class') : '',
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
        $form1Elements = array();
        //get the class and root values from the form
        $form1Elements['root'] = $form_state->getValue('roots');
        $form1Elements['class'] = $form_state->getValue('class');
        
        //store the required data from this page to the saving page
        $this->store->set('form1Elements', $form1Elements);
        $this->store->set('roots', $form_state->getValue('roots'));
        $this->store->set('class', $form_state->getValue('class'));
        //go to the next page
        $form_state->setRedirect('oeaw_newresource_two');
    }

}
