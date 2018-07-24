<?php 

namespace Drupal\oeaw\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\user\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\oeaw\Model\OeawStorage;
use Drupal\oeaw\OeawFunctions;
use acdhOeaw\util\RepoConfig as RC;

class SidebarKeywordSearchForm extends FormBase
{
    
    private $oeawStorage;
    private $oeawFunctions;
    
    /**
     * Set up necessary properties
     */
    public function __construct() 
    {
        $this->oeawStorage = new OeawStorage();
        $this->oeawFunctions = new OeawFunctions();
    }
    
    /**
     * Set up the form id
     * @return string
     */
    public function getFormId()
    {
        return "sks_form";
    }
    
    /**
     * Build form
     * 
     * @param array $form
     * @param FormStateInterface $form_state
     * @return string
     */
    public function buildForm(array $form, FormStateInterface $form_state) 
    {   

        echo "sidebar keyword";
        $this->createSearchInput($form);
        
        $resData["title"] = "Resource Types";
        $resData["type"] = "searchbox_types";
        $resFields = $this->oeawStorage->getACDHTypes(true);
        
        $rs = array();
        foreach($resFields as $val){            
            $type = str_replace(RC::get('fedoraVocabsNamespace'), '', $val['type']);
            $count = str_replace(RC::get('fedoraVocabsNamespace'), '', $val['type'])." (".$val['typeCount'].")";
            $rs[$type] = $count;
        }
        $resData["fields"] = $rs;
        if(count($resData["fields"]) > 0){
            $this->createBox($form, $resData);
        }
        
        $formatData["title"] = "Format";
        $formatData["type"] = "searchbox_format";
        $formatFields = $this->oeawStorage->getMimeTypes();
        $frm = array();
        foreach($formatFields as $val){            
            $type = $val['mime'];
            $count = $val['mime']." (".$val['mimeCount'].")";
            $frm[$type] = $count;
        }
        $formatData["fields"] = $frm;
        
        if(count($formatData["fields"]) > 0){
            $this->createBox($form, $formatData);
        }
        
        $form['date_start_date'] = [
            '#type' => 'date',           
            '#date_format' => 'd-m-Y',
            '#title' => t('Start date'),
        ];
        
        $form['date_end_date'] = [
            '#type' => 'date',
            '#date_format' => 'd-m-Y',
            '#title' => t('End date'),
        ];
        return $form;
    }
    
    /**
     * Create the checbox templates
     * 
     * @param array $form
     * @param array $data
     */
    private function createBox(array &$form, array $data)
    {
        $form['search'][$data["type"]] = array(
            '#type' => 'checkboxes',
            '#options' =>
                $data["fields"],
            '#attributes' => array(
                'class' => array('form-checkbox-custom'),
            ),
            '#title' => $this->t($data["title"])
        );
    }
    
    
    /**
     * 
     * this function creates the search input field 
     * 
     * @param array $form
     * @return array
     */
    private function createSearchInput(array &$form)
    {

        $propertys = array();
        $searchTerms = array();
        $basePath = base_path();
        $propertys = $this->oeawStorage->getAllPropertyForSearch();
  
        if(empty($propertys)){
             drupal_set_message($this->t('Your DB is EMPTY! There are no Propertys -> SidebarKeywordSearchForm '), 'error');
             return $form;
        }else {
            $fields = array();
            // get the fields from the sparql query 
            $fields = array_keys($propertys[0]);        
            $searchTerms = $this->oeawFunctions->createPrefixesFromArray($propertys, $fields);
            $searchTerms = $searchTerms["p"];
            asort($searchTerms);
            if(count($searchTerms) > 0) {
                foreach($searchTerms as $terms){
                    $select[$terms] = t($terms);
                }
                
                // If there was a keyword search already, use it in the input as value
                $fullpath = $_SERVER['REQUEST_URI'];
                $fullpath = explode("/", $fullpath);
                if (count($fullpath) == 3) {
	                $defaultterm = end($fullpath);
	                $actionterm = prev($fullpath);
	                if ($actionterm != "oeaw_keywordsearch") {
		               $defaultterm = "";
	                }
                } else {
	                $defaultterm = "";
                }			    
                $form['metavalue'] = array(
                  '#type' => 'textfield',
                  '#attributes' => array(
	                'value' => $defaultterm, 
                    'class' => array('form-control')
				  ),                            
                  '#required' => TRUE,
                );
                $form['actions']['#type'] = 'actions';
                $form['actions']['submit'] = array(
                  '#type' => 'submit',
                  '#value' => $this->t('search'),
                  '#attributes' => array(
                    'class' => array('keywordsearch-btn')
				  ),                   
                  '#button_type' => 'primary',
                );
                $form['examples'] = array(
                  '#type' => 'container',
                  '#attributes' => array(
                    'class' => array('form-examples')
                  ),
                  '#markup' => $this->t('Keyword examples for a quick-start:'),
                );
			    
                $form['examples']['example-1'] = array(
                  '#type' => 'container',
                  '#markup' => $this->t('Austria'),
                  '#attributes' => array(
                    'class' => array('form-example-btn'),
                    'onClick' => 'window.location = "'.$basePath.'oeaw_keywordsearch/Austria";'
				  ),                   
                  '#button_type' => 'primary',
                );
                $form['examples']['example-2'] = array(
                  '#type' => 'container',
                  '#markup' => $this->t('Media'),
                  '#attributes' => array(
                    'class' => array('form-example-btn'),
                    'onClick' => 'window.location = "'.$basePath.'oeaw_keywordsearch/Media";'
				  ),                   
                  '#button_type' => 'primary',
                );
                
                $form['examples']['example-3'] = array(
                  '#type' => 'container',
                  '#markup' => $this->t('History'),
                  '#attributes' => array(
                    'class' => array('form-example-btn'),
                    'onClick' => 'window.location = "'.$basePath.'oeaw_keywordsearch/History";'
				  ),                   
                  '#button_type' => 'primary',
                );
                
                return $form;
            } else {            
                drupal_set_message($this->t('Your DB is EMPTY! There are no Propertys -> SidebarKeywordSearchForm'), 'error');
                return $form;
            }
        }
    }
    
    public function submitForm(array &$form, FormStateInterface $form_state) 
    {
        $metavalue = $form_state->getValue('metavalue');
        // Data AND thun NOT editions type:Collection NOT Person date:[20170501 TO 20171020]
                
        //$metaVal = $this->oeawFunctions->convertSearchString($metavalue);        
        
     
        //$form_state->setRedirect('oeaw_keywordsearch', ["metavalue" => $metaVal]); 
        $form_state->setRedirect('oeaw_keywordsearch', ["metavalue" => $metavalue]); 
    
    }
  
}
