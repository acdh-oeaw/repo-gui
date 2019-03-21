<?php

namespace Drupal\oeaw\Model;

use Drupal\oeaw\ConfigConstants as CC;
use acdhOeaw\util\RepoConfig as RC;

/**
 *
 * This object will contains the oeaw_detail resource special data
 * For example: the person or organisation special properties
 * The list of these special properties can found under the ConfigConstants::$availableCustomViews
 *
 */
class OeawResourceCustomData
{
    private $uri = "";
    private $title = "";
    private $type = "";
    private $pid = "";
    private $identifiers = array();
    private $insideUri = "";
    private $typeUri = "";
    private $basicProperties = array();
    private $bpKeys = array();
    private $extendedProperties = array();
    private $epKeys = array();
    private $accessRestriction = 'public';
    public $errors = array();
    
    public static $availableCustomViews = array(
        "person", "project", "organisation", "publication", "place"
    );
    
    /**
     * Set up the necessary properties and init the obj
     *
     * @param \ArrayObject $arrayObj
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
                ($objIterator->key() == "title") ? $this->title = $objIterator->current() : null;
                ($objIterator->key() == "type") ? $this->type = $objIterator->current() : null;
                ($objIterator->key() == "pid") ? $this->pid = $objIterator->current() : null;
                ($objIterator->key() == "identifiers") ? $this->identifiers = $objIterator->current() : null;
                ($objIterator->key() == "insideUri") ? $this->insideUri = $objIterator->current() : null;
                ($objIterator->key() == "typeUri") ? $this->typeUri = $objIterator->current() : null;
                ($objIterator->key() == "basicProperties") ? $this->basicProperties = $objIterator->current() : null;
                ($objIterator->key() == "extendedProperties") ? $this->extendedProperties = $objIterator->current() :  null;
                ($objIterator->key() == "accessRestriction") ? $this->accessRestriction = $objIterator->current() : 'public' ;
                $objIterator->next();
            }
        } else {
            throw new \ErrorException(t('ArrayObject').' '.t('Error').' -> OeawResourceCustomData construct');
        }
        $this->checkEmptyVariables();
        if (count($this->errors) > 0) {
            throw new \ErrorException(
                t('Init').' '.t('Error').' : OeawResourceCustomData.'.' '.t(' Empty').' '.t('Data').': '.print_r($this->errors, true)
            );
        }
        //setup the basic and extended prperties values
        $this->setupBasicExtendedKeys();
    }
    
    /**
     * Check the necessary properties for the obj init.
     */
    private function checkEmptyVariables()
    {
        if (empty($this->uri)) {
            array_push($this->errors, "uri");
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
        if (empty($this->identifiers)) {
            array_push($this->errors, "identifiers");
        }
        if (empty($this->insideUri)) {
            array_push($this->errors, "insideUri");
        }
    }
    
    /**
     * Setup the basic properties by the type
     */
    private function setupBasicExtendedKeys()
    {
        if ($this->isCustomType() === true) {
            $this->bpKeys = $this->getBasicPropertiesByType();
            $this->epKeys = $this->getExtendedPropertiesByType();
        }
    }
    
    /**
     *
     * Setup the extended properties by the type
     *
     * @param \Drupal\oeaw\Model\OeawResource $data
     */
    public function setupBasicExtendedData(\Drupal\oeaw\Model\OeawResource $data)
    {
        if (count($this->bpKeys) > 0) {
            foreach ($this->bpKeys as $bP) {
                //if we have the basic prop tin our object then we will add it to the new arrobj
                if ((($data->getTableData($bP) !== null)) && (count($data->getTableData($bP)) > 0)) {
                    foreach ($data->getTableData($bP) as $val) {
                        $this->basicProperties[$bP] =  $val;
                    }
                }
            }
        }
        if (count($this->epKeys) > 0) {
            foreach ($this->epKeys as $eP) {
                //if we have the basic prop tin our object then we will add it to the new arrobj
                if ((($data->getTableData($eP) !== null)) && (count($data->getTableData($eP)) > 0)) {
                    foreach ($data->getTableData($eP) as $val) {
                        $this->extendedProperties[$eP] =  $val;
                    }
                }
            }
        }
    }
    
    /**
     * Check that the available type is in the custom type array
     *
     * @return bool
     */
    private function isCustomType(): bool
    {
        if (in_array(strtolower($this->getType()), CC::$availableCustomViews)) {
            return true;
        }
        return false;
    }
    
    /**
     * Get the basic properties by the type
     *
     * @return array
     */
    private function getBasicPropertiesByType(): array
    {
        //get the necessary properties for the different types
        $propertyData = array();
        $propertyData = CC::getCustomDetailViewTemplateDataProperties($this->getType());
        //create the basic and extended properties arrays
        if (count($propertyData) > 0) {
            if (isset($propertyData['basicProperties'])) {
                return $propertyData['basicProperties'];
            }
        }
        return array();
    }
    
    /**
     * Get the extended properties by the type
     *
     * @return array
     */
    private function getExtendedPropertiesByType() : array
    {
        //get the necessary properties for the different types
        $propertyData = array();
        $propertyData = CC::getCustomDetailViewTemplateDataProperties($this->getType());
        //create the basic and extended properties arrays
        if (count($propertyData) > 0) {
            if (isset($propertyData['extendedProperties'])) {
                return $propertyData['extendedProperties'];
            }
        }
        return array();
    }

    /**
     * The resource uri
     *
     * @return type
     */
    public function getUri(): string
    {
        return $this->uri;
    }
    
    /**
     * resource title
     *
     * @return type
     */
    public function getTitle(): string
    {
        return $this->title;
    }
    
    /**
     * Resource type. f.e.: Collection
     *
     * @return type
     */
    public function getType(): string
    {
        return $this->type;
    }
    
    /**
     * Resource PID
     * @return type
     */
    public function getPid(): string
    {
        return $this->pid;
    }
    
    /**
     * Resource identifiers array
     * @return type
     */
    public function getIdentifiers(): array
    {
        return $this->identifiers;
    }
    
    /**
     * Get the Non ACDH identifiers
     *
     * @return array
     */
    public function getNonAcdhIdentifiers(): array
    {
        $result = array();
        if (count($this->identifiers) > 0) {
            foreach ($this->identifiers as $id) {
                if (strpos($id, 'https://id.acdh.oeaw.ac.at') === false) {
                    array_push($result, $id);
                }
            }
        }
        return $result;
    }
    
    /**
     * ARCHE supported inside url for the detail view display
     * @return type
     */
    public function getInsideUri(): string
    {
        return $this->insideUri;
    }
    
    /**
     * Resource type URL
     * @return type
     */
    public function getTypeUri(): string
    {
        return $this->typeUri;
    }
    
    /**
     * Resource Basic properties array for the special views
     * @return type
     */
    public function getBasicProperties(): array
    {
        return $this->basicProperties;
    }
    
    /**
     * Resource Extended properties array for the special views
     * @return type
     */
    public function getExtendedProperties(): array
    {
        return $this->extendedProperties;
    }
    
    /**
     * Resource access restrictions
     * @return string
     */
    public function getAccessRestriction(): string
    {
        if ((strtolower($this->getType()) == "collection") ||
            (strtolower($this->getType()) == "resource") ||
            (strtolower($this->getType()) == "metadata")) {
            return $this->accessRestriction;
        }
        return '';
    }
    
    /**
     * Get the ACDH identifiers
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
}
