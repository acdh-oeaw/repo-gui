<?php

namespace Drupal\oeaw\Model;

use acdhOeaw\util\RepoConfig as RC;
use EasyRdf\Graph;
use EasyRdf\Resource;

/**
 *
 * This object is contains the necessary data for the oeaw_detail Resource.
 * Also the special views and child elements will also use this object to
 * create their own data
 *
 */
class OeawResource
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
    private $imageThumbUrl = "";
    private $availableDate = "";
    private $highlighting = array();
    private $accessRestriction = 'public';
    private $accessRestrictionUrlFormat = array();
    private $table = array();
    private $lng;
    public $errors = array();
    private $bz_search = "";
    private $resultProp = "";
    
     
    /**
     * Set up the properties and init the obj
     *
     * @param \ArrayObject $arrayObj
     * @param type $cfg
     * @throws \ErrorException
     */
    public function __construct(\ArrayObject $arrayObj, $cfg = null, string $lng = "en")
    {
        $this->lng = $lng;
        
        if (!$cfg) {
            if (file_exists($_SERVER["DOCUMENT_ROOT"].'/modules/custom/oeaw/config.ini')) {
                \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/custom/oeaw/config.ini');
            } elseif (isset($_SERVER['TRAVIS_BUILD_DIR']) && file_exists($_SERVER['TRAVIS_BUILD_DIR']."/drupal/modules/oeaw/config.unittest.ini")) {
                \acdhOeaw\util\RepoConfig::init($_SERVER['TRAVIS_BUILD_DIR']."/drupal/modules/oeaw/config.unittest.ini");
            }
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
                ($objIterator->key() == "imageThumbUrl") ? $this->imageThumbUrl = $objIterator->current() : null ;
                ($objIterator->key() == "availableDate") ? $this->availableDate = $objIterator->current() : null ;
                ($objIterator->key() == "accessRestriction") ? $this->accessRestriction = $objIterator->current() : 'public' ;
                ($objIterator->key() == "highlighting") ? $this->highlighting = $objIterator->current() : null ;
                ($objIterator->key() == "bz_search") ? $this->bz_search = $objIterator->current() : null ;
                ($objIterator->key() == "resultProp") ? $this->resultProp = $objIterator->current() : null ;
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
    
    /**
     * Get the ACDH identifier string
     *
     * @return string
     */
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
    
    public function getImageThumbUrl(): string
    {
        return $this->imageThumbUrl;
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
    
    /**
     * Get the accesres as a string
     *
     * @return string
     */
    public function getAccessRestriction(): string
    {
        if ((strtolower($this->getType()) == "collection") ||
            (strtolower($this->getType()) == "resource") ||
            (strtolower($this->getType()) == "metadata")) {
            if (isset($this->accessRestriction['uri'])) {
                return $this->accessRestriction['uri'];
            } else {
                return $this->accessRestriction;
            }
        }
        return '';
    }
    
    /**
     * Get the actual restriction and if we have a translation for that, than we will display that
     *
     * @return array
     */
    public function getAccessRestrictionUrlFormat(): array
    {
        if (isset($this->accessRestriction['uri'])) {
            if (isset($this->accessRestriction['title'])) {
                $this->accessRestrictionUrlFormat = $this->accessRestriction;
            } else {
                $this->accessRestrictionUrlFormat = array('uri' => $this->accessRestriction['uri']);
            }
        } else {
            $this->accessRestrictionUrlFormat = array('uri' => $this->accessRestriction);
        }
        return $this->accessRestrictionUrlFormat;
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
    
    /**
     * Change the actual table data for the gui
     *
     * @param string $prop
     * @param array $data
     * @return bool
     */
    public function setTableData(string $prop, array $data) : bool
    {
        if (isset($this->table[$prop])) {
            $this->table[$prop] = $data;
            return true;
        } else {
            return false;
        }
    }
    
    /**
     *
     * Get SOLR highlight text
     *
     * @return array
     */
    public function getHighlighting(): array
    {
        return $this->highlighting;
    }
    
    /**
     *
     * Get the Blazegraph search value
     *
     * @return array
     */
    public function getBzResults(): string
    {
        return $this->bz_search;
    }
    
    /**
     * Get the Blazegraph search property
     *
     * @return string
     */
    public function getResultProp(): string
    {
        return $this->resultProp;
    }
}
