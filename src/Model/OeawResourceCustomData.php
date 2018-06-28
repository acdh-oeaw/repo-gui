<?php

namespace Drupal\oeaw\Model;

use Drupal\oeaw\ConfigConstants as CC;

/**
 * 
 * This object will contains the oeaw_detail resource special data
 * For example: the person or organisation special properties
 * The list of these special properties can found under the ConfigConstants::$availableCustomViews
 * 
 */
class OeawResourceCustomData {
    
    private $uri;
    private $title;
    private $type;
    private $pid;
    private $identifiers;
    private $insideUri;
    private $typeUri;
    private $basicProperties = array();
    private $bpKeys = array();
    private $extendedProperties = array();
    private $epKeys = array();
    private $accessRestriction = 'public';
    public $errors = array();
    
    public static $availableCustomViews = array(
        "person", "project", "organisation", "publication", "place"
    );
    
    public function __construct(\ArrayObject $arrayObj) {
        
        if (is_object($arrayObj) || !empty($arrayObj)) {
            $objIterator = $arrayObj->getIterator();
            
            while($objIterator->valid()) {
                ($objIterator->key() == "uri") ? $this->uri = $objIterator->current() : NULL;
                ($objIterator->key() == "title") ? $this->title = $objIterator->current() : NULL;
                ($objIterator->key() == "type") ? $this->type = $objIterator->current() : NULL;
                ($objIterator->key() == "pid") ? $this->pid = $objIterator->current() : NULL;
                ($objIterator->key() == "identifiers") ? $this->identifiers = $objIterator->current() : NULL;
                ($objIterator->key() == "insideUri") ? $this->insideUri = $objIterator->current() : NULL;
                ($objIterator->key() == "typeUri") ? $this->typeUri = $objIterator->current() : NULL;
                ($objIterator->key() == "basicProperties") ? $this->basicProperties = $objIterator->current() : NULL;
                ($objIterator->key() == "extendedProperties") ? $this->extendedProperties = $objIterator->current() :  NULL;
                ($objIterator->key() == "accessRestriction") ? $this->accessRestriction = $objIterator->current() : 'public' ;
                $objIterator->next();
            }
            
        }else {
            throw new \ErrorException("ArrayObject is not an object!");
        }
        
        $this->checkEmptyVariables();
        
        if(count($this->errors) > 0){
            throw new \ErrorException("You have errors during the OeawResource Object initilaizing! Following data are missing: ".print_r($this->errors, true));
        }
        //setup the basic and extended prperties values
        $this->setupBasicExtendedKeys();
        
    }
    
    private function checkEmptyVariables() {
        if(empty($this->uri)){ array_push($this->errors, "uri"); }
        if(empty($this->title)){ array_push($this->errors, "title");  }
        if(empty($this->type)){ array_push($this->errors, "type");  }
        if(empty($this->identifiers)){ array_push($this->errors, "identifiers");  }
        if(empty($this->insideUri)){ array_push($this->errors, "insideUri");  }
    }
    
    /**
     * 
     * Setup the basic and extended properties by the type
     * 
     */
    private function setupBasicExtendedKeys(){
        if($this->isCustomType() === true){
            $this->bpKeys = $this->getBasicPropertiesByType();
            $this->epKeys = $this->getExtendedPropertiesByType();
        }
    }
    
    public function setupBasicExtendedData(\Drupal\oeaw\Model\OeawResource $data){
        if(count($this->bpKeys) > 0){
            foreach($this->bpKeys as $bP) {
                //if we have the basic prop tin our object then we will add it to the new arrobj
                if( (($data->getTableData($bP) !== null)) && (count($data->getTableData($bP)) > 0) ){
                    foreach($data->getTableData($bP) as $val){
                        $this->basicProperties[$bP] =  $val;
                    }
                }
            }
        }
        if( count($this->epKeys) > 0 ){
            foreach($this->epKeys as $eP) {
                //if we have the basic prop tin our object then we will add it to the new arrobj
                if( (($data->getTableData($eP) !== null)) && (count($data->getTableData($eP)) > 0) ){
                    foreach($data->getTableData($eP) as $val){
                        $this->extendedProperties[$eP] =  $val;
                    }
                }
            }
        }
    }
    
    /**
     * 
     * Check that the available type is in the custom type array
     * 
     * @return bool
     */
    private function isCustomType(): bool{
        if(in_array(strtolower($this->getType()), CC::$availableCustomViews)){
            return true;
        }
        return false;
    }
    
    private function getBasicPropertiesByType(): array{
        //get the necessary properties for the different types
        $propertyData = array();
        $propertyData = CC::getCustomDetailViewTemplateDataProperties($this->getType());
        //create the basic and extended properties arrays
        if( count($propertyData) > 0){
            if(isset($propertyData['basicProperties'])){
                return $propertyData['basicProperties'];
            }
        }
        return array(); 
    }
    
    private function getExtendedPropertiesByType() : array {
        //get the necessary properties for the different types
        $propertyData = array();
        $propertyData = CC::getCustomDetailViewTemplateDataProperties($this->getType());
        //create the basic and extended properties arrays
        if( count($propertyData) > 0){
            if(isset($propertyData['extendedProperties'])){
                return $propertyData['extendedProperties'];
            }
        }
        return array(); 
    }


    
    public function getUri(){
        return $this->uri;
    }
    
    public function getTitle(){
        return $this->title;
    }
    
    public function getType(){
        return $this->type;
    }
    
    public function getPid(){
        return $this->pid;
    }
    
    public function getIdentifiers(){
        return $this->identifiers;
    }
    
    /**
     * Get the Non ACDH identifiers
     * 
     * @return array
     */
    public function getNonAcdhIdentifiers(){
        $result = array();
        if(count($this->identifiers) > 0){
            foreach ($this->identifiers as $id){
                if (strpos($id, 'https://id.acdh.oeaw.ac.at') === false) {
                    array_push($result, $id);
                }
            }
        }
        return $result;
    }
    
    public function getInsideUri(){
        return $this->insideUri;
    }
    
    public function getTypeUri(){
        return $this->typeUri;
    }
    
    public function getBasicProperties(){
        return $this->basicProperties;
    }
    
    public function getExtendedProperties(){
        return $this->extendedProperties;
    }
    
    public function getAccessRestriction(){
        return $this->accessRestriction;
    }
}
