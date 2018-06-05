<?php

namespace Drupal\oeaw\Model;

/**
 * 
 * This object will contains the oeaw_detail resource special data
 * For example: the person or organisation special properties
 * The list of these special properties can found under the ConfigConstants::$availableCustomViews
 * 
 */
class OeawResourceCustomData {
    
    public $uri;
    public $title;
    public $type;
    public $pid;
    public $identifiers;
    public $insideUri;
    public $typeUri;
    public $basicProperties = array();
    public $extendedProperties = array();
    public $errors = array();
    
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
        if(empty($this->type)){ array_push($this->errors, "type");  }
        if(empty($this->identifiers)){ array_push($this->errors, "identifiers");  }
        if(empty($this->insideUri)){ array_push($this->errors, "insideUri");  }
        if(empty($this->basicProperties)){ array_push($this->errors, "basicProperties");  }
    }
}
