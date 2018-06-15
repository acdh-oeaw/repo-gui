<?php

namespace Drupal\oeaw\Model;

/**
 * 
 * This object is contains the necessary data for the oeaw_detail Resource.
 * Also the special views and child elements will also use this object to 
 * create their own data
 * 
 */
class OeawResource {
    
    private $uri;
    private $insideUri;
    private $fedoraUri;
    private $identifiers = array();
    private $title;
    private $pid;
    private $type;
    private $typeUri;
    private $imageUrl;
    private $table = array();
    public $errors = array();
    
    public function __construct(\ArrayObject $arrayObj) {
        
        if (is_object($arrayObj) || !empty($arrayObj)) {
            $objIterator = $arrayObj->getIterator();
            
            while($objIterator->valid()) {
                ($objIterator->key() == "uri") ? $this->uri = $objIterator->current() : NULL;
                ($objIterator->key() == "insideUri") ? $this->insideUri = $objIterator->current() : NULL;
                ($objIterator->key() == "fedoraUri") ? $this->fedoraUri = $objIterator->current() : NULL;
                ($objIterator->key() == "identifiers") ? $this->identifiers = $objIterator->current() : NULL ;
                ($objIterator->key() == "title") ? $this->title = $objIterator->current() : NULL;
                ($objIterator->key() == "pid") ? $this->pid = $objIterator->current() : NULL;
                ($objIterator->key() == "type") ? $this->type = $objIterator->current() :  NULL;
                ($objIterator->key() == "typeUri") ? $this->typeUri = $objIterator->current() :  NULL;
                ($objIterator->key() == "imageUrl") ? $this->imageUrl = $objIterator->current() : NULL ;
                ($objIterator->key() == "table") ? $this->table = $objIterator->current() : NULL ;
                
                $objIterator->next();
            }
        }else {
            throw new \ErrorException("ArrayObject is not an object!");
        }
        
        $this->checkEmptyVariables();
        
        if(count($this->errors) > 0){
            throw new \ErrorException("You have errors during the OeawResource Object initilaizing! Following data are missing: ".print_r($this->errors, true));
        }
        
    }
    
    private function checkEmptyVariables() {
        if(empty($this->uri)){ array_push($this->errors, "uri"); }
        if(empty($this->title)){ array_push($this->errors, "title");  }
        if(empty($this->insideUri)){ array_push($this->errors, "insideUri");  }
        if(empty($this->fedoraUri)){ array_push($this->errors, "fedoraUri");  }
        if(empty($this->identifiers)){ array_push($this->errors, "identifiers");  }
        if(empty($this->title)){ array_push($this->errors, "title");  }
        if(empty($this->type)){ array_push($this->errors, "type");  }
        if(empty($this->typeUri)){ array_push($this->errors, "typeUri");  }
        if(empty($this->table)){ array_push($this->errors, "table");  }
        
    }
    
    public function getUri(){
        return $this->uri;
    }
    
    public function getInsideUri(){
        return $this->insideUri;
    }
    
    public function getIdentifiers(){
        return $this->identifiers;
    }
    
    public function getFedoraUri(){
        return $this->fedoraUri;
    }
    
    public function getTitle(){
        return $this->title;
    }
    
    public function getType(){
        return $this->type;
    }
    
    public function getTypeUri(){
        return $this->typeUri;
    }
    
    public function getTable(){
        return $this->table;
    }
    
    public function getTableData(string $prop){
        if(isset($this->table[$prop])){
            return $this->table[$prop];
        }
    }
    
    public function setTableData(string $prop, array $data){
        if(isset($this->table[$prop])){
            $this->table[$prop] = $data;
        }
    }
    
}
