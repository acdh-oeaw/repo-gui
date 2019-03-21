<?php

namespace Drupal\oeaw\Model;

use Drupal\oeaw\OeawFunctions;
use Drupal\oeaw\ConfigConstants;
use Drupal\oeaw\Helper\Helper;

use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\FedoraResource;

use acdhOeaw\util\RepoConfig as RC;

class ModelFunctions
{
    public function __construct()
    {
        \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');
    }
    
    //the date formats for the formatting possibilities
    private $dateFormats = array(
        'Y-m-d' => array('YEAR', 'MONTH', 'DAY'),
        'd-m-Y' => array('DAY', 'MONTH', 'YEAR'),
        'Y' => array('YEAR')
    );
    
    //the properties which can contains multiple languages
    private $langProp = array(
        "https://vocabs.acdh.oeaw.ac.at/schema#hasCity",
        "https://vocabs.acdh.oeaw.ac.at/schema#hasCountry",
        "https://vocabs.acdh.oeaw.ac.at/schema#hasRegion",
        "https://vocabs.acdh.oeaw.ac.at/schema#hasAlternativeTitle",
        "https://vocabs.acdh.oeaw.ac.at/schema#hasDescription",
        "https://vocabs.acdh.oeaw.ac.at/schema#hasAppliedMethodDescription",
        "https://vocabs.acdh.oeaw.ac.at/schema#hasArrangement",
        "https://vocabs.acdh.oeaw.ac.at/schema#hasCompleteness",
        "https://vocabs.acdh.oeaw.ac.at/schema#hasEditorialPractice",
        "https://vocabs.acdh.oeaw.ac.at/schema#hasExtent",
        "https://vocabs.acdh.oeaw.ac.at/schema#hasNamingScheme",
        "https://vocabs.acdh.oeaw.ac.at/schema#hasNote",
        "https://vocabs.acdh.oeaw.ac.at/schema#hasSeriesInformation",
        "https://vocabs.acdh.oeaw.ac.at/schema#hasTableOfContents",
        "https://vocabs.acdh.oeaw.ac.at/schema#hasTechnicalInfo",
        "https://vocabs.acdh.oeaw.ac.at/schema#hasTemporalCoverage",
        "https://vocabs.acdh.oeaw.ac.at/schema#hasTitle",
        "http://www.w3.org/2004/02/skos/core#altLabel",
        "http://www.w3.org/2000/01/rdf-schema#comment"
    );
    
    
    /**
     *
     * This func will format the date on the sparql result based on the definied format
     *
     * @param string $inputVar : Input field name
     * @param string $outputVar : output field name
     * @param string $format : the date format based on the $dateFormats
     * @return string
     */
    public function convertFieldDate(string $inputVar, string $outputVar, string $format): string
    {
        $result = "";
        //if the defined format is not in the allowed formats then we set up a default one
        if (!array_key_exists($format, $this->dateFormats)) {
            $format = 'd-m-Y';
        }
        
        $count = count($this->dateFormats[$format]);
        $result = ' (CONCAT ( ';
        for ($x = 0; $x <= count($this->dateFormats[$format]) - 1; $x++) {
            //setup the vars
            $result .= 'STR( '.$this->dateFormats[$format][$x].'(?'.$inputVar.'))';
            //setup the
            if ((count($this->dateFormats[$format]) - 1 > 1) && ($x < count($this->dateFormats[$format]) - 1)) {
                $result .= ', "-", ';
            }
        }
        $result .= ') as ?'.$outputVar.')';
        
        return $result;
    }
    
    //use : Uriparam = ?uri , propertyUri = <https://vocabs.acdh.oeaw.ac.at/schema#hasTitle>, $valueParam = ?title
    
    
    public function filterLanguage(string $uriParam, string $propertyUri, string $valueParam, string $lang = "en", bool $optional = false): string
    {
        $return = "";
        $lang = strtolower($lang);
        
        //if the property
        if (in_array($propertyUri, $this->langProp)) {
            if ($optional == true) {
                $return .= " OPTIONAL { ";
            }
            $return .= "?".$uriParam." <".$propertyUri."> ?defaultValue".$valueParam." . "
                    . " OPTIONAL { "
                    . "?".$uriParam." <".$propertyUri."> ?langValue".$valueParam." . "
                    . "FILTER regex(lang(?langValue".$valueParam."), '".$lang."','i') ."
                    . " } "
                    . " BIND( IF( !bound(?langValue".$valueParam.") , ?defaultValue".$valueParam.", ?langValue".$valueParam.") as ?".$valueParam." ) .  ";
            if ($optional == true) {
                $return .= " } ";
            }
        } else {
            if ($optional == true) {
                $return .= " OPTIONAL { ";
            }
            $return .= "?".$uriParam." <".$propertyUri."> ?".$valueParam." . ";
            if ($optional == true) {
                $return .= " } ";
            }
        }
        return $return;
    }
}
