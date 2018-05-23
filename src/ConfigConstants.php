<?php

namespace Drupal\oeaw;

use acdhOeaw\util\RepoConfig as RC;

abstract class ConfigConstants {
    
    public static $prefixes = ''
            . 'PREFIX dct: <http://purl.org/dc/terms/> '
            . 'PREFIX ebucore: <http://www.ebu.ch/metadata/ontologies/ebucore/ebucore#> '
            . 'PREFIX premis: <http://www.loc.gov/premis/rdf/v1#> '
            . 'PREFIX acdh: <https://vocabs.acdh.oeaw.ac.at/schema#> '
            . 'PREFIX fedora: <http://fedora.info/definitions/v4/repository#> '
            . 'PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#> '
            . 'PREFIX owl: <http://www.w3.org/2002/07/owl#>';
    
  
    public static $prefixesToBlazegraph = array(
        "dct" => "http://purl.org/dc/terms/"        
    );
    
    public static $prefixesToChange = array(        
        "http://fedora.info/definitions/v4/repository#" => "fedora",
        "http://www.ebu.ch/metadata/ontologies/ebucore/ebucore#" => "ebucore",
        "http://www.loc.gov/premis/rdf/v1#" => "premis",
        "http://www.jcp.org/jcr/nt/1.0#" => "nt",
        "http://www.w3.org/2000/01/rdf-schema#" => "rdfs",
        "http://www.w3.org/ns/ldp#" => "ldp",
        "http://www.iana.org/assignments/relation/" => "iana",
        "https://vocabs.acdh.oeaw.ac.at/schema#" => "acdh",        
        "https://id.acdh.oeaw.ac.at/" => "acdhID",
        "http://purl.org/dc/elements/1.1/" => "dc",
        "http://purl.org/dc/terms/" => "dct",
        "http://purl.org/dc/terms/" => "dcterms",
        "http://purl.org/dc/terms/" => "dcterm",
        "http://www.w3.org/2002/07/owl#" => "owl",
        "http://xmlns.com/foaf/0.1/" => "foaf",
        "http://www.w3.org/1999/02/22-rdf-syntax-ns#" => "rdf",
        "http://www.w3.org/2004/02/skos/core#" => "skos",
        //"http://xmlns.com/foaf/spec/" => "foaf"
    );
    
    //propertys
    public static $acdhQueryType = "https://vocabs.acdh.oeaw.ac.at/schema#querytype";
    
    
    static function getCustomDetailViewTemplateDataProperties(string $property): array {
        $result = array();
        
        $property = strtolower($property);
        
        if($property == "person"){
            $result['basicProperties'] = array(
                "acdh:hasTitle",
                "acdh:hasIdentifier",
                "acdh:isMember"
            );
                    
            //contact details
            $result['extendedProperties'] =  array(
                "acdh:hasAddressLine1",
                "acdh:hasAddressLine2",
                "acdh:hasCountry",
                "acdh:hasRegion",
                "acdh:hasCity",
                "acdh:hasEmail",
                "acdh:hasUrl",
                "acdh:hasPostcode"
            );
            return $result;
        }
        
        if($property == "project"){
            $result['basicProperties'] = array(
                "acdh:hasTitle",
                "acdh:hasIdentifier",
                "acdh:hasAlternativeTitle",
                "acdh:hasUrl",
                "acdh:hasContact",
                "acdh:hasFunder",
                "acdh:hasPrincipalInvestigator",
                "acdh:hasStartDate",
                "acdh:hasEndDate",
                "acdh:hasLifeCycleStatus",
                "acdh:hasLanguage"
            );
                    
            $result['extendedProperties'] = array(
                "acdh:hasRelatedDiscipline",
                "acdh:hasSubject",
                "acdh:hasActor",
                "acdh:hasSpatialCoverage",
                "acdh:hasTemporalCoverage",
                "acdh:hasCoverageStartDate",
                "acdh:hasCoverageEndDate",
                "acdh:hasAppliedMethod",
                "acdh:hasAppliedMethodDescription",
                "acdh:hasTechnicalInfo",
                "acdh:hasEditorialPractice",
                "acdh:hasNote"
            );
            return $result;
        }
        
        if($property == "organisation"){
            $result['basicProperties'] = array(
                "acdh:hasTitle",
                "acdh:hasAlternativeTitle",
                "acdh:hasIdentifier",
                "acdh:hasAddressLine1",
                "acdh:hasAddressLine2",
                "acdh:hasPostcode",
                "acdh:hasCity",
                "acdh:hasRegion",
                "acdh:hasCountry",
                "acdh:hasUrl",
                "acdh:hasEmail"
            );
            return $result;
        }

        if($property == "place"){
            $result['basicProperties'] = array(
                "acdh:hasTitle",
                "acdh:hasAlternativeTitle",
                "acdh:hasIdentifier",
                "acdh:hasAddressLine1",
                "acdh:hasAddressLine2",
                "acdh:hasPostcode",
                "acdh:hasCity",
                "acdh:hasRegion",
                "acdh:hasCountry",
                "acdh:hasPart",
                "acdh:isPartOf",
                "acdh:isIdenticalTo"
            );

            $result['extendedProperties'] = array(
                "acdh:hasLatitude",
                "acdh:hasLongitude",
                "acdh:hasWKT"
            );
            return $result;
        }
        
        if($property == "publication"){
            $result['basicProperties'] = array(
                "acdh:hasTitle",
                "acdh:hasAlternativeTitle",
                "acdh:hasIdentifier",
                "acdh:hasAuthor",
                "acdh:hasEditor",
                "acdh:hasSeriesInformation",
                "acdh:hasPages",
                "acdh:hasRegion",
                "acdh:hasCity",
                "acdh:hasPublisher",
                "acdh:isPartOf",
                "acdh:hasNonLinkedIdentifier",
                "acdh:hasUrl",
                "acdh:hasEditorialPractice",
                "acdh:hasNote",
                "acdh:hasLanguage"
            );
            
            return $result;
        }
        
        return $result;
    }
    
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
