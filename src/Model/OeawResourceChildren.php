<?php

namespace Drupal\oeaw\Model;

/**
 *
 * This object will contains the oeaw_detail Resource Child elements
 *
 */
class OeawResourceChildren
{
    private $uri = "";
    private $title = "";
    private $pid = "";
    private $description = "";
    private $typeUri = "";
    private $identifier = "";
    private $insideUri = "";
    private $typeName = "";
    private $accessRestriction = 'public';
    public $errors = array();
    
    /**
     * Set up the properties and init the obj
     *
     * @param \ArrayObject $arrayObj
     * @throws \ErrorException
     */
    public function __construct(\ArrayObject $arrayObj)
    {
        if (is_object($arrayObj) || !empty($arrayObj)) {
            $objIterator = $arrayObj->getIterator();
            
            while ($objIterator->valid()) {
                ($objIterator->key() == "uri") ? $this->uri = $objIterator->current() : null;
                ($objIterator->key() == "title") ? $this->title = $objIterator->current() : null;
                ($objIterator->key() == "pid") ? $this->pid = $objIterator->current() : null;
                ($objIterator->key() == "description") ? $this->description = $objIterator->current() : null;
                ($objIterator->key() == "typeUri") ? $this->typeUri = $objIterator->current() :  null;
                ($objIterator->key() == "identifier") ? $this->identifier = $objIterator->current() : null ;
                ($objIterator->key() == "insideUri") ? $this->insideUri = $objIterator->current() : null;
                ($objIterator->key() == "typeName") ? $this->typeName = $objIterator->current() : null;
                ($objIterator->key() == "accessRestriction") ? $this->accessRestriction = $objIterator->current() : 'public' ;
                $objIterator->next();
            }
        } else {
            throw new \ErrorException(t('ArrayObject').' '.t('Error').' -> OeawResourceChildren construct');
        }
        
        $this->checkEmptyVariables();
        if (count($this->errors) > 0) {
            throw new \ErrorException(
                t('Init').' '.t('Error').' : OeawResourceChildren.'.' '.t(' Empty').' '.t('Data').': '.print_r($this->errors, true)
            );
        }
    }
    
    /**
     *  Check the necessary properties for the obj init
     */
    private function checkEmptyVariables()
    {
        if (empty($this->uri)) {
            array_push($this->errors, "uri");
        }
        if (empty($this->title)) {
            array_push($this->errors, "title");
        }
        //if(empty($this->description)){ array_push($this->errors, "description");  }
        if (empty($this->typeUri)) {
            array_push($this->errors, "typeUri");
        }
        if (empty($this->typeName)) {
            array_push($this->errors, "typeName");
        }
        if (empty($this->identifier)) {
            array_push($this->errors, "identifier");
        }
        if (empty($this->insideUri)) {
            array_push($this->errors, "insideUri");
        }
    }
    
    public function getUri(): string
    {
        return $this->uri;
    }
    
    public function getTitle(): string
    {
        return $this->title;
    }
    
    public function getPid(): string
    {
        return $this->pid;
    }
    
    public function getDescription(): string
    {
        return $this->description;
    }
    
    public function getTypeUri() : string
    {
        return $this->typeUri;
    }
    
    public function getIdentifier()
    {
        return $this->identifier;
    }
    
    public function getInsideUri()
    {
        return $this->insideUri;
    }
    
    public function getTypeName()
    {
        return $this->typeName;
    }
    
    public function getAccessRestriction()
    {
        if ((strtolower($this->getTypeName()) == "collection") ||
            (strtolower($this->getTypeName()) == "resource") ||
            (strtolower($this->getTypeName()) == "metadata")) {
            return $this->accessRestriction;
        }
        return '';
    }
}
