<?php
namespace Drupal\oeaw;

class ConnData 
{
    /*      
     * prefixes to sparql querys
     * 
    */    
    public static $prefixes = ''
            . 'PREFIX dct: <http://purl.org/dc/terms/> '
            . 'PREFIX ebucore: <http://www.ebu.ch/metadata/ontologies/ebucore/ebucore#> '
            . 'PREFIX premis: <http://www.loc.gov/premis/rdf/v1#> '
            . 'PREFIX acdh: <https://vocabs.acdh.oeaw.ac.at/#> '
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
        "https://vocabs.acdh.oeaw.ac.at/#" => "acdh",        
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
    
    public static $imageProperty = "http://xmlns.com/foaf/0.1/Image";
    public static $imageThumbnail = "http://xmlns.com/foaf/0.1/thumbnail";
    public static $rdfType = "http://www.w3.org/1999/02/22-rdf-syntax-ns#type";
    public static $fedoraBinary = "http://fedora.info/definitions/v4/repository#Binary";
    public static $foafName = "http://xmlns.com/foaf/0.1/name";
    public static $acdhQuery = "https://vocabs.acdh.oeaw.ac.at/#query";
    public static $acdhQueryType = "https://vocabs.acdh.oeaw.ac.at/#querytype";
    
    //For search result variables
    //public static $title = "http://purl.org/dc/elements/1.1/title";
    public static $title = "https://vocabs.acdh.oeaw.ac.at/#hasTitle";
    public static $description = "https://vocabs.acdh.oeaw.ac.at/#hasDescription";
    public static $hasDissService = "https://vocabs.acdh.oeaw.ac.at/#hasDissService";
    public static $contributor = "https://vocabs.acdh.oeaw.ac.at/#hasContributor";
    public static $author = "https://vocabs.acdh.oeaw.ac.at/#hasAuthor";
    public static $creationdate = "http://fedora.info/definitions/v4/repository#created";
    public static $isPartOf = "https://vocabs.acdh.oeaw.ac.at/#isPartOf";
    public static $providesMime = "https://vocabs.acdh.oeaw.ac.at/#providesMime";
    
    
    //special RDF types
    public static $acdhNamespace = "https://vocabs.acdh.oeaw.ac.at/#";
    public static $person = "https://vocabs.acdh.oeaw.ac.at/#Person";
    public static $hasLastName = "https://vocabs.acdh.oeaw.ac.at/#hasLastName";
    public static $hasFirstName = "https://vocabs.acdh.oeaw.ac.at/#hasFirstName";
      
    
}

