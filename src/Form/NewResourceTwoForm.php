<?php

namespace Drupal\oeaw\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\FedoraResource;
use acdhOeaw\util\RepoConfig as RC;
use EasyRdf_Graph;
use EasyRdf_Resource;
use InvalidArgumentException;
use RuntimeException;
use Drupal\oeaw\OeawFunctions;

class NewResourceTwoForm extends NewResourceFormBase  {
    
    /* 
     * drupal core formid
     *     
     * @return string : form id
    */
    public function getFormId() {
        return 'multistep_form_two';
    }
       
    /*      
     * drupal core buildForm function, to create the form what the user will see
     *
     * @param array $form : it will contains the form elements
     * @param FormStateInterface $form_state : form object
     *
     * @return void
    */
    public function buildForm(array $form, FormStateInterface $form_state) {
        
        $form = parent::buildForm($form, $form_state);
        $form_state->disableCache();
        // get form page 1 stored values
        $formVal = $this->store->get('form1Elements');
        
        //the selected class
        $class = $formVal['class'];
        //the selected root
        $root =  $formVal['root'];
        
        $fedora = new Fedora();
        
        //get the selected root class identifier
        if(count($fedora->getResourceByUri($root)->getId()) > 0 ){
            $rootIdentifier = $fedora->getResourceByUri($root)->getId();
        }else {
            return drupal_set_message($this->t('Your root element is missing! You cant add new resource without a root element!'), 'error');
        }
        
        // get the digital resource classes where the user must upload binary file
        //create the digitalResources array
        $digitalResources = array();
        foreach($this->OeawStorage->getDigitalResources() as $dr){
            if(isset($dr["collection"])){
                $digitalResources[] = $dr["id"];
            }
        }
        
        //create and load the data to the graph        
        $classRes = $fedora->getResourceByUri($class)->getMetadata()->get(RC::get('fedoraIdProp'));
        
        if(!empty($fedora->getResourceByUri($class)->getMetadata()->get(RC::get('fedoraIdProp'))->getUri())){
                $classValue = $fedora->getResourceByUri($class)->getMetadata()->get(RC::get('fedoraIdProp'))->getUri();
                //we store the ontology identifier for the saving process
                $this->store->set('ontologyClassIdentifier', $classValue);            
        }else {
            return drupal_set_message($this->t('ClassValue is empty!'), 'error');
        }
        
        // compare the digRes and the actual class, because if it is a DigColl then 
        // we need to show the fileupload option
        $checkDigRes = in_array($classValue, $digitalResources);

        if(count($this->OeawStorage->getClassMeta($class)) < 0){
            return drupal_set_message($this->t('There is no metadata for this class'), 'error');
        }else {
            // get the actual class metadata
            $metadataQuery = $this->OeawStorage->getClassMeta($class);
        }
        
        $fieldsArray = array();
        $defaultValue = "";
        $labelVal = "";
        
        foreach ($metadataQuery as $m) {
            //we dont need the identifier, because doorkeeper will generate it automatically
            if($m["id"] === RC::get('fedoraIdProp')){
               continue; 
            }
            
            $required = FALSE;
            $attributes = array();
            $label = $this->getLabel($m);
            
            //set the field cardinality values
            $attributes = $this->setCardinality($m);
            $attributes["data-ownclass"] = $label."-input-class";
            
            if($m["id"] === RC::get('fedoraRelProp') ){
                $defaultValue = $rootIdentifier;
                $attributes["readonly"] = "readonly";
            } else {
                $defaultValue = $this->store->get($m["id"]) ? $this->store->get($m["id"]) : '';
            }
            
            if(isset($attributes["data-cardinality"]) || isset($attributes["data-mincardinality"])){ $required = TRUE; }            
            
            $form[$label] = array(
                '#type' => 'textfield',
                '#title' => $this->t($label),
                '#default_value' => $defaultValue,
                '#attributes' => $attributes,
                '#required' => $required,
                '#description' => $this->t($label.' description'),
                '#autocomplete_route_name' => 'oeaw.autocomplete',
                '#autocomplete_route_parameters' => array('prop1' => strtr(base64_encode($m["id"]), '+/=', '-_,'), 'fieldName' => $label),
                //create the ajax to we can display the selected uri title
                '#ajax' => [
                    // Function to call when event on form element triggered.
                    'callback' => 'Drupal\oeaw\Form\NewResourceTwoForm::fieldValidateCallback',
                    'effect' => 'fade',
                    // Javascript event to trigger Ajax. Currently for: 'onchange'.
                    //we need to wait the end of the autocomplete
                    'event' => 'autocompleteclose',
                    'progress' => array(
                        // Graphic shown to indicate ajax. Options: 'throbber' (default), 'bar'.
                        'type' => 'throbber',
                        // Message to show along progress graphic. Default: 'Please wait...'.
                        'message' => NULL,
                    ),
                  ],
            );
            
            if(isset($attributes["data-mincardinality"]) && $attributes["data-mincardinality"] > 1){
                
                for($i = 2; $i <= $attributes["data-mincardinality"]; $i++) {
                    $form[$label.'-'.$i] = array(
                        '#type' => 'textfield',                        
                        '#default_value' => $defaultValue,
                        '#attributes' => $attributes,
                        '#required' => $required,
                        '#description' => $this->t($label.'-'.$i.' description'),
                        '#autocomplete_route_name' => 'oeaw.autocomplete',
                        '#autocomplete_route_parameters' => array('prop1' => strtr(base64_encode($m["id"]), '+/=', '-_,'), 'fieldName' => $label.'-'.$i),
                        //create the ajax to we can display the selected uri title
                        '#ajax' => [
                            // Function to call when event on form element triggered.
                            'callback' => 'Drupal\oeaw\Form\NewResourceTwoForm::fieldValidateCallback',
                            'effect' => 'fade',
                            // Javascript event to trigger Ajax. Currently for: 'onchange'.
                            //we need to wait the end of the autocomplete
                            'event' => 'autocompleteclose',
                            'progress' => array(
                                // Graphic shown to indicate ajax. Options: 'throbber' (default), 'bar'.
                                'type' => 'throbber',
                                // Message to show along progress graphic. Default: 'Please wait...'.
                                'message' => NULL,
                            ),
                          ],
                    );
                    
                    $form[$labelVal.'-'.$i.':prop'] = array(
                        '#type' => 'hidden',
                        '#value' => $m["id"],
                    );
                    
                    $fieldsArray[] = $label.'-'.$i;
                    $fieldsArray[] = $label.'-'.$i.':prop';
                }
            }
            
            if(isset($attributes["data-maxcardinality"]) && $attributes["data-maxcardinality"] > 1){
                
                $attributes["data-fieldhidden"] = "yes";
                
                for($i = 2; $i <= $attributes["data-maxcardinality"]; $i++) {
                    $form[$label.'-'.$i] = array(
                        '#type' => 'textfield',                        
                        '#default_value' => $defaultValue,
                        '#attributes' => $attributes,
                        '#required' => $required,
                        '#description' => $this->t($label.'-'.$i.' description'),
                        '#autocomplete_route_name' => 'oeaw.autocomplete',
                        '#autocomplete_route_parameters' => array('prop1' => strtr(base64_encode($m["id"]), '+/=', '-_,'), 'fieldName' => $label.'-'.$i),
                        //create the ajax to we can display the selected uri title
                        '#ajax' => [
                            // Function to call when event on form element triggered.
                            'callback' => 'Drupal\oeaw\Form\NewResourceTwoForm::fieldValidateCallback',
                            'effect' => 'fade',
                            // Javascript event to trigger Ajax. Currently for: 'onchange'.
                            //we need to wait the end of the autocomplete
                            'event' => 'autocompleteclose',
                            'progress' => array(
                                // Graphic shown to indicate ajax. Options: 'throbber' (default), 'bar'.
                                'type' => 'throbber',
                                // Message to show along progress graphic. Default: 'Please wait...'.
                                'message' => NULL,
                            ),
                          ],
                    );
                    
                    $form[$labelVal.'-'.$i.':prop'] = array(
                        '#type' => 'hidden',
                        '#value' => $m["id"],
                    );
                    
                    $fieldsArray[] = $label.'-'.$i;
                    $fieldsArray[] = $label.'-'.$i.':prop';
                }
            }
            
            if(isset($attributes["data-maxcardinality"])){
                $form[$label.'-add_remove'] = array(
                    '#type' => 'item',
                    '#markup' => t('<a href="#" id="'.$label.'-plus">Add fields</a> <a href="#" id="'.$label.'-minus">Remove last</a>')
                );
            }
            
            $labelVal = str_replace(' ', '+', $label);
            $form[$labelVal.':prop'] = array(
                '#type' => 'hidden',
                '#value' => $m["id"],
            );
            
            $fieldsArray[] = $label;
            $fieldsArray[] = $labelVal.':prop';
        }
      
        //the user own identifer
        $form['ownIdentifier'] = array(
            '#title' => $this->t('ownIdentifier'),
            '#type' => 'textfield',
            '#required' => True,
            '#description' => $this->t('Please add your own identifier'),
        );
                    
        
        // if we have a digital resource then the user must upload a binary resource
        if($checkDigRes == true){
            $form['file'] = array(
                '#type' => 'managed_file', 
                '#title' => t('FILE'), 
                '#required' => TRUE,
                '#upload_validators' => array(
                    'file_validate_extensions' => array('xml doc txt simplified docx pdf jpg png tiff gif bmp'),
                 ),
                '#description' => t('Upload a file, allowed extensions: XML, CSV, and images etc....'),
            );
        }
        
        $this->store->set('form2Fields', $fieldsArray);
        
        $form['actions']['previous'] = array(
            '#type' => 'link',
            '#title' => $this->t('Previous'),
            '#attributes' => array(
                'class' => array('btn'),
                'style' => 'margin:10px; color:white;'
            ),
            '#weight' => 10,
            '#url' => Url::fromRoute('oeaw_newresource_one'),
        );
        
        return $form;
    }
    /**
     * 
     * the field ajax function
     * 
     * @param array $form
     * @param FormStateInterface $form_state
     * @return type
     */
    public function fieldValidateCallback(array &$form, FormStateInterface $form_state) {
        //get the formelements
        $formElements = $form_state->getUserInput();
        $result = array();
        
        $oeawFunc = new OeawFunctions();
        $result = $oeawFunc->getFieldNewTitle($formElements, "new");
        
        return $result;
    }
    
    public function validateForm(array &$form, FormStateInterface $form_state) 
    {    
        if (strlen($form_state->getValue('title')) == 0) {
            $form_state->setErrorByName('title', $this->t('Title is required'));
        }
        
        if (strlen($form_state->getValue('isPartOf')) == 0) {
            $form_state->setErrorByName('isPartOf', $this->t('isPartOf is required'));
        }
    }
    
    public function ContainsNumbers(string $String): bool{
        return preg_match('/\\d/', $String) > 0;
    }
    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
         
        $valuesArray = array();
        $uriAndValue = array();
        $formVal = "";
        $form2Fields = array();
        
        /* get the form 1 elements */
        $formVal = $this->store->get('form1Elements');
        /* get the form2 autogenerated fields name */
        $form2Fields = $this->store->get('form2Fields');        
        $ontologyClassIdentifier = $this->store->get('ontologyClassIdentifier');
        
        //get the uploaded file Drupal number
        $fileID = $form_state->getValue('file');
        $fileID = $fileID[0];
        //create the file objectt
        $fObj = file_load($fileID);
        
        if(!empty($fObj) || isset($fObj)){
            //get the temp file uri
            $fUri = $fObj->getFileUri();
        }
        
        //get the form fields
        foreach($form2Fields as $f){
            //get the property fields
            if (strpos($f, ':') !== false) {                
                $propField = explode(':', $f);
                //if it is a cardinality input field. f.e: contributor-3
                // then it will contains a number
                if($propField[1] == 'prop'){
                    if(!empty($form_state->getValue($f))){
                        $propUrls[$propField[0]] = $form_state->getValue($f);
                    }
                }                
            }else{
                //this is the real user input value
                $valuesField = explode('-', $f);
                //if it is contains numbers then it will have more than one fields
                if($this->ContainsNumbers($f)){
                    $cardField = explode('-', $f);
                    $valuesArray[$cardField[0]][] = $form_state->getValue($f);
                }else {
                    $valuesArray[$f][] = $form_state->getValue($f);
                }
            }
        }
        //get the property urls for the keys f.e: title -> http://purl.org/dc/elements/1.1/title
        foreach($propUrls as $key => $value){
            if(!empty($value)){
                //if we have the key in the values array then we will add it our new array
                if(isset($valuesArray[$key])){
                    $uriAndValue[$value] = $valuesArray[$key];
                }
            }            
        }
        
        $ownIdentifier = $this->OeawStorage->getDataByProp(RC::get('fedoraIdProp'), "https://id.acdh.oeaw.ac.at/".urlencode($form_state->getValue('ownIdentifier')));
        if(count($ownIdentifier) > 0){
            return drupal_set_message($this->t('This ownIdentifier is already exists in the DB, please change it!!!'), 'error');
        }else {
            // add the ownIdentifier
            $uriAndValue[RC::get('fedoraIdProp')][] = "https://id.acdh.oeaw.ac.at/".urlencode($form_state->getValue('ownIdentifier'));
        }
      
        $this->store->set('fileName', $fUri);
        $this->store->set('uriAndValue', $uriAndValue);
        
        // Save the data
        parent::saveData();
    }
    
    /**
     * 
     * set the cardinality values to the field data attributes
     * based on the metadata array
     * 
     * @param array $m
     * @return array
     */
    private function setCardinality(array $m): array
    {
        $attributes = array();
        if(isset($m["cardinality"]) && !empty($m["cardinality"])){
            $attributes["data-cardinality"] = $m["cardinality"];
        }

        if(isset($m["minCardinality"]) && !empty($m["minCardinality"])){
            $attributes["data-mincardinality"] = $m["minCardinality"];
        }

        if(isset($m["maxCardinality"]) && !empty($m["maxCardinality"])){
            $attributes["data-maxcardinality"] = $m["maxCardinality"];
        }        
        return $attributes;
    }
    
    /**
     * 
     * create the label from the property
     * 
     * @param array $m
     * @return string
     */
    private function getLabel(array $m): string 
    {        
        $expProp = array();
        $label = "";
        
        $expProp = explode("/", $m["id"]);
        $expProp = end($expProp);
        
        if (strpos($expProp, '#') !== false) {
           $expProp = str_replace('#', '', $expProp);
        }        
        $label = $expProp;
        
        return $label;
    }
}
