<?php

namespace Drupal\oeaw\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\Url;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ChangedCommand;
use Drupal\Core\Ajax\CssCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\user\PrivateTempStoreFactory;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\FedoraResource;

use acdhOeaw\util\RepoConfig as RC;
use EasyRdf_Graph;
use EasyRdf_Resource;
use Drupal\oeaw\OeawStorage;
use Drupal\oeaw\OeawFunctions;
use Symfony\Component\HttpFoundation\RedirectResponse;

class EditForm extends FormBase {

    /**
     * @var \Drupal\user\PrivateTempStoreFactory
     */
    protected $tempStoreFactory;

    /**
     * @var \Drupal\Core\Session\SessionManagerInterface
     */
    private $sessionManager;

    /**
     * @var \Drupal\Core\Session\AccountInterface
     */
    private $currentUser;

    /**
     * @var \Drupal\user\PrivateTempStore
     */
    protected $store;

    
    private $OeawFunctions;
    private $OeawStorage;
    private $fedora;
    
    /**
     *
     * @param \Drupal\user\PrivateTempStoreFactory $temp_store_factory
     * @param \Drupal\Core\Session\SessionManagerInterface $session_manager
     * @param \Drupal\Core\Session\AccountInterface $current_user
     */
    public function __construct(PrivateTempStoreFactory $temp_store_factory, SessionManagerInterface $session_manager, AccountInterface $current_user) {
        $this->tempStoreFactory = $temp_store_factory;
        $this->sessionManager = $session_manager;
        $this->currentUser = $current_user;
        $this->store = $this->tempStoreFactory->get('edit_form');
        \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');
        $this->OeawStorage = new OeawStorage();
        $this->OeawFunctions = new OeawFunctions();
        $this->fedora = new Fedora();
    }

    public static function create(ContainerInterface $container) {
        return new static(
                $container->get('user.private_tempstore'), $container->get('session_manager'), $container->get('current_user')
        );
    }

    public function getFormId() {
        return "edit_form";
    }

    public function buildForm(array $form, FormStateInterface $form_state) {

        $form_state->disableCache();
        //get the hash uri from the url, based on the drupal routing file
        $editHash = \Drupal::request()->get('uri');
        
        $editMetaData = array();
        $isImage = false;
        if (empty($editHash)) {
            return drupal_set_message($this->t('The uri is not exists!'), 'error');            
        }
        
        $editUri = $this->OeawFunctions->createDetailsUrl($editHash, 'decode');
        $editMetaData = $this->fedora->getResourceByUri($editUri)->getMetadata();
        // get the digital resource classes where the user must upload binary file
        $digitalResQuery = $this->OeawStorage->getDigitalResources();
        
        $digitalResources = array();
        $digitalResources = $this->digitalResources($digitalResQuery);
        
        //create and load the data to the graph
        $classGraph = $this->OeawFunctions->makeGraph($editUri);
        $resTitle = $classGraph->label($editUri);
        
        $classVal = array();
        //get the identifier from the graph and convert the easyrdf_resource object to php array        
        
        $classValue = $editMetaData->all(\Drupal\oeaw\ConnData::$rdfType);
        
        if(count($classValue) > 0){
            foreach ($classValue as $v) {
                if(!empty($v->getUri())){
                    $classVal[] = $v->getUri();
                }
            }
        } else {
            return drupal_set_message($this->t('The acdh RDF Type is missing'), 'error');
        }

        $fedora = $this->OeawFunctions->initFedora();
        $editUriClass = "";
       
        if (!empty($classVal)) {
            foreach ($classVal as $cval) {
               $res = $fedora->getResourcesByProperty(RC::get('fedoraIdProp'), $cval);
                // this will contains the onotology uri, what will helps to use to know
                // which fields we need to show in the editing form
                echo "<pre>";
                var_dump($cval);
                echo "</pre>";

                if(count($res) > 0){
                    $editUriClass = $res[0]->getUri();
                    $actualClassUri = $cval;
                    if($cval == \Drupal\oeaw\ConnData::$imageProperty ){ $isImage = true; }
                }
            }
        } else {
            return drupal_set_message($this->t('ACDH Vocabs missing from the Resource!!'), 'error');
        }
        
        if (empty($editUriClass)) {
            $msg = base64_encode("There is no valid RDF:TYPE for the URI Class!!");
            $response = new RedirectResponse(\Drupal::url('oeaw_error_page', ['errorMSG' => $msg]));
            $response->send();
            return;
        }
        
        //the actual fields for the editing form based on the editUriClass variable
        $editUriClassMetaFields = $this->OeawStorage->getClassMeta($editUriClass);
        
        if(empty($editUriClassMetaFields)){
            return drupal_set_message($this->t('There are no Fields for this URI CLASS'), 'error');
        }
        
        $form['resource_title'] = array(
            '#markup' => '<h2><b><a href="'.$editUri.'" target="_blank">'.$resTitle.'</a></b></h2>',
        );
        
        $frmOldData = array();
        
        foreach($editUriClassMetaFields as $m){
            $fieldValues = array();
            $fieldValue = "";
            $attributes = array();
            $required = FALSE;
            
            //$fieldValues = $classGraph->all($editUri, $m["id"]);
            $fieldValues = $editMetaData->all($m["id"]);
            
            //get the fields cardinality info
            $attributes = $this->setCardinality($m);

            //if the input field has value then we need to check the type
            if(count($fieldValues) > 0){           
                if(get_class($fieldValues[0]) == "EasyRdf\Resource"){
                    $fieldValue = $this->getResourceValue($fieldValues[0]);
                }                
                if(get_class($fieldValues[0]) == "EasyRdf\Literal"){
                    $fieldValue = $this->getLiteralValue($fieldValues[0]);
                }
            }
            
            $oldLabel = "";
            $label = $this->getLabel($m["id"]);
            // if the label is the isPartOf or identifier, then we need to disable the editing
            // to the users, they do not have a permission to change it
            $attributes = $this->disableFields($m["id"], $attributes);
            
            $oldLabel = $this->getOldLabel($fieldValue, $classGraph, $fedora, $editUri, $m["id"]);
            
            if(isset($attributes["data-cardinality"]) || isset($attributes["data-mincardinality"])){ $required = TRUE; }
            
             // generate the form fields
            $form[$label] = array(
                '#type' => 'textfield',
                '#title' => $this->t($label),
                '#default_value' => $fieldValue,
                '#required' => $required,
                '#attributes' => $attributes,
                '#field_suffix' => $oldLabel,
                //description required a space, in other case the ajax callback will not works....
                '#description' => $this->t($label.' description'),
                //define the autocomplete route and values
                '#autocomplete_route_name' => 'oeaw.autocomplete',
                '#autocomplete_route_parameters' => array('prop1' => strtr(base64_encode($m["id"]), '+/=', '-_,'), 'fieldName' => $label),
                //create the ajax to we can display the selected uri title
                '#ajax' => [
                    // Function to call when event on form element triggered.
                    'callback' => 'Drupal\oeaw\Form\EditForm::fieldValidateCallback',
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
            
            //create the hidden propertys to the saving methods
            $labelVal = str_replace(' ', '+', $label);
           /* $form[$labelVal . ':oldValues'] = array(
                '#type' => 'hidden',
                '#value' => $fieldValue,
            );
*/
            $property[$label] = $m["id"];
            $fieldsArray[] = $label;
            //$fieldsArrayOldValues[] = $labelVal . ':oldValues';
            $frmOldData[$label][] = $fieldValue;
            
            
            if(isset($attributes["data-mincardinality"]) && $attributes["data-mincardinality"] > 1){
                
                for($x = 2; $x <= $attributes["data-mincardinality"]; $x++) {                    
                    $fieldValue = "";                    
                    if(isset($fieldValues[$x-1])){
                        if(get_class($fieldValues[$x-1]) == "EasyRdf\Resource"){
                            $fieldValue = $this->getResourceValue($fieldValues[$x-1]);
                        }                
                        if(get_class($fieldValues[$x-1]) == "EasyRdf\Literal"){
                            $fieldValue = $this->getLiteralValue($fieldValues[$x-1]);
                        }
                    }                    
                    
                    $oldLabel = "";
                    $oldLabel = $this->getOldLabel($fieldValue, $classGraph, $fedora, $editUri, $m["id"]);
                    
                    $form[$label.'-'.$x] = array(
                        '#type' => 'textfield',                        
                        '#default_value' => $fieldValue,
                        '#attributes' => $attributes,
                        '#required' => $required,
                        '#field_suffix' => $oldLabel,
                        '#description' => $this->t($label.'-'.$x.' description'),
                        '#autocomplete_route_name' => 'oeaw.autocomplete',
                        '#autocomplete_route_parameters' => array('prop1' => strtr(base64_encode($m["id"]), '+/=', '-_,'), 'fieldName' => $label.'-'.$x),
                        //create the ajax to we can display the selected uri title
                        '#ajax' => [
                            // Function to call when event on form element triggered.
                            'callback' => 'Drupal\oeaw\Form\EditForm::fieldValidateCallback',
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
                    
                    $form[$labelVal.'-'.$x.':prop'] = array(
                        '#type' => 'hidden',
                        '#value' => $m["id"],
                    );
                    $frmOldData[$label][] = $fieldValue;
                    
                    $fieldsArray[] = $label.'-'.$x;
                    $fieldsArray[] = $label.'-'.$x.':prop';
                }
            }
            
            if(isset($attributes["data-maxcardinality"]) && $attributes["data-maxcardinality"] > 1){
                
                for($x = 2; $x <= $attributes["data-maxcardinality"]; $x++) {
                    
                    
                    $fieldValue = "";
                    if(isset($fieldValues[$x-1])){
                        if(get_class($fieldValues[$x-1]) == "EasyRdf\Literal"){
                            $fieldValue = $fieldValues[$x-1]->getValue();
                        }

                        if(get_class($fieldValues[$x-1]) == "EasyRdf\Resource"){
                            $fieldValue = $fieldValues[$x-1]->getUri();
                        }
                    }
                    
                    $oldLabel = "";
                    $oldLabel = $this->getOldLabel($fieldValue, $classGraph, $fedora, $editUri, $m["id"]);
                    
                    $form[$label.'-'.$x] = array(
                        '#type' => 'textfield',                        
                        '#default_value' => $fieldValue,
                        '#attributes' => $attributes,
                        '#required' => $required,
                        '#field_suffix' => $oldLabel,
                        '#description' => $this->t($label.'-'.$x.' description'),
                        '#autocomplete_route_name' => 'oeaw.autocomplete',
                        '#autocomplete_route_parameters' => array('prop1' => strtr(base64_encode($m["id"]), '+/=', '-_,'), 'fieldName' => $label.'-'.$x),
                        //create the ajax to we can display the selected uri title
                        '#ajax' => [
                            // Function to call when event on form element triggered.
                            'callback' => 'Drupal\oeaw\Form\EditForm::fieldValidateCallback',
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
              
                    $form[$labelVal.'-'.$x.':prop'] = array(
                        '#type' => 'hidden',
                        '#value' => $m["id"],
                    );
                    $frmOldData[$label][] = $fieldValue;
                    $fieldsArray[] = $label.'-'.$x;
                    $fieldsArray[] = $label.'-'.$x.':prop';
              
                }
            }
            //if we have a maxcardinality property then we need to add the add/remove buttons to the UI
            if(isset($attributes["data-maxcardinality"])){
                $form[$label.'-add_remove'] = array(
                    '#type' => 'item',
                    '#markup' => t('<a href="#" id="'.$label.'-plus">Add fields</a> <a href="#" id="'.$label.'-minus">Remove last</a>')
                );
            }        
        }
        
        $this->store->set('formEditFields', $fieldsArray);        
        $this->store->set('frmOldData', $frmOldData);
        $this->store->set('propertysArray', $property);
        $this->store->set('resourceUri', $editUri);

        $checkDigRes = in_array($actualClassUri, $digitalResources);

        // if we have a digital resource then the user must upload a binary resource        
        if ($checkDigRes == true || $isImage == true) {
            $form['file'] = array(
                '#type' => 'managed_file',
                '#title' => t('Binary Resource'),                
                '#upload_validators' => array(
                    'file_validate_extensions' => array('xml doc txt simplified docx pdf jpg png tiff gif bmp'),
                 ),
                '#description' => t('Upload a file, allowed extensions: XML, CSV, and images etc....'),
            );            
        }
        
        $form['submit'] = array(
            '#type' => 'submit',
            '#value' => t('Submit'),
        );
       
        return $form;
    }
    
    
    public function validateForm(array &$form, FormStateInterface $form_state) {
        
    }

    public function submitForm(array &$form, FormStateInterface $form_state) {
        //get the form stored values
        $editForm = $this->store->get('formEditFields');
        $frmOldData = $this->store->get('frmOldData');
        $propertysArray = $this->store->get('propertysArray');
        $resourceUri = $this->store->get('resourceUri');
        //get the uploaded files values
        $fileID = $form_state->getValue('file');
        
        $newFrmValues = array();
        
        //if the user submitted a new binary resource
        if (!empty($fileID)) {
            $fileID = $fileID[0];
            //create the file object
            $fObj = file_load($fileID);
            if (!empty($fObj) || isset($fObj)) {
                //get the temp file uri
                $fUri = $fObj->getFileUri();
            }
        }
        
        // create array with new form values
        foreach ($editForm as $e) {
            $editFormValues[$e] = $form_state->getValue($e);
        }
        
     
        //create the newValues array based on the submitted data from the FORM
        $newValues = array();
        foreach($editFormValues as $key => $value){
            //if it is a cardinality field, then we wil add it to the array
            if ((strpos($key, '-') !== false) && (strpos($key, ':prop') === false) && ( preg_match('/\\d/', $key) > 0)) {
                if($key !== "isPartOf" || $key !== "identifier"){
                    $nKey = explode('-', $key);
                    $newValues[$nKey[0]][] = $value;
                }
                //we adding the normal fields too
            }elseif(strpos($key, ':prop') === false){
                if(($key !== "isPartOf") || ($key !== "identifier")){
                    $newValues[$key][] = $value;
                }
            }
        }
        
        foreach ($propertysArray as $key => $value) {
            //in the editing we need to skip the ispartof
            // because the user cant overwrite the original
            if(($key !== "isPartOf")){
                if(isset($newValues[$key])){
                    foreach($newValues[$key] as $v){
                        $uriAndValue[$value][] = $v;
                    }
                    
                }                
            }
        }
                
        $fedora = new Fedora();
        $fedora->begin();
        $resourceUri = preg_replace('|^.*/rest/|', '', $resourceUri);
        $fr = $fedora->getResourceByUri($resourceUri);
        //get the existing metadata
        $meta = $fr->getMetadata();
        
        //insert the new metadata data to the graph
        foreach($uriAndValue as $key => $value){
            if(!empty($value)){
                $meta->delete($key);
                foreach($value as $v){
                    if(!empty($v)){
                        if (strpos(substr($v,0,4), 'http') !== false) {
                            //$meta->addResource("http://vocabs.acdh.oeaw.ac.at/#represents", "http://dddd-value2222");
                            $meta->addResource($key, $v);
                        } else {
                            //$meta->addLiteral("http://vocabs.acdh.oeaw.ac.at/#depositor", "dddd-value");
                            $meta->addLiteral($key, $v);
                        }
                    }
                }
            }
        }
        
        
        try {
            $fr->setMetadata($meta);
            $fr->updateMetadata();
            if (!empty($fUri)) { $fr->updateContent($fUri); }
            $fedora->commit();
                   
            $encodeUri = $this->OeawFunctions->createDetailsUrl($resourceUri, 'encode');
            
            if (strpos($encodeUri, 'fcr:metadata') !== false) {
                $encodeUri = $encodeUri.'/fcr:metadata';
            }
            //do the redirect with the result URI
            $response = new RedirectResponse(\Drupal::url('oeaw_new_success', ['uri' => $encodeUri]));
            $response->send();
            return;
            
        } catch (Exception $ex) {
            $fedora->rollback();
            
            return drupal_set_message($this->t('Error during the saving process'), 'error');
        }
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
     * Create the attributes to the input fields
     * 
     * @param string $field
     * @param array $attributes
     * @return string
     */
    public function disableFields(string $field, array $attributes):array{
        
        if($field === RC::get('fedoraRelProp') || $field === RC::get('fedoraIdProp')){
            $attributes['readonly'] = 'readonly';
            $attributes['data-repoid'] = $field;
        } else {
            $attributes['data-repoid'] = $field;
        }        
        return $attributes;
    }
    
    /**
     * 
     * Some of the input fields has an http:// value, this method is 
     * generating a readable format from it.
     * 
     * @param type $value
     * @param type $classGraph
     * @param type $fedora
     * @param type $editUri
     * @param type $property
     * @return string
     */
    public function getOldLabel($value, $classGraph, $fedora, $editUri, $property): string{
        
        $oldLabel = "";
        //if the value is not empty and starts with the http and it is an acdh http
        if (!empty($value) 
                &&  (strpos(substr($value,0,4), 'http') !== false) 
                && (strpos($value, RC::get('fedoraIdNamespace')) !== false)) {
            
            $resOT = $fedora->getResourcesByProperty(RC::get('fedoraIdProp'), $value);
            
            foreach($resOT as $ot){
                if(!empty($ot->getMetadata()->label())){
                    $labelURL = (string)$value;
                    $labelTxt = (string)$ot->getMetadata()->label();
                    $oldLabel = "Old Value: <a href='$labelURL' target='_blank'>".$labelTxt."</a>";
                    
                }else {
                    $oldLabel = "";
                }
            }
        }
                
        return $oldLabel;   
    }
    /**
     * Get the Value of the literal property for the edit input fields
     * 
     * @param \EasyRdf\Literal $obj
     * @return string
     */
    public function getLiteralValue(\EasyRdf\Literal $obj):string{
        if(get_class($obj) == "EasyRdf\Literal"){
            $fieldValue = $obj->getValue();
        }
        return $fieldValue;
    }
    /**
     * 
     * Get the Value of the Resource property for the edit input fields
     * 
     * @param \EasyRdf\Resource $obj
     * @return string
     */
    private function getResourceValue(\EasyRdf\Resource $obj):string{
        if(get_class($obj) == "EasyRdf\Resource"){
            $fieldValue = $obj->getUri();
        }
        return $fieldValue;
    }
    
     /**
     * 
     * create the label from the property
     * 
     * @param array $m
     * @return string
     */
    private function getLabel(string $m): string 
    {        
        $label = explode("/", $m);
        $label = end($label);
        $label = str_replace('#', '', $label);
        
        return $label;
    }
    
    /**
     * 
     * Create digital resources array
     * 
     * @param array $digRes
     * @return array
     */
    private function digitalResources(array $digRes){
        $digitalResources = array();
        
        if(!$digRes){
            return drupal_set_message($this->t('digitalResources function has no data!'), 'error');
        }
        //we need that ones where the collection is true
        foreach ($digRes as $dr) {
            if (isset($dr["collection"])) {
                $digitalResources[] = $dr["id"];
            }
        }
        
        return $digitalResources;
    }
    
    /**
     * 
     * The ajax validatecallback for the readable field values.
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
    
    


}
