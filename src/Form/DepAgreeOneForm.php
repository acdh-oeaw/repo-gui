<?php

namespace Drupal\oeaw\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

class DepAgreeOneForm extends DepAgreeBaseForm
{
    private $l_name = "";
    private $f_name = "";
    private $title = "";
    private $institution = "";
    private $city = "";
    private $address = "";
    private $zipcode = "";
    private $email = "";
    private $phone = "";
    
    public function getFormId()
    {
        return 'depagree_form';
    }
    
    private function setupFields()
    {
        $dataField = array();
        $dataOne = json_decode($this->dbData["data"]);
        if (isset($dataOne->one)) {
            $dataField = $dataOne->one;
        }
        
        if (count($dataField) > 0 && isset($dataField)) {
            if (isset($dataField->l_name)) {
                $this->l_name = $dataField->l_name;
            }
            if (isset($dataField->f_name)) {
                $this->f_name = $dataField->f_name;
            }
            if (isset($dataField->title)) {
                $this->title = $dataField->title;
            }
            if (isset($dataField->institution)) {
                $this->institution = $dataField->institution;
            }
            if (isset($dataField->city)) {
                $this->city = $dataField->city;
            }
            if (isset($dataField->address)) {
                $this->address = $dataField->address;
            }
            if (isset($dataField->zipcode)) {
                $this->zipcode = $dataField->zipcode;
            }
            if (isset($dataField->email)) {
                $this->email = $dataField->email;
            }
            if (isset($dataField->phone)) {
                $this->phone = $dataField->phone;
            }
        }
    }
    
    //, AccountInterface $user = NULL
    public function buildForm(array $form, FormStateInterface $form_state, $formid = null)
    {
        $form = parent::buildForm($form, $form_state);
        $repoFormID = \Drupal::routeMatch()->getParameter("formid");
        
        $this->checkRepoId($repoFormID);
                
        if (!empty($this->repoid) && !empty($this->dbData)) {
            if (count($this->dbData) > 0) {
                $this->setupFields();
            }
        }

        $form['depositor'] = array(
            '#type' => 'fieldset',
            '#title' => t('<b>Depositor</b>'),
            '#field_prefix' => 'The Depositor is the person authorised to transfer and deposit digital resources.',
        );
        
        $form['depositor']['l_name'] = array(
            '#type' => 'textfield',
            '#title' => t('Last Name:'),
            '#required' => true,
            '#default_value' => $this->l_name,
        );
        
        $form['depositor']['f_name'] = array(
            '#type' => 'textfield',
            '#title' => t('First Name:'),
            '#required' => true,
            '#default_value' => $this->f_name,
        );
        
        $form['depositor']['title'] = array(
            '#type' => 'textfield',
            '#title' => t('Title:'),
            '#required' => false,
            '#default_value' => $this->title,
        );
        
        $form['depositor']['institution'] = array(
            '#type' => 'textfield',
            '#title' => t('Institution:'),
            '#required' => true,
            '#default_value' => $this->institution,
        );
        
        $form['depositor']['city'] = array(
            '#type' => 'textfield',
            '#title' => t('City:'),
            '#required' => true,
            '#default_value' => $this->city,
        );
        
        $form['depositor']['address'] = array(
            '#type' => 'textfield',
            '#title' => t('Address:'),
            '#required' => true,
            '#default_value' => $this->address,
        );
        
        $form['depositor']['zipcode'] = array(
            '#type' => 'textfield',
            '#title' => t('Zipcode:'),
            '#required' => true,
            '#default_value' => $this->zipcode,
        );
        
        $form['depositor']['phone'] = array(
            '#type' => 'tel',
            '#title' => t('Phone:'),
            '#required' => true,
            '#default_value' => $this->phone,
        );
        
        $form['depositor']['email'] = array(
            '#type' => 'email',
            '#title' => t('E-mail:'),
            '#required' => true,
            '#default_value' => $this->email,
        );
        
        //create the next button to the form second page
        $form['actions']['submit']['#value'] = $this->t('Next');
        
        return $form;
    }
  
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
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
    
        $DBData = array();
    
        //we have record in the DB, so it will be an DB update
        if (isset($this->dbData["data"]) && !empty($this->dbData["data"])) {
            $DBData = json_decode($this->dbData["data"], true);
            //if we dont have a key then we creating one
            if (array_key_exists('one', $DBData) == null) {
                $DBData["one"] = $form1Val;
            } else {
                //if we have it then it will be a modification
                foreach ($DBData as $key => $value) {
                    if ($key == "one") {
                        $DBData["one"] = $form1Val;
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
        } else {
            $DBData["one"] = $form1Val;
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
            
        $form_state->setRedirect('oeaw_depagree_two', array('formid' => $this->repoid));
    }
}
