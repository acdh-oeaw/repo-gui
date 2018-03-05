<?php

namespace Drupal\oeaw;

use acdhOeaw\util\RepoConfig as RC;

abstract class ConfigConstants {
    
    
    
    /**
     * 
     * Get the necessary properties for the ChildViewGeneraton, based on the root 
     * resource property (fe: Person, Organisation, etc..)
     * 
     * OeawFunctions->generateChildViewData()
     * 
     * @param string $propertyUri
     * @return array
     */
    static function getDetailChildViewProperties(string $propertyUri): array {
        
        $result = array();
        
        //organisation
        if($propertyUri == RC::get('fedoraOrganisationClass')){
            $result = 
                array(
                    RC::get('drupalHasContributor'), 
                    RC::get('drupalHasFunder'), 
                    RC::get('fedoraHostingProp'), 
                    RC::get('drupalHasOwner'), 
                    RC::get('drupalHasLicensor'), 
                    RC::get('drupalHasRightsholder')
                );
        }
        //publication
        if($propertyUri == RC::get('drupalPublication')){
            $result = 
                array(
                    RC::get('drupalHasDerivedPublication'), 
                    RC::get('drupalHasSource'), 
                    RC::get('drupalHasReferencedBy')
                );
        }
        //person
        if($propertyUri == RC::get('drupalPerson')){
            $result = 
                array(
                    RC::get('drupalHasContributor')
                );
        }
        
        //concept
        if($propertyUri == RC::get('drupalConcept')){
            $result = 
                array(
                    RC::get('drupalSkosNarrower')
                );
        }
        
        //project
        if($propertyUri == RC::get('drupalProject')){
            $result = 
                array(
                    RC::get('drupalRelatedProject')
                );
        }
        //institute
        if($propertyUri == RC::get('drupalInstitute')){
            $result = 
                array(
                    RC::get('drupalHasMember')
                );
        }
        
        //place
        if($propertyUri == RC::get('drupalPlace')){
            $result = 
                array(
                    RC::get('drupalHasSpatialCoverage')
                );
        }
        
        return $result;
        
        
    }
    
    
    
    
    
}
