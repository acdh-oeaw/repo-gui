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
    
    public $uri;
    public $insideUri;
    public $fedoraUri;
    public $identifiers = array();
    public $title;
    public $pid;
    public $type;
    public $typeUri;
    public $imageUrl;
    public $table = array();
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
    
    
}
