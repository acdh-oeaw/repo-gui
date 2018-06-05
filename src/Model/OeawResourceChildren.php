<?php

namespace Drupal\oeaw\Model;


/**
 * 
 * This object will contains the oeaw_detail Resource Child elements
 * 
 */
class OeawResourceChildren {
    
    public $uri;
    public $title;
    public $pid;
    public $description;
    public $types;
    public $identifier;
    public $insideUri;
    public $typeName;
    public $errors = array();
    
    public function __construct(\ArrayObject $arrayObj) {
        
        if (is_object($arrayObj) || !empty($arrayObj)) {
            $objIterator = $arrayObj->getIterator();
            
            while($objIterator->valid()) {
                ($objIterator->key() == "uri") ? $this->uri = $objIterator->current() : NULL;
                ($objIterator->key() == "title") ? $this->title = $objIterator->current() : NULL;
                ($objIterator->key() == "pid") ? $this->pid = $objIterator->current() : NULL;
                ($objIterator->key() == "description") ? $this->description = $objIterator->current() : NULL;
                ($objIterator->key() == "types") ? $this->types = $objIterator->current() :  NULL;
                ($objIterator->key() == "identifier") ? $this->identifier = $objIterator->current() : NULL ;
                ($objIterator->key() == "insideUri") ? $this->insideUri = $objIterator->current() : NULL;
                ($objIterator->key() == "typeName") ? $this->typeName = $objIterator->current() : NULL;
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
        //if(empty($this->description)){ array_push($this->errors, "description");  }
        if(empty($this->types)){ array_push($this->errors, "types");  }
        if(empty($this->identifier)){ array_push($this->errors, "identifier");  }
        if(empty($this->insideUri)){ array_push($this->errors, "insideUri");  }
    }
    
    
}
