<?php

namespace Drupal\oeaw\Helper;

use Drupal\Core\Url;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ChangedCommand;
use Drupal\Component\Render\MarkupInterface;

use Drupal\oeaw\Model\OeawStorage;
use Drupal\oeaw\ConfigConstants;
use Drupal\oeaw\Model\OeawCustomSparql;

use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\FedoraResource;
use acdhOeaw\util\RepoConfig as RC;
use EasyRdf\Graph;
use EasyRdf\Resource;

class Helper
{
    public function __construct()
    {
        \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');
    }
    
    
    public static function getAcdhIdentifier(array $identifiers): string
    {
        if (count($identifiers) > 0) {
            foreach ($identifiers as $id) {
                if (strpos($id, RC::get('fedoraUuidNamespace')) !== false) {
                    return $id;
                //if the identifier is the normal acdh identifier then return it
                } elseif (strpos($id, RC::get('fedoraIdNamespace')) !== false) {
                    return $id;
                }
            }
        }
        return "";
    }
    
    /**
     *
     * This function checks that the Resource is a 3dData or not
     *
     * @param array $data
     * @return bool
     */
    public static function check3dData(array $data): bool
    {
        $return = false;
       
        if ((isset($data['ebucore:filename'][0]))
            &&
            ((strpos(strtolower($data['ebucore:filename'][0]), '.nxs') !== false)
            ||
            (strpos(strtolower($data['ebucore:filename'][0]), '.ply') !== false))
            &&
            (isset($data['acdh:hasCategory'][0]) && $data['acdh:hasCategory'][0] =="3dData")
            && (isset($data['premis:hasSize'][0]))
        ) {
            //check the size of the binary, because our 3d viewer can shows only files till 125MB
            if (((int)$data['premis:hasSize'][0] > 0) && ((int)$data['premis:hasSize'][0] < 125829120)) {
                $return = true;
            }
        }
        return $return;
    }
    
    
    /**
    * Generate Loris Url and data for the IIIF Viwer and for the detail view
    *
    * @param string $uri - base64 encoded fedora rest uri
    * @param bool $image
    * @return array
    */
    public static function generateLorisUrl(string $uri, bool $image = false): array
    {
        $result = array();
        if (!$uri) {
            return $result;
        }
        
        $url = "";
        $lorisUrl = RC::get('drupalLorisUrl');
        $domain = "";
        //check which instance we are using
        if (strpos(RC::get('fedoraApiUrl'), 'hephaistos') !== false) {
            $domain = "hephaistos:/rest/";
        } elseif (strpos(RC::get('fedoraApiUrl'), 'minerva') !== false) {
            $domain = "minerva:/rest/";
        } else {
            $domain = "apollo:/rest/";
        }
        
        $resource = explode("/rest/", $uri);
        
        if (isset($resource[1]) && !empty($resource[1])) {
            if ($image == false) {
                $result['imageUrl'] = $lorisUrl.$domain.$resource[1]."/info.json";
            } else {
                $result['imageUrl'] = $lorisUrl.$domain.$resource[1]."/full/500,/0/default.jpg";
            }
            $oeawStorage = new OeawStorage();
            $tRes = $oeawStorage->getResourceTitle($uri);
            if ($tRes[0]["title"]) {
                $result['title'] = $tRes[0]["title"];
            }
            $result['insideUri'] = $uri;
        }
        
        return $result;
    }
    
    /**
    *
    * Get hasPid & create copy link
    * Order of desired URIs:
    * PID > id.acdh > id.acdh/uuid > long gui url
    *
    *
    * @param array $results
    * @return string
    */
    public static function generateNiceUri(\Drupal\oeaw\Model\OeawResource $results): string
    {
        $niceURI = "";
        
        if (!empty($results->getTableData("acdh:hasPid"))) {
            if (isset($results->getTableData("acdh:hasPid")[0]['uri'])) {
                $niceURI = $results->getTableData("acdh:hasPid")[0]['uri'];
            }
        }
        
        if (empty($niceURI)) {
            if (!empty($results->getTableData("acdh:hasIdentifier")) && !empty($results->getTableData("acdh:hasIdentifier"))) {
                $acdhURIs = $results->getTableData("acdh:hasIdentifier");
                //Only one value under acdh:hasIdentifier
                if (isset($acdhURIs["uri"])) {
                    //id.acdh/uuid
                    if (strpos($acdhURIs["uri"], 'id.acdh.oeaw.ac.at/uuid') !== false) {
                        $niceURI = $acdhURIs["uri"];
                    }
                    //id.acdh
                    if (!isset($extras["niceURI"]) && strpos($acdhURIs["uri"], 'id.acdh.oeaw.ac.at') !== false) {
                        $niceURI = $acdhURIs["uri"];
                    }
                }
                //Multiple values under acdh:hasIdentifier
                else {
                    foreach ($acdhURIs as $key => $acdhURI) {
                        if (strpos($acdhURI["uri"], 'id.acdh.oeaw.ac.at/uuid') !== false) {
                            $acdhURIuuid = $acdhURI["uri"];
                        } elseif (strpos($acdhURI["uri"], 'id.acdh.oeaw.ac.at') !== false) {
                            $acdhURIidacdh = $acdhURI["uri"];
                        }
                    }
                    if (isset($acdhURIidacdh)) {
                        $niceURI = $acdhURIidacdh;
                    } elseif (isset($acdhURIuuid)) {
                        $niceURI = $acdhURIuuid;
                    }
                }
            }
        }
        
        return $niceURI;
    }
    
    /**
     *
     * Creates a property uri based on the prefix
     *
     * @param string $prefix
     * @return string
     */
    public static function createUriFromPrefix(string $prefix): string
    {
        if (empty($prefix)) {
            return false;
        }
        
        $res = "";
        
        $newValue = explode(':', $prefix);
        $newPrefix = $newValue[0];
        $newValue =  $newValue[1];
        
        $prefixes = \Drupal\oeaw\ConfigConstants::$prefixesToChange;
        
        foreach ($prefixes as $key => $value) {
            if ($value == $newPrefix) {
                $res = $key.$newValue;
            }
        }
        return $res;
    }
    
    
    /**
    *
    * Create nice format from file sizes
    *
    * @param type $bytes
    * @return string
    */
    public static function formatSizeUnits(string $bytes): string
    {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }

        return $bytes;
    }
    
    /**
     *
     * Checks the multi array by key, and if the key has a duplicated value then
     * it will remove it, the result will be an unique array
     *
     * @param array $array
     * @param string $key
     * @return array
     */
    public static function removeDuplicateValuesFromMultiArrayByKey(array $array, string $key): array
    {
        $temp_array = [];
        foreach ($array as &$v) {
            if (!isset($temp_array[$v[$key]])) {
                $temp_array[$v[$key]] =& $v;
            }
        }
        $array = array_values($temp_array);
        return $array;
    }
    
    /**
     *
     * check that the string is URL
     *
     * @param string $string
     * @return string
     */
    public static function isURL(string $string): string
    {
        $res = "";
        if (filter_var($string, FILTER_VALIDATE_URL)) {
            if (strpos($string, RC::get('fedoraApiUrl')) !== false) {
                $res = base64_encode($string);
            }
            return $res;
        } else {
            return false;
        }
    }
    
    
    /**
     *
     * Calculate the estimated Download time for the collection
     *
     * @param int $binarySize
     * @return string
     */
    public static function estDLTime(int $binarySize): string
    {
        $result = "10";
        if ($binarySize < 1) {
            return $result;
        }
        
        $kb=1024;
        flush();
        $time = explode(" ", microtime());
        $start = $time[0] + $time[1];
        for ($x=0; $x < $kb; $x++) {
            str_pad('', 1024, '.');
            flush();
        }
        $time = explode(" ", microtime());
        $finish = $time[0] + $time[1];
        $deltat = $finish - $start;
        
        $input = (($binarySize / 512) * $deltat);
        $input = floor($input / 1000);
        $seconds = $input;
        
        if ($seconds > 0) {
            //because of the zip time we add
            $result = round($seconds * 1.35) * 4;
            return $result;
        }
        
        return $result;
    }
    
    /**
     *
     * Check the array if there is a string inside it
     *
     * @param array $data
     * @param string $str
     * @return bool
     */
    public static function checkArrayForValue(array $data, string $str):bool
    {
        if (count($data) > 0) {
            foreach ($data as $item) {
                if (strpos($item, $str)!== false) {
                    return true;
                }
            }
        }
        return false;
    }
}
