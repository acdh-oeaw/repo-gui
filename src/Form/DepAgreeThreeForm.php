<?php

namespace Drupal\oeaw\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class DepAgreeThreeForm extends DepAgreeBaseForm
{
    private $material_mat_licence = "";
    private $folder_name = "";
    private $transfer_method = "";
    private $integrity_checks = "";
    private $access_mode = "";
    private $material_ipr = "";
    private $embargo_question = "";
    private $embargo_date = "";
    private $diss_material_title = "";
    private $transfer_method_url = "";
    private $transfer_method_link_url = "";
    private $diss_material_logos = array();
    private $accmode_l_name = "";
    private $accmode_f_name = "";
    private $accmode_title = "";
    private $accmode_city  = "";
    private $accmode_address  = "";
    private $accmode_zipcode  = "";
    private $accmode_phone  = "";
    private $accmode_email  = "";
            
    public function getFormId()
    {
        return 'depagree_form';
    }
    
    private function setupFields()
    {
        $dataField = array();
        $dataThree = json_decode($this->dbData["data"]);
        
        if (isset($dataThree->three)) {
            $dataField = $dataThree->three;
        }

        if (count($dataField) > 0 && isset($dataField)) {
            if (isset($dataField->accmode_l_name)) {
                $this->accmode_l_name = $dataField->accmode_l_name;
            }
            if (isset($dataField->accmode_f_name)) {
                $this->accmode_f_name = $dataField->accmode_f_name;
            }
            if (isset($dataField->accmode_title)) {
                $this->accmode_title = $dataField->accmode_title;
            }
            if (isset($dataField->accmode_city)) {
                $this->accmode_city = $dataField->accmode_city;
            }
            if (isset($dataField->accmode_address)) {
                $this->accmode_address = $dataField->accmode_address;
            }
            if (isset($dataField->accmode_zipcode)) {
                $this->accmode_zipcode = $dataField->accmode_zipcode;
            }
            if (isset($dataField->accmode_phone)) {
                $this->accmode_phone = $dataField->accmode_phone;
            }
            if (isset($dataField->accmode_email)) {
                $this->accmode_email = $dataField->accmode_email;
            }
            if (isset($dataField->embargo_question)) {
                $this->embargo_question = $dataField->embargo_question;
            }
            if (isset($dataField->embargo_date)) {
                $this->embargo_date = $dataField->embargo_date;
            }
            if (isset($dataField->material_mat_licence)) {
                $this->material_mat_licence = $dataField->material_mat_licence;
            }
            if (isset($dataField->folder_name)) {
                $this->folder_name = $dataField->folder_name;
            }
            if (isset($dataField->transfer_method)) {
                $this->transfer_method = $dataField->transfer_method;
            }
            if (isset($dataField->integrity_checks)) {
                $this->integrity_checks = $dataField->integrity_checks;
            }
            if (isset($dataField->access_mode)) {
                $this->access_mode = $dataField->access_mode;
            }
            if (isset($dataField->material_ipr)) {
                $this->material_ipr = $dataField->material_ipr;
            }
            if (isset($dataField->diss_material_title)) {
                $this->diss_material_title = $dataField->diss_material_title;
            }
            if (isset($dataField->diss_material_logos)) {
                $this->diss_material_logos = $dataField->diss_material_logos;
            }
            if (isset($dataField->transfer_method_url)) {
                $this->transfer_method_url = $dataField->transfer_method_url;
            }
            if (isset($dataField->transfer_method_link_url)) {
                $this->transfer_method_link_url = $dataField->transfer_method_link_url;
            }
        }
    }
    
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
        
        /**** TRANSFER PROCEDURES ***/
        $this->getTransferProcedures($form);
        
        /*************** EMBARGO ************************/
        $this->getEmbargo($form);
                
        /*********************** ACCESS MODE ********************************/
        
        $this->getAccessMode($form, $form_state);
        
        
        $form['transfer']['integrity_checks'] = array(
            '#type' => 'radios',
            '#title' => t('Integrity Checks:'),
            '#options' => \Drupal\oeaw\DepAgreeConstants::getDataValidation(),
            '#description' => $this->t(''),
            '#required' => false,
            '#default_value' => $this->integrity_checks,
        );
        
        /*************** Dissemination materials ************************/
        
        $this->getDisseminationMaterials($form);
        
        /**************** CREATORS *******************************************/
        
        $max = $form_state->get('fields_count');
        if (is_null($max)) {
            $max = 0;
            $form_state->set('fields_count', $max);
        }
        
        $this->getCreators($form, $max);
        
        
        $form['fields_count_value'] = array(
            '#type' => 'hidden',
            '#name' => 'fields_count_value',
            '#value' => $form_state->get('fields_count')
        );
        
        $form['actions']['previous'] = array(
            '#type' => 'link',
            '#title' => $this->t('Previous'),
            '#attributes' => array(
                'class' => array('btn'),
                'style' => 'margin:5px; background-color:#88DBDF;'
            ),
            '#weight' => 0,
            '#url' => Url::fromRoute('oeaw_depagree_two', array('formid' => $this->repoid))
        );
        
        return $form;
    }
    
    public function getCreators(&$form, $max)
    {
        $form['fields']['creators_title'] = [
            '#type' => 'checkbox',
            '#title' => t('Show / Hide Creators Form'),
            '#options' => [
                '1' => t('Show/Hide')
             ],
        ];

        $form['fields']['creators'] = array(
            '#type' => 'fieldset',
            '#collapsible' => true,
            '#collapsed' => true,
            '#title' => t('Creators'),
            '#prefix' => '<div id="creators-wrapper">',
            '#suffix' => '</div>',
            '#states' => array(
                'visible' => array(
                    ':input[name="creators_title"]' => array('checked' => true),
                ),
            ),
        );
       
        
        // Add elements that don't already exist
        for ($i=0; $i<=$max; $i++) {
            if (!isset($form['fields']['creators'][$i])) {
                $form['fields']['creators']['creator_l_name_'.$i] = array(
                    '#type' => 'textfield',
                    '#title' => t('Last Name:'),
                    '#required' => false,
                    '#default_value' => $this->store->get('creator_l_name_'.$i) ? $this->store->get('creator_l_name_'.$i) : '',
                    '#prefix' => t('<h2><b>Creator '.($i+1).' data</b></h2>')
                );
                $form['fields']['creators']['creator_f_name_'.$i] = array(
                    '#type' => 'textfield',
                    '#title' => t('First Name:'),
                    '#required' => false,
                    '#default_value' => $this->store->get('creator_f_name_'.$i) ? $this->store->get('creator_f_name_'.$i) : '',
                );
                $form['fields']['creators']['creator_title_'.$i] = array(
                    '#type' => 'textfield',
                    '#title' => t('Title:')
                );
                $form['fields']['creators']['creator_institution_'.$i] = array(
                    '#type' => 'textfield',
                    '#title' => t('Institution:'),
                    '#default_value' => $this->store->get('creator_institution_'.$i) ? $this->store->get('creator_institution_'.$i) : '',
                );
                $form['fields']['creators']['creator_city_'.$i] = array(
                    '#type' => 'textfield',
                    '#title' => t('City:'),
                    '#default_value' => $this->store->get('creator_city_'.$i) ? $this->store->get('creator_city_'.$i) : '',
                );
                $form['fields']['creators']['creator_address_'.$i] = array(
                    '#type' => 'textfield',
                    '#title' => t('Address:'),
                    '#default_value' => $this->store->get('creator_address_'.$i) ? $this->store->get('creator_address_'.$i) : '',
                );
                $form['fields']['creators']['creator_zipcode_'.$i] = array(
                    '#type' => 'textfield',
                    '#title' => t('Zipcode:'),
                    '#default_value' => $this->store->get('creator_zipcode_'.$i) ? $this->store->get('creator_zipcode_'.$i) : '',
                );
                $form['fields']['creators']['creator_phone_'.$i] = array(
                    '#type' => 'tel',
                    '#title' => t('Phone'),
                    '#default_value' => $this->store->get('creator_phone_'.$i) ? $this->store->get('creator_phone_'.$i) : '',
                );
                $form['fields']['creators']['creator_email_'.$i] = array(
                    '#type' => 'email',
                    '#title' => t('Email'),
                    '#default_value' => $this->store->get('creator_email_'.$i) ? $this->store->get('creator_email_'.$i) : '',
                    '#suffix' => '<hr><br>',
                );
            }
        }

        if ($max < 5) {
            $form['fields']['creators']['add'] = array(
                '#type' => 'submit',
                '#name' => 'addfield',
                '#value' => t('Add Creator'),
                '#submit' => array(array($this, 'addfieldsubmit')),
                '#ajax' => array(
                    'callback' => array($this, 'addfieldCallback'),
                    'wrapper' => 'creators-wrapper',
                    'effect' => 'fade',
                ),
            );
        }
        
        if ($max >= 1 && $max < 5) {
            $form['fields']['creators']['remove'] = array(
                '#type' => 'submit',
                '#name' => 'removefield',
                '#value' => t('Remove Creator'),
                '#submit' => array(array($this, 'removefieldsubmit')),
                '#ajax' => array(
                    'callback' => array($this, 'removefieldCallback'),
                    'wrapper' => 'creators-wrapper',
                    'effect' => 'fade',
                ),
            );
        }
        
        $form['creators_title2'] = array(
            '#markup' => '<br><br>',
        );
    }
    
    public function getTransferProcedures(&$form)
    {
        $form['transfer'] = array(
            '#type' => 'fieldset',
            '#title' => t('<b>Transfer Procedures</b>'),
            '#collapsible' => true,
            '#collapsed' => false,
            '#field_prefix' => 'Data shall be transmitted to the repository by the Depositor on the following date or schedule, using the transfer medium described'
        );
                
        $form['transfer']['folder_name'] = array(
            '#type' => 'textfield',
            '#title' => t('Folder name or BagIt name:'),
            '#required' => true,
            '#default_value' => $this->folder_name,
            '#description' => 'Please provide name of folder or BagIt containing the resources'
        );
        
        $form['transfer']['transfer_date'] = array(
            '#type' => 'textfield',
            '#title' => t('Transfer date:'),
            '#attributes' => array("readonly" => true),
            '#default_value' => date("d-m-Y")
        );
        
        $form['transfer']['transfer_method'] = array(
            '#type' => 'select',
            '#title' => t('Transfer medium and method:'),
            '#options' => \Drupal\oeaw\DepAgreeConstants::getTransferMedium(),
            '#description' => $this->t(''),
            '#required' => false,
            '#default_value' => $this->transfer_method,
        );
        
        $form['transfer']['transfer_method_link_url'] = [
            '#type' => 'container',
            '#states' => array(
                'visible' => array(
                    ':input[name="transfer_method"]' => array('value' => 'LINK'),
                ),
            ),
        ];
        
        $form['transfer']['transfer_method_link_url']['transfer_method_link_url'] = array(
            '#type' => 'textfield',
            '#title' => t('URL for transfer:'),
            '#default_value' => $this->transfer_method_url,
            '#description' => 'Please provide the URL for oeawCloud or file storage'
        );
        
        $form['transfer']['transfer_method_oeawcloud_url'] = [
            '#type' => 'container',
            '#states' => array(
                'visible' => array(
                    ':input[name="transfer_method"]' => array('value' => 'OEAWCLOUD'),
                ),
            ),
        ];
        
        $form['transfer']['transfer_method_oeawcloud_url']['transfer_method_oeawcloud_url'] = array(
            '#type' => 'textfield',
            '#title' => t('URL for transfer:'),
            '#default_value' => $this->transfer_method_url,
            '#description' => 'Please provide the URL for oeawCloud or file storage'
        );
        
       
        $form['transfer']['material_mat_licence'] = array(
            '#type' => 'select',
            '#options' => \Drupal\oeaw\DepAgreeConstants::getMaterialLicences(),
            '#title' => t('Licence:'),
            '#required' => false,
            '#default_value' => $this->material_mat_licence ? $this->material_mat_licence : 'CC-BY',
            '#description' => $this->t('Please choose a licence for the resources. We strongly encourage open access for your data, which is essential for reuse and thus longevity of data. '
                    . 'We suggest the use of CC-BY (CC - Attribution) or CC-BY-SA (CC - Attribution-ShareAlike). '
                    . 'When depositing code or software consider using specific software licences like BSD or GPL.'),
        );
        
        $form['transfer']['material_ipr'] = array(
            '#type' => 'textarea',
            '#title' => t('Intellectual Property Rights (IPR):'),
            '#required' => false,
            '#default_value' => $this->material_ipr,
            '#description' => $this->t('Please state any intellectual property rights including, but not limited to copyrights, related (or neighbouring) rights and database rights to take into consideration for this deposition.'),
        );
    }
    
    public function getEmbargo(&$form)
    {
        $form['transfer']['embargo_title'] = array(
            '#type' => 'fieldset',
            '#title' => t('<b>Embargo</b>'),
            '#collapsible' => true,
            '#collapsed' => false,
        );
       
        
        $form['transfer']['embargo_question'] = array(
            '#type' => 'radios',
            '#title' => t('List of file formats included:'),
            '#required' => false,
            '#default_value' => $this->embargo_question ? $this->embargo_question : 0,
            '#options' => array(0 => $this->t('No'), 1 => $this->t('Yes')),
            '#description' => $this->t('Is an embargo period for your resources needed?'),
        );
        
        $form['transfer']['embargo_date'] = [
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
        
        $form['transfer']['embargo_date']['embargo_date'] = array(
            '#type' => 'date',
            '#title' => t('Until when?'),
            '#default_value' => $this->embargo_date,
            '#date_label_position' => 'within', // See other available attributes and what they do in date_api_elements.inc
            '#date_increment' => 15, // Optional, used by the date_select and date_popup elements to increment minutes and seconds.
            '#date_year_range' => '-2:+2', // Optional, used to set the year range (back 3 years and forward 3 years is the default).
        );
    }
        
    public function getDisseminationMaterials(array &$form)
    {
        $form['diss_material'] = array(
            '#type' => 'fieldset',
            '#title' => t('<b>Dissemination Material</b>'),
            '#collapsible' => true,
            '#collapsed' => false,
        );
        
        $form['diss_material']['diss_material_title'] = array(
            '#type' => 'managed_file',
            '#title' => t('Title image:'),
            '#upload_location' => 'public://'.$this->repoid.'/',
            '#default_value' => $this->diss_material_title,
            '#upload_validators' => array(
                'file_validate_extensions' => array('png jpg gif jpeg'),
             ),
            '#description' => $this->t('Please provide an image to be used for resource presentation '),
            '#required' => false,
        );
       
        $form['diss_material']['diss_material_logos'] = array(
            '#type' => 'managed_file',
            '#title' => t('Logos:'),
            '#multiple' => true,
            '#upload_location' => 'public://'.$this->repoid.'/',
            '#default_value' => $this->diss_material_logos,
            '#upload_validators' => array(
                'file_validate_extensions' => array('png jpg gif jpeg'),
             ),
            '#description' => $this->t('Please provide logos to be displayed along the resources'),
            '#required' => false,
            '#theme' => 'advimagearray_thumb_upload',
        );
    }
    
    public function getAccessMode(array &$form)
    {
        $form['transfer']['access_mode'] = array(
            '#type' => 'radios',
            '#title' => t('Access mode:'),
            '#options' => \Drupal\oeaw\DepAgreeConstants::getAccessMode(),
            '#description' => $this->t('Please specify the access mode and who the contact to grant access will be.'),
            '#required' => false,
            '#default_value' => $this->access_mode,
        );
       
        $form['transfer']['access_mode_check'] = [
            '#type' => 'container',
            '#states' => array(
                'visible' => array(
                    ':input[name="access_mode"]' => array('value' => 'RES'),
                ),
            ),
        ];
        
        $form['transfer']['access_mode_check']['accmode_l_name'] = array(
            '#type' => 'textfield',
            '#title' => t('Last Name:'),
            '#required' => false,
            '#default_value' => $this->store->get('accmode_l_name') ? $this->store->get('accmode_l_name') : '',
            '#prefix' => t('<h2><b>Access Mode Contact person </b></h2>'),
            '#states' => array(
                'visible' => array(
                    ':input[name="access_mode"]' => array('value' => 'RES'),
                ),
            ),
        );
        $form['transfer']['access_mode_check']['accmode_f_name'] = array(
            '#type' => 'textfield',
            '#title' => t('First Name:'),
            '#required' => false,
            '#default_value' => $this->store->get('accmode_f_name') ? $this->store->get('accmode_f_name') : '',
            '#states' => array(
                'visible' => array(
                    ':input[name="access_mode"]' => array('value' => 'RES'),
                ),
            ),
        );
        $form['transfer']['access_mode_check']['accmode_title'] = array(
            '#type' => 'textfield',
            '#title' => t('Title:'),
            '#default_value' => $this->store->get('accmode_title') ? $this->store->get('accmode_title') : '',
            '#states' => array(
                'visible' => array(
                    ':input[name="access_mode"]' => array('value' => 'RES'),
                ),
            ),
        );
        $form['transfer']['access_mode_check']['accmode_city'] = array(
            '#type' => 'textfield',
            '#title' => t('City:'),
            '#default_value' => $this->store->get('accmode_city') ? $this->store->get('accmode_city') : '',
            '#states' => array(
                'visible' => array(
                    ':input[name="access_mode"]' => array('value' => 'RES'),
                ),
            ),
        );
        $form['transfer']['access_mode_check']['accmode_address'] = array(
            '#type' => 'textfield',
            '#title' => t('Address:'),
            '#default_value' => $this->store->get('accmode_address') ? $this->store->get('accmode_address') : '',
            '#states' => array(
                'visible' => array(
                    ':input[name="access_mode"]' => array('value' => 'RES'),
                ),
            ),
        );
        $form['transfer']['access_mode_check']['accmode_zipcode'] = array(
            '#type' => 'textfield',
            '#title' => t('Zipcode:'),
            '#default_value' => $this->store->get('accmode_zipcode') ? $this->store->get('accmode_zipcode') : '',
            '#states' => array(
                'visible' => array(
                    ':input[name="access_mode"]' => array('value' => 'RES'),
                ),
            ),
        );
        $form['transfer']['access_mode_check']['accmode_phone'] = array(
            '#type' => 'tel',
            '#title' => t('Phone'),
            '#default_value' => $this->store->get('accmode_phone') ? $this->store->get('accmode_phone') : '',
            '#states' => array(
                'visible' => array(
                    ':input[name="access_mode"]' => array('value' => 'RES'),
                ),
            ),
        );
        $form['transfer']['access_mode_check']['accmode_email'] = array(
            '#type' => 'email',
            '#title' => t('Email'),
            '#default_value' => $this->store->get('accmode_email') ? $this->store->get('accmode_email') : '',
            '#suffix' => '<hr><br>',
            '#states' => array(
                'visible' => array(
                    ':input[name="access_mode"]' => array('value' => 'RES'),
                ),
            ),
        );
    }
    
  
    /**
    * Ajax submit to add new field.
    */
    public function addfieldsubmit(array &$form, FormStateInterface &$form_state)
    {
        $max = $form_state->get('fields_count') + 1;
        $form_state->set('fields_count', $max);
        $this->store->set('fields_count_value', $max);
        $form_state->setRebuild(true);
    }
    
    public function removefieldsubmit(array &$form, FormStateInterface &$form_state)
    {
        $max = $form_state->get('fields_count') - 1;
        $form_state->set('fields_count', $max);
        $this->store->set('fields_count_value', $max);
        $form_state->setRebuild(true);
    }

    /**
    * Ajax callback to add new field.
    */
    public function addfieldCallback(array &$form, FormStateInterface &$form_state)
    {
        return $form['fields']['creators'];
    }
  
    public function removefieldCallback(array &$form, FormStateInterface &$form_state)
    {
        return $form['fields']['creators'];
    }
    /*
    public function validateForm(array &$form, FormStateInterface $form_state) {
        if (strlen($form_state->getValue('folder_name')) < 3) {
            $form_state->setErrorByName('folder_name', $this->t('Please provide a real folder name!'));
        }
    }
    */
  
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $form3Val = array();
        //get the class and root values from the form
        $form3Val['accmode_l_name'] = $form_state->getValue('accmode_l_name');
        $form3Val['accmode_f_name'] = $form_state->getValue('accmode_f_name');
        $form3Val['accmode_title'] = $form_state->getValue('accmode_title');
        $form3Val['accmode_city'] = $form_state->getValue('accmode_city');
        $form3Val['accmode_address'] = $form_state->getValue('accmode_address');
        $form3Val['accmode_zipcode'] = $form_state->getValue('accmode_zipcode');
        $form3Val['accmode_phone'] = $form_state->getValue('accmode_phone');
        $form3Val['accmode_email'] = $form_state->getValue('accmode_email');
        $form3Val['material_mat_licence'] = $form_state->getValue('material_mat_licence');
        $form3Val['folder_name'] = $form_state->getValue('folder_name');
        $form3Val['transfer_date'] = $form_state->getValue('transfer_date');
        $form3Val['transfer_method'] = $form_state->getValue('transfer_method');
        $form3Val['integrity_checks'] = $form_state->getValue('integrity_checks');
        $form3Val['access_mode'] = $form_state->getValue('access_mode');
        $form3Val['material_ipr'] = $form_state->getValue('material_ipr');
        $form3Val['embargo_question'] = $form_state->getValue('embargo_question');
        $form3Val['embargo_date'] = $form_state->getValue('embargo_date');
        $form3Val['diss_material_title'] = $form_state->getValue('diss_material_title');
        $form3Val['diss_material_logos'] = $form_state->getValue('diss_material_logos');

        if (!empty($form_state->getValue('transfer_method_link_url'))) {
            $form3Val['transfer_method_url'] = $form_state->getValue('transfer_method_link_url');
        } else {
            $form3Val['transfer_method_url'] = $form_state->getValue('transfer_method_oeawcloud_url');
        }
        $form3Val['transfer_method_link_url'] = $form_state->getValue('transfer_method_link_url');
        $form3Val['fields_count'] = $form_state->get('fields_count');
        $fields_count = $this->store->set('fields_count_value', $form_state->get('fields_count'));

        $fc = $form_state->get('fields_count');

        for ($i=0; $i <= $fc; $i++) {
            $form3Val['creator_title_'.$i] = $form_state->getValue('creator_title_'.$i);
            $form3Val['creator_l_name_'.$i] = $form_state->getValue('creator_l_name_'.$i);
            $form3Val['creator_f_name_'.$i] = $form_state->getValue('creator_f_name_'.$i);
            $form3Val['creator_institution_'.$i] = $form_state->getValue('creator_institution_'.$i);
            $form3Val['creator_city_'.$i] = $form_state->getValue('creator_city_'.$i);
            $form3Val['creator_address_'.$i] = $form_state->getValue('creator_address_'.$i);
            $form3Val['creator_zipcode_'.$i] = $form_state->getValue('creator_zipcode_'.$i);
            $form3Val['creator_phone_'.$i] = $form_state->getValue('creator_phone_'.$i);
            $form3Val['creator_email_'.$i] = $form_state->getValue('creator_email_'.$i);
        }

        
        $this->store->set('accmode_l_name', $form_state->getValue('accmode_l_name'));
        $this->store->set('accmode_f_name', $form_state->getValue('accmode_f_name'));
        $this->store->set('accmode_title', $form_state->getValue('accmode_title'));
        $this->store->set('accmode_city', $form_state->getValue('accmode_city'));
        $this->store->set('accmode_address', $form_state->getValue('accmode_address'));
        $this->store->set('accmode_zipcode', $form_state->getValue('accmode_zipcode'));
        $this->store->set('accmode_phone', $form_state->getValue('accmode_phone'));
        $this->store->set('accmode_email', $form_state->getValue('accmode_email'));
        $this->store->set('folder_name', $form_state->getValue('folder_name'));
        $this->store->set('material_mat_licence', $form_state->getValue('material_mat_licence'));
        $this->store->set('transfer_date', $form_state->getValue('transfer_date'));
        $this->store->set('transfer_method', $form_state->getValue('transfer_method'));
        $this->store->set('integrity_checks', $form_state->getValue('integrity_checks'));
        $this->store->set('access_mode', $form_state->getValue('access_mode'));
        $this->store->set('material_ipr', $form_state->getValue('material_ipr'));
        $this->store->set('embargo_date', $form_state->getValue('embargo_date'));
        $this->store->set('embargo_question', $form_state->getValue('embargo_question'));
        $this->store->set('diss_material_logos', $form_state->getValue('diss_material_logos'));
        $this->store->set('diss_material_title', $form_state->getValue('diss_material_title'));
        $this->store->set('transfer_method_url', $form_state->getValue('transfer_method_url'));
        $this->store->set('transfer_method_link_url', $form_state->getValue('transfer_method_link_url'));
        $fields_count = $this->store->set('fields_count_value', $form_state->get('fields_count'));

        $DBData = array();
    
        $this->store->set('form3Val', $form3Val);
        
        //we have record in the DB, so it will be an DB update
        if (isset($this->dbData["data"]) && !empty($this->dbData["data"])) {
            $DBData = json_decode($this->dbData["data"], true);
            //if we dont have a key then we creating one
            if (array_key_exists('three', $DBData) == null) {
                $DBData["three"] = $form3Val;
            } else {
                //if we have it then it will be a modification
                foreach ($DBData as $key => $value) {
                    if ($key == "three") {
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
        } else {
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

        parent::saveData();
        //$form_state->setRedirect('oeaw_depagree_four', array('formid' => $this->repoid));
    }
}
