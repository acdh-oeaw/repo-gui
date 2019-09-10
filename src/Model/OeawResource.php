<?php

namespace Drupal\oeaw\Model;

use acdhOeaw\util\RepoConfig as RC;

/**
 *
 * This object is contains the necessary data for the oeaw_detail Resource.
 * Also the special views and child elements will also use this object to
 * create their own data
 *
 */
class OeawResource implements \JsonSerializable
{
    private $uri = "";
    private $insideUri = "";
    private $fedoraUri = "";
    private $identifiers = array();
    private $title = "";
    private $pid = "";
    private $type = "";
    private $typeUri = "";
    private $imageUrl = "";
    private $availableDate = "";
    private $highlighting = array();
    private $accessRestriction = 'public';
    private $table = array();
    public $errors = array();
    
    /**
     * Set up the properties and init the obj
     *
     * @param \ArrayObject $arrayObj
     * @param type $cfg
     * @throws \ErrorException
     */
    public function __construct(\ArrayObject $arrayObj, $cfg = null)
    {
        if ($cfg == null) {
            \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');
        } else {
            \acdhOeaw\util\RepoConfig::init($cfg);
        }
        
        if (is_object($arrayObj) || !empty($arrayObj)) {
            $objIterator = $arrayObj->getIterator();
            
            while ($objIterator->valid()) {
                ($objIterator->key() == "uri") ? $this->uri = $objIterator->current() : null;
                ($objIterator->key() == "insideUri") ? $this->insideUri = $objIterator->current() : null;
                ($objIterator->key() == "fedoraUri") ? $this->fedoraUri = $objIterator->current() : null;
                ($objIterator->key() == "identifiers") ? $this->identifiers = $objIterator->current() : null ;
                ($objIterator->key() == "title") ? $this->title = $objIterator->current() : null;
                ($objIterator->key() == "pid") ? $this->pid = $objIterator->current() : null;
                ($objIterator->key() == "type") ? $this->type = $objIterator->current() :  null;
                ($objIterator->key() == "typeUri") ? $this->typeUri = $objIterator->current() :  null;
                ($objIterator->key() == "imageUrl") ? $this->imageUrl = $objIterator->current() : null ;
                ($objIterator->key() == "availableDate") ? $this->availableDate = $objIterator->current() : null ;
                ($objIterator->key() == "accessRestriction") ? $this->accessRestriction = $objIterator->current() : 'public' ;
                ($objIterator->key() == "highlighting") ? $this->highlighting = $objIterator->current() : null ;
                ($objIterator->key() == "table") ? $this->table = $objIterator->current() : null ;
                
                $objIterator->next();
            }
        } else {
            throw new \ErrorException(t('ArrayObject').' '.t('Error').' -> OeawResource construct');
        }
        
        $this->checkEmptyVariables();
        if (count($this->errors) > 0) {
            throw new \ErrorException(
                t('Init').' '.t('Error').' : OeawResource.'.' '.t(' Empty').' '.t('Data').': '.print_r($this->errors, true)
            );
        }
    }
    
    public function jsonSerialize()
    {
        return get_object_vars($this);
        /*
        return [
            'something' => $this-&gt;something,
            'protected' => $this-&gt;get_protected(),
            'private' => $this-&gt;get_private()
        ];*/
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
        if (empty($this->insideUri)) {
            array_push($this->errors, "insideUri");
        }
        if (empty($this->fedoraUri)) {
            array_push($this->errors, "fedoraUri");
        }
        if (empty($this->identifiers)) {
            array_push($this->errors, "identifiers");
        }
        if (empty($this->title)) {
            array_push($this->errors, "title");
        }
        if (empty($this->type)) {
            array_push($this->errors, "type");
        }
        if (empty($this->typeUri)) {
            array_push($this->errors, "typeUri");
        }
        if (empty($this->table)) {
            array_push($this->errors, "table");
        }
    }
    
    /**
     * Resource URI
     * @return type
     */
    public function getUri()
    {
        return $this->uri;
    }
    
    /**
     * ARCHE supported inside url for the detail view display
     * @return type
     */
    public function getInsideUri(): string
    {
        return $this->insideUri;
    }
    
    public function getIdentifiers(): array
    {
        return $this->identifiers;
    }
    
    public function getAcdhIdentifier(): string
    {
        if (count($this->identifiers) > 0) {
            $uuid = "";
            foreach ($this->identifiers as $id) {
                if (strpos($id, RC::get('fedoraUuidNamespace')) !== false) {
                    $uuid = $id;
                //if the identifier is the normal acdh identifier then return it
                } elseif (strpos($id, RC::get('fedoraIdNamespace')) !== false) {
                    $this->insideUri = $id;
                    return $id;
                }
            }
            if (!empty($uuid)) {
                return $uuid;
            }
        }
        return "";
    }
    
    public function getFedoraUri(): string
    {
        return $this->fedoraUri;
    }
    
    public function getTitle(): string
    {
        return $this->title;
    }
    
    public function getType(): string
    {
        return $this->type;
    }
    
    public function getTypeUri(): string
    {
        return $this->typeUri;
    }
    
    public function getImageUrl(): string
    {
        return $this->imageUrl;
    }
    
    public function getPID(): string
    {
        return $this->pid;
    }
    
    public function getTable(): array
    {
        return $this->table;
    }
    
    public function getAvailableDate(): string
    {
        return $this->availableDate;
    }
    
    public function getAccessRestriction(): string
    {
        if ((strtolower($this->getType()) == "collection") ||
            (strtolower($this->getType()) == "resource") ||
            (strtolower($this->getType()) == "metadata")) {
            if (isset($this->accessRestriction['title'])) {
                return $this->accessRestriction['title'];
            } elseif (isset($this->accessRestriction['uri'])) {
                return $this->accessRestriction['uri'];
            } else {
                return $this->accessRestriction;
            }
        }
        return '';
    }
    
    /**
     *
     * Get the property based info from the object table
     * The properties are in a shortcut format in the table
     * F.e: acdh:hasAvailableDate
     *
     * @param string $prop
     * @return type
     */
    public function getTableData(string $prop)
    {
        if (isset($this->table[$prop])) {
            return $this->table[$prop];
        }
    }
    
    public function setTableData(string $prop, array $data) : bool
    {
        if (isset($this->table[$prop])) {
            $this->table[$prop] = $data;
            return true;
        } else {
            return false;
        }
    }
    
    public function getHighlighting(): array
    {
        return $this->highlighting;
    }
}
