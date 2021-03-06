<?php

namespace Drupal\oeaw;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Entity\Exception;
use Drupal\Core\Ajax\InvokeCommand;

use Drupal\oeaw\Model\OeawStorage;
use Drupal\oeaw\Model\OeawResource;
use Drupal\oeaw\Model\OeawResourceChildren;
use Drupal\oeaw\ConfigConstants as CC;
use Drupal\oeaw\Helper\HelperFunctions as HF;
use Drupal\oeaw\Model\OeawCustomSparql;
use Drupal\oeaw\Cache\CollectionCache;

use acdhOeaw\fedora\dissemination\Service;
use acdhOeaw\fedora\Fedora;
use acdhOeaw\fedora\FedoraResource;
use acdhOeaw\fedora\acl\WebAclRule as WAR;
use acdhOeaw\util\RepoConfig as RC;
use EasyRdf\Graph;
use EasyRdf\Resource;
use Symfony\Component\HttpFoundation\Response;

/**
 * Description of OeawFunctions
 *
 * @author nczirjak
 */
class OeawFunctions
{
    private $langConf;
    
    /**
     * Set up the config file
     * @param type $cfg
     */
    public function __construct($cfg = null)
    {
        $this->langConf = \Drupal::config('oeaw.settings');
        if ($cfg === null) {
            if (isset($_SERVER['TRAVIS_BUILD_DIR']) && file_exists($_SERVER['TRAVIS_BUILD_DIR']."/drupal/modules/oeaw/config.unittest.ini")) {
                \acdhOeaw\util\RepoConfig::init($_SERVER['TRAVIS_BUILD_DIR']."/drupal/modules/oeaw/config.unittest.ini");
            } elseif (file_exists($_SERVER["DOCUMENT_ROOT"].'/modules/custom/oeaw/config.ini')) {
                \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/custom/oeaw/config.ini');
            }
        }
    }
        
    /**
     *
     * Creates the Fedora instance
     *
     * @return Fedora
     */
    public function initFedora(): Fedora
    {
        // setup fedora
        $fedora = array();
        $fedora = new Fedora();
        return $fedora;
    }
    
    /**
     *
     * Check the data array for the PID, identifier or uuid identifier
     *
     * @param array $data
     * @return string
     */
    public function createDetailViewUrl(array $data): string
    {
        //check the PID
        if (isset($data['pid']) && !empty($data['pid'])) {
            if (strpos($data['pid'], RC::get('epicResolver')) !== false) {
                return $data['pid'];
            }
        }
        
        if (isset($data['identifier'])) {
            //if we dont have pid then check the identifiers
            $idArr = explode(",", $data['identifier']);
            $uuid = "";
            foreach ($idArr as $id) {
                //the id contains the acdh uuid
                if (strpos($id, RC::get('fedoraUuidNamespace')) !== false) {
                    return $id;
                }
            }
        }
        
        return "";
    }
  
    
    /**
     *
     * Encode or decode the detail view url
     *
     * @param string $uri
     * @param bool $code : 0 - decode / 1 -encode
     * @return string
    */
    public function detailViewUrlDecodeEncode(string $data, int $code = 0): string
    {
        if (empty($data)) {
            return "";
        }
        
        if ($code == 0) {
            $data = explode(":", $data);
            $identifier = "";

            foreach ($data as $ra) {
                if (strpos($ra, '&') !== false) {
                    $pos = strpos($ra, '&');
                    $ra = substr($ra, 0, $pos);
                    $identifier .= $ra."/";
                } else {
                    $identifier .= $ra."/";
                }
            }
            
            switch (true) {
                case strpos($identifier, 'id.acdh.oeaw.ac.at/uuid/') !== false:
                    $identifier = str_replace('id.acdh.oeaw.ac.at/uuid/', RC::get('fedoraUuidNamespace'), $identifier);
                    $identifier = (substr($identifier, -1) == "/") ? substr_replace($identifier, "", -1) : $identifier;
                    break;
                case strpos($identifier, 'id.acdh.oeaw.ac.at/') !== false:
                    $identifier = str_replace('id.acdh.oeaw.ac.at/', RC::get('fedoraIdNamespace'), $identifier);
                    $identifier = (substr($identifier, -1) == "/") ? substr_replace($identifier, "", -1) : $identifier;
                    break;
                case strpos($identifier, 'hdl.handle.net') !== false:
                    $identifier = str_replace('hdl.handle.net/', RC::get('epicResolver'), $identifier);
                    $identifier = (substr($identifier, -1) == "/") ? substr_replace($identifier, "", -1) : $identifier;
                    $identifier = $this->specialIdentifierToUUID($identifier, true);
                    break;
                case strpos($identifier, 'geonames.org') !== false:
                    $identifier = str_replace('geonames.org/', RC::get('geonamesUrl'), $identifier);
                    $identifier = (substr($identifier, -1) == "/") ? substr_replace($identifier, "", -1) : $identifier;
                    $identifier = $this->specialIdentifierToUUID($identifier);
                    break;
                case strpos($identifier, 'd-nb.info') !== false:
                    $identifier = str_replace('d-nb.info/', RC::get('dnbUrl'), $identifier);
                    $identifier = (substr($identifier, -1) == "/") ? substr_replace($identifier, "", -1) : $identifier;
                    $identifier = $this->specialIdentifierToUUID($identifier);
                    break;
                case strpos($identifier, 'viaf.org/') !== false:
                    $identifier = str_replace('viaf.org/', RC::get('viafUrl'), $identifier);
                    $identifier = (substr($identifier, -1) == "/") ? substr_replace($identifier, "", -1) : $identifier;
                    $identifier = $this->specialIdentifierToUUID($identifier);
                    break;
                case strpos($identifier, 'orcid.org/') !== false:
                    $identifier = str_replace('orcid.org/', RC::get('orcidUrl'), $identifier);
                    $identifier = (substr($identifier, -1) == "/") ? substr_replace($identifier, "", -1) : $identifier;
                    $identifier = $this->specialIdentifierToUUID($identifier);
                    break;
                case strpos($identifier, 'pleiades.stoa.org/') !== false:
                    $identifier = str_replace('pleiades.stoa.org/', RC::get('pelagiosUrl'), $identifier);
                    $identifier = (substr($identifier, -1) == "/") ? substr_replace($identifier, "", -1) : $identifier;
                    $identifier = $this->specialIdentifierToUUID($identifier);
                    break;
                case strpos($identifier, 'gazetteer.dainst.org/') !== false:
                    $identifier = str_replace('gazetteer.dainst.org/', RC::get('gazetteerUrl'), $identifier);
                    $identifier = (substr($identifier, -1) == "/") ? substr_replace($identifier, "", -1) : $identifier;
                    $identifier = $this->specialIdentifierToUUID($identifier);
                    break;
                case strpos($identifier, 'doi.org/') !== false:
                    $identifier = str_replace('doi.org/', RC::get('doiUrl'), $identifier);
                    $identifier = (substr($identifier, -1) == "/") ? substr_replace($identifier, "", -1) : $identifier;
                    $identifier = $this->specialIdentifierToUUID($identifier);
                    break;
            }
            return $identifier;
        }
        
        if ($code == 1) {
            if (strpos($data, 'hdl.handle.net') !== false) {
                $data = str_replace("http://", "", $data);
            } elseif (strpos($data, 'https') !== false) {
                $data = str_replace("https://", "", $data);
            } else {
                $data = str_replace("http://", "", $data);
            }
            return $data;
        }
    }
    
    /**
     *
     * This function is get the acdh identifier by the PID, because all of the functions
     * are using the identifier and not the pid :)
     *
     * @param string $identifier
     * @return string
     */
    private function specialIdentifierToUUID(string $identifier, bool $pid = false): string
    {
        $return = "";
        $oeawStorage = new OeawStorage();
        
        try {
            if ($pid === true) {
                $idsByPid = $oeawStorage->getACDHIdByPid($identifier);
            } else {
                $idsByPid = $oeawStorage->getUUIDBySpecialIdentifier($identifier);
            }
        } catch (Exception $ex) {
            drupal_set_message($ex->getMessage(), 'error');
            return "";
        } catch (\InvalidArgumentException $ex) {
            drupal_set_message($ex->getMessage(), 'error');
            return "";
        }
        
        if (count($idsByPid) > 0) {
            foreach ($idsByPid as $d) {
                if (strpos((string)$d['id'], RC::get('fedoraIdNamespace')) !== false) {
                    $return = $d['id'];
                    break;
                }
            }
        }
        return $return;
    }
    
    /**
     * Get the actual Resource Dissemination services
     *
     * @param FedoraResource $fedoraRes
     * @return array
     * @throws \Exception
     * @throws \acdhOeaw\fedora\exceptions\NotFound
     */
    public function getResourceDissServ(\acdhOeaw\fedora\FedoraResource $fedoraRes): array
    {
        $result = array();
        
        if ($fedoraRes) {
            try {
                $id = $fedoraRes->getId();
                $dissServ = $fedoraRes->getDissServices();
                if (count($dissServ) > 0) {
                    $processed = array();
                    $guiUrls = array();
                    $i = 0;
                    $fedora = $this->initFedora();
                    foreach ($dissServ as $service) {
                        
                        //get the acdh identifiers for the dissemination services
                        if (!in_array($id, $processed)) {
                            $key = "";
                            $processed[$i] = $service->getId();
                            //get the final url of the dissemination service
                            $srv = new Service($fedora, $service->getUri());
                            $service->getId();
                            //the url dissemination return shortname (raw/gui), to we can identify them
                            $sUri = $service->getFormats();
                            $key = strtolower(substr($service->getUri(), strrpos($service->getUri(), '/') + 1));
                            $servUri = "";
                            //make a nice url to remove the https:// tags from the url
                            //because of the acdh identifier should appears there
                            if ($srv->getRequest($fedoraRes)->getUri()->__toString() && !empty($key)) {
                                $servUri = urldecode($srv->getRequest($fedoraRes)->getUri()->__toString());
                                //remove the identifier http/https tags from the url
                                if (strpos($servUri, '/https') !== false) {
                                    $servUri = str_replace('/https://', '/', $servUri);
                                } elseif (strpos($servUri, '/http') !== false) {
                                    $servUri = str_replace('/http://', '/', $servUri);
                                }
                                if (strpos($servUri, '/fcr:metadata') !== false) {
                                    $servUri = str_replace('/fcr:metadata', '', $servUri);
                                }
                                //add to the guiurl array
                                $guiUrls[$key]['guiUrl'] = $servUri;
                                $guiUrls[$key]['id'] = $service->getId();
                                $guiUrls[$key]['url'] = $service->getUri();
                            }
                            $i++;
                        }
                    }

                    if (count($processed) > 0) {
                        $oeawStorage = new OeawStorage();
                        //get the titles
                        $titles = array();
                        //get the titles fro the diss services.
                        $titles = $oeawStorage->getTitleByIdentifierArray($processed, true);
                        //remove the duplicates
                        $titles = HF::removeDuplicateValuesFromMultiArrayByKey($titles, "title");
                        
                        if (count($titles) > 0) {
                            //merge the available diss.serv and the guiUrls
                            foreach ($titles as $key => $val) {
                                //get the fedora dissemination service key from the database
                                $fedoraDKey = strtolower(substr($val['uri'], strrpos($val['uri'], '/') + 1)) ? strtolower(substr($val['uri'], strrpos($val['uri'], '/') + 1)) : "";
                                if (!empty($guiUrls[$fedoraDKey])) {
                                    //compare/merge dissemination and fedora data
                                    if (isset($guiUrls[$fedoraDKey]['guiUrl']) && !empty($guiUrls[$fedoraDKey]['guiUrl'])) {
                                        $result[$key] = $val;
                                        $result[$key]['guiUrl'] = $guiUrls[$fedoraDKey]['guiUrl'];
                                        if ($val['returnType'] == "rdf") {
                                            $result[$key]['guiUrl'] =  $guiUrls[$fedoraDKey]['guiUrl'].'/fcr:metadata';
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                return $result;
            } catch (Exception $ex) {
                throw new \Exception(
                    t('Error').':'.__FUNCTION__.t('Message').':'.$ex->getMessage()
                );
            } catch (\acdhOeaw\fedora\exceptions\NotFound $ex) {
                throw new \acdhOeaw\fedora\exceptions\NotFound(
                    t('Error').':'.__FUNCTION__.t('Message').':'.$ex->getMessage()
                );
            }
        }
        return $result;
    }
    
    /**
     *
     * Create the data for the pagination function
     *
     * @param int $limit
     * @param int $page
     * @param int $total
     * @return array
     *
     */
    public function createPaginationData(int $limit, int $page, int $total): array
    {
        $totalPages = 0;
        $res = array();
        
        if ($limit == 0) {
            $totalPages = 0;
        } else {
            $totalPages = ceil($total / $limit) ;
        }

        if (isset($page) && $page != 0) {
            if ($page > 0 && $page <= $totalPages) {
                $start = ($page - 1) * $limit;
                $end = $page * $limit;
            } else {
                // error - show first set of results
                $start = 0;
                $end = $limit;
            }
        } else {
            // if page isn't set, show first set of results
            $start = 0;
            $end = 0;
            $page = 0;
        }
        
        $res["start"] = $start;
        $res["end"] = $end;
        $res["page"] = $page;
        $res["totalPages"] = $totalPages;
        
        return $res;
    }
    
   
    
    /**
     * Creates a string from the currentPage For the pagination
     *
     * @return string
     */
    public function getCurrentPageForPagination(): string
    {
        $currentPath = "";
        $currentPage = "";
        
        $currentPath = \Drupal::service('path.current')->getPath();
        $currentPage = substr($currentPath, 1);
        $currentPage = explode("/", $currentPage);
        if (isset($currentPage[0]) && isset($currentPage[1])) {
            $currentPage = $currentPage[0].'/'.$currentPage[1];
        } else {
            $currentPage = $currentPage[0].'/';
        }
        
        return $currentPage;
    }
    
    /**
     * Create a rawurlencoded string from the users entered search string
     *
     * @param string $string
     * @param array $extras
     * @return string
     */
    public function convertSearchString(string $string, array $extras = null): string
    {
        $filters = array("type", "date", "words",);
        $operands = array("or", "not");
        $positions = array();
        
        $res = "";
        $string = strtolower($string);
        $string = str_replace(' ', '+', $string);
        //get the filters actual position in the string
        foreach ($filters as $f) {
            if (strpos($string, $f)) {
                $positions[$f] = strpos($string.':', $f);
            }
        }
        if (empty($positions) && !empty($string)) {
            $positions["words"] = 0;
        }
        //sort them by value to get the right order in the text
        asort($positions);

        $keys = array_keys($positions);

        $newStrArr = array();
        //create the type array
        foreach (array_keys($keys) as $k) {
            $thisVal = $positions[$keys[$k]];
            if ($k == 0) {
                //add the first line
                $newStrArr["words"] = substr($string, 0, $thisVal);
            }
            
            if ($positions[$keys[$k+1]]) {
                $nextVal = $positions[$keys[$k+1]];
                $newStrArr[$keys[$k]] =  substr($string, $thisVal, $nextVal - $thisVal);
            } else {
                $newStrArr[$keys[$k]] =  substr($string, $thisVal);
            }
        }
        
        $dtStr = "";
        $tyStr = "";
        $wsStr = "";
                
        if (isset($newStrArr["words"])) {
            $wdStr = strtolower($newStrArr["words"]);
            $wdStr = "words=".$wdStr;
            $res = $wdStr;
        }

        if (isset($newStrArr["type"])) {
            $tyStr = strtolower($newStrArr["type"]);
            if (isset($extras["type"])) {
                foreach ($extras["type"] as $t) {
                    if (strpos($tyStr, $t) == false) {
                        $tyStr .= "or+".$t."+";
                    }
                }
            }
            
            $tyStr = str_replace('type:', 'type=', $tyStr);
            
            if (!empty($tyStr)) {
                $res = $res."&".$tyStr;
            }
        } elseif (isset($extras["type"])) {
            $tyStr .="type=";
            
            $count = count($extras["type"]);
            $i = 0;
            foreach ($extras["type"] as $t) {
                if (strpos($tyStr, $t) == false) {
                    $tyStr .= "".$t."+";
                }
                if ($i != $count -1) {
                    $tyStr .= "or+";
                }
                $i++;
            }
            $res = $res."&".$tyStr;
        }
        
        //date format should be: mindate=20160101&maxdate=20170817
        if (isset($newStrArr["date"])) {
            $dtStr = strtolower($newStrArr["date"]);
            $dtStr = str_replace('date:[', 'mindate=', $dtStr);
            $dtStr = str_replace(']', '', $dtStr);
            $dtStr = str_replace(' ', '', $dtStr);
            $dtStr = str_replace('+to+', '&maxdate=', $dtStr);
            $newStrArr["date"] = $dtStr;
            if (!empty($res)) {
                $res = $res."&".$dtStr;
            }
        } elseif (isset($extras["start_date"]) && isset($extras["end_date"])) {
            $mindate = date("Ymd", strtotime($extras['start_date']));
            $maxdate = date("Ymd", strtotime($extras['end_date']));
        
            $res = $res."&mindate=".$mindate."&maxdate=".$maxdate;
        }
        
        $res = str_replace('+&', '&', $res);
        
        return $res;
    }
    
    
   
    
    
    /**
     *
     * create the page navigation html code
     *
     * @param type $actualPage
     * @param type $page
     * @param type $tpages
     * @param type $limit
     * @return string
     */
    public function createPaginationHTML(string $actualPage, string $page, $tpages, $limit): string
    {
        $adjacents = 2;
        $prevlabel = "<i class='material-icons'>&#xE5CB;</i>";
        $nextlabel = "<i class='material-icons'>&#xE5CC;</i>";
        $out = "";
        $actualPage;
        $tpages = $tpages;
        // previous
        if ($page == 0) {
            //Don't show prev if we are on the first page
            //$out.= "<li class='pagination-item'><span>" . $prevlabel . "</span></li>";
        } else {
            $out.= "<li class='pagination-item'><a data-pagination='" . $page . "'>" . $prevlabel . "</a>\n</li>";
        }

        $pmin = ($page > $adjacents) ? ($page - $adjacents) : 1;
        $pmax = ($page < ($tpages - $adjacents)) ? ($page + $adjacents) : $tpages;
        
        for ($i = $pmin; $i <= $pmax; $i++) {
            if ($i-1 == $page) {
                $out.= "<li class='pagination-item active'><a data-pagination='".$i."'>" . $i . "</a></li>\n";
            } else {
                $out.= "<li class='pagination-item'><a data-pagination='" . $i . "'>" . $i . "</a>\n</li>";
            }
        }

        // next
        if ($page < $tpages-1) {
            $out.= "<li class='pagination-item'><a data-pagination='" . ($page + 2) . "'>" . $nextlabel . "</a>\n</li>";
        } else {
            //Don't show next if we are on the last page
            //$out.= "<li class='pagination-item'><span style=''>" . $nextlabel . "</span></li>";
        }
        
        if ($page < ($tpages - $adjacents)) {
            $out.= "<li class='pagination-item'><a data-pagination='" . $tpages . "'><i class='material-icons'>&#xE5DD;</i></a></li>";
        }
        $out.= "";
        
        return $out;
    }
    

    /**
     *
     * Check the Resource Rules and display the users/grants
     *
     * @param array $rules
     * @return array
     *
     */
    public function checkRules(array $rules): array
    {
        $rights = array();
        //if we dont have rights then we have some error in the fedora db, so we
        // will automatically adding the READ rights for the resource
        if (count($rules) == 0) {
            $rights['username'] = "user";
            $rights['mode'][] = "READ";
        } else {
            $i = 0;
            //check the rules
            foreach ($rules as $r) {
                if ($r->getRoles(\acdhOeaw\fedora\acl\WebAclRule::USER)) {
                    $rights['username'] = "user";
                } elseif ($r->getRoles(\acdhOeaw\fedora\acl\WebAclRule::GROUP)) {
                    $rights['username'] = "group";
                }
                
                switch ($r->getMode(\acdhOeaw\fedora\acl\WebAclRule::WRITE)) {
                    case \acdhOeaw\fedora\acl\WebAclRule::READ:
                        $rights['mode'][] = "READ";
                        break;
                    case \acdhOeaw\fedora\acl\WebAclRule::WRITE:
                        $rights['mode'][] = "WRITE";
                        break;
                    default:
                        $rights['mode'][] = "NONE";
                }
            }
        }
        
        if (count($rights) == 0 || $this->checkMultiDimArrayForValue('NONE', $rights) == true) {
            throw new \Exception(
                $this->langConf->get('errmsg_dont_have_permission') ? $this->langConf->get('errmsg_dont_have_permission') : 'You do not have enough permission'
            );
        }
        return $rights;
    }
    
    /**
     *
     * Get the Fedora Resource Rules
     * If it is empty, then it is a private resource
     *
     * @param string $uri
     * @param FedoraResource $fedoraRes
     * @return array
     */
    public function getRules(string $uri, \acdhOeaw\fedora\FedoraResource $fedoraRes): array
    {
        $result = array();
        
        try {
            $aclObj = $fedoraRes->getAcl()->getRules();
        } catch (Exception $ex) {
            throw new \Exception(
                t('Error').':'.__FUNCTION__.t('Message').':'.$ex->getMessage()
            );
        } catch (\acdhOeaw\fedora\exceptions\NotFound $ex) {
            throw new \acdhOeaw\fedora\exceptions\NotFound(
                t('Error').':'.__FUNCTION__.t('Message').':'.$ex->getMessage()
            );
        }
        return $result;
    }
    
    /**
     *
     * Add access to the user on the actual resource
     *
     * @param string $uri
     * @param string $user
     * @param Fedora $fedora
     * @return array
     */
    public function grantAccess(string $uri, string $user, \acdhOeaw\fedora\Fedora $fedora): array
    {
        $result = array();
        
        $fedora->begin();
        
        try {
            $res = $fedora->getResourceByUri($uri);
        } catch (Exception $ex) {
            return array();
        } catch (\acdhOeaw\fedora\exceptions\NotFound $ex) {
            return array();
        }
        
        $aclObj = $res->getAcl();
        $aclObj->grant(\acdhOeaw\fedora\acl\WebAclRule::USER, $user, \acdhOeaw\fedora\acl\WebAclRule::READ);
        $aclObj = $res->getAcl();
        $result = $aclObj->getRules();
        $fedora->commit();
        
        return $result;
    }
    
    /**
     * Remove the user rules from the resource
     *
     * @param string $uri
     * @param string $user
     * @param Fedora $fedora
     * @return array
     */
    public function revokeRules(string $uri, string $user, \acdhOeaw\fedora\Fedora $fedora): array
    {
        $result = array();
        
        $fedora->begin();
        $res = $fedora->getResourceByUri($uri);
        $aclObj = $res->getAcl();
        $aclObj->revoke(\acdhOeaw\fedora\acl\WebAclRule::USER, $user, \acdhOeaw\fedora\acl\WebAclRule::READ);
        $aclObj->revoke(\acdhOeaw\fedora\acl\WebAclRule::USER, $user, \acdhOeaw\fedora\acl\WebAclRule::WRITE);
        $aclObj = $res->getAcl();
        $result = $aclObj->getRules();
        $fedora->commit();
        
        return $result;
    }
    
    /**
     * This functions create the Concept template data for the basic view
     *
     * @param array $data
     * @return array
     */
    public function createPlacesTemplateData(array $data): array
    {
        $result = array();
        
        if (count($data['table']) > 0) {
            //basic
            $basicPropertys = array(
                "acdh:hasTitle",
                "acdh:hasIdentifier",
                "acdh:hasAlternativeTitle",
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
            
            foreach ($basicPropertys as $bP) {
                if ((isset($data['table'][$bP])) && (count($data['table'][$bP]) > 0)) {
                    foreach ($data['table'][$bP] as $val) {
                        if ($bP == "acdh:hasIdentifier") {
                            if (strpos($val['uri'], 'id.acdh.oeaw.ac.at') == false) {
                                $result['basic'][$bP][] = $val;
                            }
                        } else {
                            $result['basic'][$bP][] = $val;
                        }
                    }
                }
            }
            if (isset($data['acdh_rdf:type'])) {
                $result['basic']['acdh_rdf:type'] = $data['acdh_rdf:type'];
            }
            
            //contact details
            $spatialProperties = array(
                "acdh:hasLatitude",
                "acdh:hasLongitude",
                "acdh:hasWKT"
            );
            
            //generate the contact data
            foreach ($spatialProperties as $prop) {
                if ((isset($data['table'][$prop])) && (count($data['table'][$prop]) > 0)) {
                    $result['spatial'][$prop] = $data['table'][$prop];
                }
            }
        }
        return $result;
    }
        
    
    /**
     *
     * Convers the sparql result contributors, authors, creators data to fit our spec. Obj
     *
     * @param array $data
     * @return array
     */
    public function createContribAuthorData(array $data): array
    {
        $result = array();
        $oeawStorage = new OeawStorage();
        foreach ($data as $d) {
            $title = $oeawStorage->getTitleByIdentifier($d);
            if (count($title) > 0) {
                if (!empty($title[0]['title'])) {
                    $result[] = array("title" => $title[0]['title'], "insideUri" => $this->detailViewUrlDecodeEncode($d, 1));
                }
            }
        }
        return $result;
    }

    /**
     *
     * Get the necessary data for the CITE Widget based on the properties
     *
     * @param array $data - resource data array
     * @param string $property - shortcur property - f.e.: acdh:hasCreator
     * @return string - a string with the available data
     */
    private function getCiteWidgetData(\Drupal\oeaw\Model\OeawResource $data, string $property): string
    {
        $result = "";
        
        if (count((array)$data) > 0) {
            if (!empty($data->getTableData($property))) {
                foreach ($data->getTableData($property) as $key => $val) {
                    if (count($data->getTableData($property)) > 0) {
                        if (isset($val["title"])) {
                            $result .= $val["title"];
                            if ($key + 1 != count($data->getTableData($property))) {
                                $result .= ", ";
                            }
                        } elseif (isset($val["uri"])) {
                            $result .= $val["uri"];
                            if ($key + 1 != count($data->getTableData($property))) {
                                $result .= ", ";
                            }
                        } else {
                            if (!is_array($val)) {
                                $result .= ", " . $val;
                            }
                        }
                    } else {
                        if (isset($val["title"])) {
                            $result = $val["title"];
                        } elseif (isset($val["uri"])) {
                            $result = $val["uri"];
                        } else {
                            $result = $val;
                        }
                    }
                }
            }
        }
        return $result;
    }
    
    /**
     *
     * Create the HTML content of the cite-this widget on single resource view
     *
     * @param array $resourceData Delivers the properties of the resource
     * @return array $widget Returns the cite-this widget as HTML
     */
    public function createCiteThisWidget(\Drupal\oeaw\Model\OeawResource $resourceData): array
    {
        $content = [];

        /** MLA Format
         * Example:
         * Mörth, Karlheinz. Dictionary Gate. ACDH, 2013, hdl.handle.net/11022/0000-0000-001B-2. Accessed 12 Oct. 2017.
         */
        $widget["MLA"] = ["authors" => "", "creators" => "", "hasPrincipalInvestigator" => "", "contributors" => "", "title" => "", "isPartOf" => "", "availableDate" => "", "hasHosting" => "", "hasEditor" => "", "accesedDate" => "", "acdhURI" => ""];

        //Get authors(s)
        $authors = "";
        $authors = $this->getCiteWidgetData($resourceData, "acdh:hasAuthor");
        if (!empty($authors)) {
            $widget["MLA"]["authors"] = $authors;
        }
        
        //Get creator(s)
        $creators = "";
        $creators = $this->getCiteWidgetData($resourceData, "acdh:hasCreator");
        if (!empty($creators)) {
            $widget["MLA"]["creators"] = $creators;
        }
        
        //Get contributor(s)
        $contributors = "";
        $contributors = $this->getCiteWidgetData($resourceData, "acdh:hasContributor");
        if (!empty($contributors)) {
            $widget["MLA"]["contributors"] = $contributors;
        }
        
        //Get PrincipalInvestigator(s)
        $principalInvestigator = "";
        $principalInvestigator = $this->getCiteWidgetData($resourceData, "acdh:hasPrincipalInvestigator");
        if (!empty($principalInvestigator)) {
            $widget["MLA"]["hasPrincipalInvestigator"] = $principalInvestigator;
        }

        //Get title
        if (!empty($resourceData->getTitle())) {
            $widget["MLA"]["title"] = $resourceData->getTitle();
        }

        //Get isPartOf
        if (!empty($resourceData->getTableData("acdh:isPartOf"))) {
            $isPartOf = $resourceData->getTableData("acdh:isPartOf")[0]["title"];
            $widget["MLA"]["isPartOf"] = $isPartOf;
        }
        
        //Get hasHosting
        if (!empty($resourceData->getTableData("acdh:hasHosting"))) {
            $hasHosting = $resourceData->getTableData("acdh:hasHosting")[0]["title"];
            $widget["MLA"]["hasHosting"] = $hasHosting;
        }

        /* Get hasPid & create copy link
         * Order of desired URIs:
         * PID > id.acdh > id.acdh/uuid > long gui url
         */
        if (!empty($resourceData->getPID())) {
            $widget["MLA"]["acdhURI"] = $resourceData->pid;
        }
        
        if (!$widget["MLA"]["acdhURI"]) {
            if (!empty($resourceData->getIdentifiers()) && count($resourceData->getIdentifiers()) > 0) {
                $acdhURIs = $resourceData->getIdentifiers();
                //Only one value under acdh:hasIdentifier
                
                $uuid = "";
                
                foreach ($acdhURIs as $id) {
                    //the id contains the acdh uuid
                    if (strpos($id, RC::get('fedoraUuidNamespace')) !== false) {
                        $uuid = $id;
                    //if the identifier is the normal acdh identifier then return it
                    } elseif (strpos($id, RC::get('fedoraIdNamespace')) !== false) {
                        $uuid = $id;
                        break;
                    }
                }
                $widget["MLA"]["acdhURI"] = $uuid;
            }
        }

        //Get available date
        if (!empty($resourceData->getTableData("acdh:hasAvailableDate"))) {
            $availableDate = $resourceData->getTableData("acdh:hasAvailableDate")[0];
            $availableDate = strtotime($availableDate);
            $widget["MLA"]["availableDate"] = date('Y', $availableDate);
        }
        
        //Get accesed date
        $widget["MLA"]["accesedDate"] = date('d M Y');

        
        //Process MLA
        //Top level resource
        //if (!$widget["MLA"]["isPartOf"]) {

        $widget["MLA"]["string"] = "";
        //AUTHORS
        if (isset($widget["MLA"]["authors"]) && !empty($widget["MLA"]["authors"])) {
            $widget["MLA"]["string"] .= $widget["MLA"]["authors"].'... ';
        } elseif (isset($widget["MLA"]["creators"]) && !empty($widget["MLA"]["creators"])) {
            $widget["MLA"]["string"] .= $widget["MLA"]["creators"].'. ';
        } elseif (isset($widget["MLA"]["contributors"]) && !empty($widget["MLA"]["contributors"])) {
            $widget["MLA"]["string"] .= $widget["MLA"]["contributors"].'. ';
        }

        //hasPrincipalInvestigator
        if (
            isset($widget["MLA"]["hasPrincipalInvestigator"])
                &&
            !empty(trim($widget["MLA"]["hasPrincipalInvestigator"]))) {
            $widget["MLA"]["string"] = str_replace(".", ",", $widget["MLA"]["string"]);
            
            $arr = explode(",", $widget["MLA"]["string"]);
            foreach ($arr as $a) {
                $a = ltrim($a);
                //if the string already contains the prininv name, then we will skip it from the final result
                if (!empty($a) && strpos($widget["MLA"]["hasPrincipalInvestigator"], $a) !== false) {
                    $widget["MLA"]["hasPrincipalInvestigator"] = str_replace($a.",", "", $widget["MLA"]["hasPrincipalInvestigator"]);
                    $widget["MLA"]["hasPrincipalInvestigator"] = str_replace($a, "", $widget["MLA"]["hasPrincipalInvestigator"]);
                }
            }
            
            //$widget["MLA"]["hasPrincipalInvestigator"] = substr(rtrim($widget["MLA"]["hasPrincipalInvestigator"]), 0, -1);
            if (isset($widget["MLA"]["hasPrincipalInvestigator"]) && !empty(trim($widget["MLA"]["hasPrincipalInvestigator"]))) {
                //if the last char is the , then we need to remove it
                if (substr(trim($widget["MLA"]["hasPrincipalInvestigator"]), -1) == ",") {
                    $widget["MLA"]["hasPrincipalInvestigator"] = trim($widget["MLA"]["hasPrincipalInvestigator"]);
                    $widget["MLA"]["hasPrincipalInvestigator"] = rtrim($widget["MLA"]["hasPrincipalInvestigator"], ",");
                }
                $widget["MLA"]["string"] .= ' '.$widget["MLA"]["hasPrincipalInvestigator"].'. ';
            }
        }
        
        if (substr(trim($widget["MLA"]["string"]), -1) == ",") {
            $widget["MLA"]["string"] = trim($widget["MLA"]["string"]);
            $widget["MLA"]["string"] = rtrim($widget["MLA"]["string"], ",");
            $widget["MLA"]["string"] .= '. ';
        }

        //TITLE
        if ($widget["MLA"]["title"]) {
            $widget["MLA"]["string"] .= '<em>'.$widget["MLA"]["title"].'.</em> ';
        }

        //PUBLISHER
        if ($widget["MLA"]["hasHosting"]) {
            $widget["MLA"]["string"] .= $widget["MLA"]["hasHosting"].', ';
        }

        //DATE
        if ($widget["MLA"]["availableDate"]) {
            $widget["MLA"]["string"] .= $widget["MLA"]["availableDate"].', ';
        }

        //HANDLE
        if ($widget["MLA"]["acdhURI"]) {
            $widget["MLA"]["string"] .= $widget["MLA"]["acdhURI"].'. ';
        }

        //DATE
        if ($widget["MLA"]["accesedDate"]) {
            $widget["MLA"]["string"] .= 'Accessed '.$widget["MLA"]["accesedDate"].'. ';
        }

        /*
        } else {
            //Only cite top level collections for now
            return $content;
        }
        */

        return $widget;
    }

    /**
     *
     * Creates the EasyRdf_Resource by uri
     *
     * @param string $uri
     * @return \EasyRdf\Resource
     */
    public function makeMetaData(string $uri): \EasyRdf\Resource
    {
        if (empty($uri)) {
            return drupal_set_message(
                $this->langConf->get('errmsg_resource_not_exists') ? $this->langConf->get('errmsg_resource_not_exists') : 'Resource does not exist!',
                'error'
            );
        }
        
        $meta = array();
        // setup fedora
        $fedora = new Fedora();
        try {
            $meta = $fedora->getResourceByUri($uri);
            $meta = $meta->getMetadata();
        } catch (\acdhOeaw\fedora\exceptions\NotFound $ex) {
            throw new \acdhOeaw\fedora\exceptions\NotFound(
                $this->langConf->get('errmsg_resource_not_exists') ? $this->langConf->get('errmsg_resource_not_exists') : 'Resource does not exist!'
            );
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            throw new \GuzzleHttp\Exception\ClientException($ex->getMessage());
        }
        return $meta;
    }
    
    
    /**
     * Creates the EasyRdf_Graph by uri
     *
     * @param string $uri - resource uri
     * @return  \EasyRdf\Graph
     *
     */
    public function makeGraph(string $uri): \EasyRdf\Graph
    {
        $graph = array();
        // setup fedora
        $fedora = new Fedora();
        
        //create and load the data to the graph
        try {
            $graph = $fedora->getResourceByUri($uri);
            $graph = $graph->getMetadata()->getGraph();
        } catch (\Exception $ex) {
            throw new \Exception($ex->getMessage());
        } catch (\acdhOeaw\fedora\exceptions\NotFound $ex) {
            throw new \acdhOeaw\fedora\exceptions\NotFound("Resource does not exist!");
        } catch (\acdhOeaw\fedora\exceptions\Deleted $ex) {
            throw new \acdhOeaw\fedora\exceptions\Deleted($ex->getMessage());
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            throw new \GuzzleHttp\Exception\ClientException($ex->getMessage());
        }
        
        return $graph;
    }
    
    
    /**
    * Get the title by the property
    * This is a static method because the Edit/Add form will use it
    * over their callback method.
    *
    *
    * @param array $formElements -> the actual form input
    * @param string $mode -> edit/new form.
    * @return AjaxResponse
    *
    */
    public function getFieldNewTitle(array $formElements, string $mode = 'edit'): AjaxResponse
    {
        $ajax_response = array();
        $fedora = new Fedora();
        
        if ($mode == "edit") {
            //create the old values and the new values arrays with the user inputs
            foreach ($formElements as $key => $value) {
                if (strpos($key, ':oldValues') !== false) {
                    if (strpos($key, ':prop') === false) {
                        $newKey = str_replace(':oldValues', "", $key);
                        $oldValues[$newKey] = $value;
                    }
                } else {
                    $newValues[$key] = $value;
                }
            }
            //get the differences
            $result = array_diff_assoc($newValues, $oldValues);
        } elseif ($mode == "new") {
            //get the values which are urls
            foreach ($formElements as $key => $value) {
                if ((strpos($key, ':prop') !== false)) {
                    unset($formElements[$key]);
                } elseif (strpos($value, 'http') !== false) {
                    $result[$key] = $value;
                }
            }
        }
        
        $ajax_response = new AjaxResponse();
        if (empty($result)) {
            return $ajax_response;
        }
        
        foreach ($result as $key => $value) {
            //get the fedora urls, where we can create a FedoraObject
            if (!empty($value) && strpos($value, RC::get('fedoraApiUrl')) !== false && $key != "file" && !is_array($value)) {
                $lblFO = $this->makeMetaData($value);
                //if not empty the fedoraObj then get the label
                if (!empty($lblFO)) {
                    $lbl = $lblFO->label();
                }
            }
            
            if (!empty($lbl)) {
                $label = htmlentities($lbl, ENT_QUOTES, "UTF-8");
            } else {
                $label = "";
            }
            $ajax_response->addCommand(new HtmlCommand('#edit-'.$key.'--description', "New Value: <a href='".(string)$value."' target='_blank'>".(string)$label."</a>"));
            $ajax_response->addCommand(new InvokeCommand('#edit-'.$key.'--description', 'css', array('color', 'green')));
        }
        // Return the AjaxResponse Object.
        return $ajax_response;
    }
  
    
    /**
     *
     * Creating an array from the vocabsNamespace
     *
     * @param string $string
     * @return array
     *
     *
     */
    public function createStringFromACDHVocabs(string $string): array
    {
        if (empty($string)) {
            return false;
        }
        $result = array();
        
        if (strpos($string, RC::vocabsNmsp()) !== false) {
            $result['rdfType']['typeUri'] = $string;
            $result['rdfType']['typeName'] = str_replace(RC::vocabsNmsp(), '', $string);
        }
        return $result;
    }
    
    /**
     *
     * create prefix from string based on the  prefixes
     *
     * @param string $string
     * @return string
     */
    public static function createPrefixesFromString(string $string): string
    {
        if (empty($string)) {
            return false;
        }
        $result = array();
        $endValue = explode('/', $string);
        $endValue = end($endValue);
        
        if (strpos($endValue, '#') !== false) {
            $endValue = explode('#', $string);
            $endValue = end($endValue);
        }
        
        $newString = array();
        $newString = explode($endValue, $string);
        $newString = $newString[0];
                
        if (!empty(CC::$prefixesToChange[$newString])) {
            $result = CC::$prefixesToChange[$newString].':'.$endValue;
        } else {
            $result = $string;
        }
        return $result;
    }

    
    /**
     *
     * create prefix from array based on the ConfigConstants.php prefixes
     *
     * @param array $array
     * @param array $header
     * @return array
     */
    public function createPrefixesFromArray(array $array, array $header): array
    {
        if (empty($array) && empty($header)) {
            return drupal_set_message(t('Error').':'.__FUNCTION__, 'error');
        }
        
        $result = array();
        $newString = array();
        
        for ($index = 0; $index < count($header); $index++) {
            $key = $header[$index];
            foreach ($array as $a) {
                $value = $a[$key];
                $endValue = explode('/', $value);
                $endValue = end($endValue);
                
                if (strpos($endValue, '#') !== false) {
                    $endValue = explode('#', $value);
                    $endValue = end($endValue);
                }
                
                $newString = explode($endValue, $value);
                $newString = $newString[0];
                 
                if (!empty(CC::$prefixesToChange[$newString])) {
                    $result[$key][] = CC::$prefixesToChange[$newString].':'.$endValue;
                } else {
                    $result[$key][] = $value;
                }
            }
        }
        return $result;
    }
    
    
    /**
     *
     * Format data to children array
     *
     * @param array $data
     * @return array
     *
     */
    public function createChildrenViewData(array $data): array
    {
        $result = array();
        
        if (count($data) == 0) {
            throw new \ErrorException(
                $this->langConf->get('errmsg_no_child_resources') ? $this->langConf->get('errmsg_no_child_resources') : 'There is no any children data'
            );
        }
        
        foreach ($data as $d) {
            $id = $this->createDetailViewUrl($d);
            $arrayObject = new \ArrayObject();
            
            $arrayObject->offsetSet('uri', $d['uri']);
            $arrayObject->offsetSet('title', $d['title']);
            $arrayObject->offsetSet('pid', $d['pid']);
            $arrayObject->offsetSet('description', $d['description']);
            $arrayObject->offsetSet('typeUri', $d['types']);
            $arrayObject->offsetSet('identifier', $d['identifier']);
            $arrayObject->offsetSet('insideUri', $this->detailViewUrlDecodeEncode($id, 1));
            $arrayObject->offsetSet('accessRestriction', $d['accessRestriction']);
            
            if (isset($d['uri'])) {
                $arrayObject->offsetSet('typeName', explode(RC::get('fedoraVocabsNamespace'), $d['types'])[1]);
            }
            $result[] = new \Drupal\oeaw\Model\OeawResourceChildren($arrayObject);
        }

        return $result;
    }
    

    
    
    
    /**
     *
     * Get the keys from a multidimensional array
     *
     * @param array $arr
     * @return array
     */
    public function getKeysFromMultiArray(array $arr): array
    {
        foreach ($arr as $key => $value) {
            $return[] = $key;
            if (is_array($value)) {
                $return = array_merge($return, $this->getKeysFromMultiArray($value));
            }
        }
        
        //remove the duplicates
        $return = array_unique($return);
        
        //remove the integers from the values, we need only the strings
        foreach ($return as $key => $value) {
            if (is_numeric($value)) {
                unset($return[$key]);
            }
        }
        
        return $return;
    }
    
    /**
     *
     * Get the Resource Title by the uri
     *
     * @param string $string
     * @return boolean
     *
     */
    public function getTitleByUri(string $string)
    {
        if (!$string) {
            return false;
        }
        
        $return = "";
        $OeawStorage = new OeawStorage();
        
        $itemRes = $OeawStorage->getResourceTitle($string);

        if (count($itemRes) > 0) {
            if ($itemRes[0]["title"]) {
                $return = $itemRes[0]["title"];
            }
        }
        return $return;
    }
    
    /**
     * Get the title if the url contains the fedoraIDNamespace or the viaf.org ID
     *
     * @param string $string
     * @return array
     */
    public function getTitleByTheFedIdNameSpace(string $string): array
    {
        if (!$string) {
            return false;
        }
        $return = array();
        $OeawStorage = new OeawStorage();
        $itemRes = $OeawStorage->getTitleByIdentifier($string);

        if (count($itemRes) > 0) {
            $return = $itemRes;
        }
        return $return;
    }
    
    
    /**
     *
     * Check the value in the array
     *
     * @param type $needle -> strtolower value of the string
     * @param type $haystack -> the array where the func should serach
     * @param type $strict
     * @return boolean
     */
    public function checkMultiDimArrayForValue($needle, $haystack, $strict = false)
    {
        foreach ($haystack as $item) {
            if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && $this->checkMultiDimArrayForValue($needle, $item, $strict))) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check value in array, recursive mode
     *
     * @param string $needle
     * @param array $haystack
     * @param bool $strict
     * @param array $keys
     * @return bool
     */
    public function in_array_r(string $needle, array $haystack, bool $strict = false, array &$keys): bool
    {
        foreach ($haystack as $key => $item) {
            if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && $this->in_array_r($needle, $item, $strict, $keys))) {
                //we checking only the propertys
                if (strpos($key, ':') !== false) {
                    $keys[$key] = $needle;
                }
                return true;
            }
        }
        return false;
    }
    
  
    
    /**
     * This func is generating a child based array from a single array by ID
     *
     * @param type $list
     * @param type $parent
     * @return type
     */
    public function convertToTreeById(&$list, $parent)
    {
        $tree = array();
        foreach ($parent as $k=>$l) {
            if (isset($list[$l['resShortId']])) {
                $l['children'] = $this->convertToTreeById($list, $list[$l['resShortId']]);
            }
            $tree[] = $l;
        }
        return $tree;
    }
    
    /**
     *
     * This func is generating a child based array from a single array
     *
     * @param array $flat
     * @param type $idField
     * @param type $parentIdField
     * @param type $childNodesField
     * @return type
     */
    public function convertToTree(
        array $flat,
        $idField = 'id',
        $parentIdField = 'parentId',
        $childNodesField = 'children'
    ) {
        $indexed = array();
        // first pass - get the array indexed by the primary id
        foreach ($flat as $row) {
            $indexed[$row[$idField]] = $row;
            $indexed[$row[$idField]][$childNodesField] = array();
        }
   
        //second pass
        $root = null;
        foreach ($indexed as $id => $row) {
            $indexed[$row[$parentIdField]][$childNodesField][] =& $indexed[$id];
            if (!$row[$parentIdField] || empty($row[$parentIdField])) {
                $root = $id;
            }
        }
        return array($indexed[$root]);
    }
    
    
    /**
     * Generate the resource child data and some pagination data also
     *
     * @param array $identifiers - Resource acdh:hasIdentifier
     * @param array $data - Resource metadata
     * @param array $properties - actual uri and for pagination: limit, page
     * @return array with children array, type and currentpage
     *
     */
    public function generateChildViewData(array $identifiers, \Drupal\oeaw\Model\OeawResource $data, array $properties): array
    {
        $result = array();
        if ((count($identifiers) == 0) || (count((array)$data) == 0) || (count($properties) == 0)) {
            return $result;
        }
        
        $countData = array();
        $typeProperties = array();
        $oeawStorage = new OeawStorage();
        $specialType = "child";
        $currentPage = $this->getCurrentPageForPagination();
        
        //we checks if the acdh:Person is available then we will get the Person Detail view data
        if (!empty($data->getType()) && !empty($data->getTypeUri())) {
            if (in_array(strtolower($data->getType()), CC::$availableCustomViews)) {
                $specialType = $data->getType();
                $typeProperties = CC::getDetailChildViewProperties($data->getTypeUri());
                if (count($typeProperties) > 0) {
                    $countData = $oeawStorage->getSpecialDetailViewData($properties['identifier'][0], $properties['limit'], $properties['page'], true, $typeProperties);
                }
            } else {
                if (count($countData) == 0) {
                    $countData = $oeawStorage->getChildrenViewData($identifiers, $properties['limit'], $properties['page'], true);
                }
            }
        }
       
        $total = (int)count($countData);
        if ($properties['limit'] == "0") {
            $pagelimit = "10";
        } else {
            $pagelimit = $properties['limit'];
        }
        //create data for the pagination
        $pageData = $this->createPaginationData($pagelimit, (int)$properties['page'], $total);

        if ($pageData['totalPages'] > 1) {
            $result["pagination"] =  $this->createPaginationHTML($currentPage, $pageData['page'], $pageData['totalPages'], $pagelimit);
        }
 
        if (in_array(strtolower($specialType), CC::$availableCustomViews)) {
            $childrenData = $oeawStorage->getSpecialDetailViewData($properties['identifier'][0], $pagelimit, $pageData['end'], false, $typeProperties);
        } else {
            //there is no special children view, so we are using the the default children table
            $childrenData = $oeawStorage->getChildrenViewData($identifiers, $pagelimit, $pageData['end']);
        }

        //we have children data so we will generate the view for it
        if (count($childrenData) > 0) {
            try {
                $result["childResult"] = $this->createChildrenViewData($childrenData);
            } catch (Exception $ex) {
                throw new \ErrorException($ex->getMessage());
            }
        }
        $result["currentPage"] = $currentPage;
        $result["specialType"] = $specialType;
        
        return $result;
    }
    
    /**
     *
     * This function is generating the child api data
     *
     * @param string $identifier
     * @param int $limit
     * @param int $page
     * @param string $order
     * @return array
     * @throws \ErrorException
     */
    public function generateChildAPIData(string $identifier, int $limit, int $page, string $order): array
    {
        $result = array();
        $countData = array();
        $typeProperties = array();
        $oeawStorage = new OeawStorage();
        $specialType = "child";
        //get the main resource data
        $resType = $oeawStorage->getTypeByIdentifier($identifier);
        
        if (count($resType) == 0) {
            return array();
        }
        
        $typeUri = $resType[0]['type'];
        $type = str_replace(RC::get('fedoraVocabsNamespace'), '', $resType[0]['type']);
        
        //we checks if the acdh:Person is available then we will get the Person Detail view data
        if (!empty($type) && !empty($typeUri)) {
            if (in_array(strtolower($type), CC::$availableCustomViews)) {
                $specialType = $type;
                $typeProperties = CC::getDetailChildViewProperties($typeUri);
                if (count($typeProperties) > 0) {
                    $countData = $oeawStorage->getSpecialDetailViewData($identifier, 0, $page, true, $typeProperties);
                    $result["maxPage"] = count($countData);
                }
            } else {
                if (count($countData) == 0) {
                    $countData = $oeawStorage->getChildrenViewData(array($identifier), 0, $page, true);
                    $result["maxPage"] = count($countData);
                }
            }
        }
       
        $total = (int)count($countData);
        if ($limit == "0") {
            $limit = "10";
        }
        if ($page != "0" && $page != "1") {
            $offset = ($page - 1)  * $limit;
        } else {
            $offset = 0;
        }
        
        if (in_array(strtolower($specialType), CC::$availableCustomViews)) {
            //$childrenData = $oeawStorage->getSpecialDetailViewData($identifier, $limit, $offset, false, $typeProperties);
            $childrenData = $oeawStorage->getSpecialDetailViewData($identifier, $limit, $offset, false, $typeProperties, "en", $order);
        } else {
            //there is no special children view, so we are using the the default children table
            //$childrenData = $oeawStorage->getChildrenViewData(array($identifier), $limit, $offset);
            $childrenData = $oeawStorage->getChildrenViewData(array($identifier), $limit, $offset, false, "en", $order);
        }
        
        //we have children data so we will generate the view for it
        if (count($childrenData) > 0) {
            try {
                $result["childResult"] = $this->createChildrenViewData($childrenData);
            } catch (Exception $ex) {
                throw new \ErrorException($ex->getMessage());
            }
        }
        
        $result["mainResourceType"] = $type;
        $result["currentPage"] = $page;
        $result["currentLimit"] = $limit;
        if (isset($result["maxPage"]) && !empty($result["maxPage"])) {
            $result["maxPageLimit"] = ceil((int)$result["maxPage"]/(int)$limit);
        }
        $result["specialType"] = $specialType;
        
        return $result;
    }
    
    /**
     * Get highlight data from solr
     *
     * @param string $text
     * @return array
     */
    public function getDataFromSolr(string $text) : array
    {
        if (!$text) {
            return array();
        }
        
        $result = array();
        $client = new \GuzzleHttp\Client();
        try {
            $request = $client->request('GET', RC::get('solrUrl').'/arche/select?hl.fl=_text_&hl.maxAnalyzedChars='.RC::get("solrMaxAnalyzedChars").'&hl=true&q=*'.$text, ['auth' => ['admin', 'admin']]);
            if ($request->getStatusCode() == 200) {
                $data = json_decode($request->getBody()->getContents());
                $docs = $data->response->docs;
                $highlighting = $data->highlighting;
                
                $docsCount = count((array)$docs);
                if ($docsCount > 0) {
                    foreach ($docs as $d) {
                        $docsData = array();
                        if (isset($d->meta_title) && isset($d->meta_rdfType)) {
                            if (is_array($d->meta_title)) {
                                $docsData['title'] = $d->meta_title[0];
                            } else {
                                $docsData['title'] = $d->meta_title;
                            }
                            
                            $docsData['uri'] = $d->id;
                            if ($d->meta_rdfType) {
                                foreach ($d->meta_rdfType as $type) {
                                    if (strpos($type, RC::get('fedoraVocabsNamespace')) !== false) {
                                        $docsData['acdhType'] = str_replace(RC::get('fedoraVocabsNamespace'), '', $type);
                                    }
                                }
                            }
                            if ($d->meta_identifier) {
                                foreach ($d->meta_identifier as $identifiers) {
                                    if (strpos($identifiers, RC::get('fedoraUuidNamespace')) !== false) {
                                        $docsData['identifier'] = $identifiers;
                                    }
                                }
                            }
                            if (isset($d->meta_description)) {
                                $docsData['description'] = $d->meta_description[0];
                            }
                            $id = $d->id;
                            if ($highlighting->$id && isset($highlighting->$id->_text_[0])) {
                                $docsData['highlighting'][] = html_entity_decode($highlighting->$id->_text_[0]);
                            } else {
                                $docsData['highlighting'][] = t('Re-index').' '.t('the').' '.t('File');
                            }
                            $result[] = $docsData;
                        }
                    }
                }
            }
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return array();
        } catch (\Exception $ex) {
            return array();
        }
        
        return $result;
    }
    
    /**
     * Formats the breadcumb data, delete the duplications.
     *
     * @param array $data
     * @return array
     */
    public function formatBreadcrumbData(array $data): array
    {
        if (count($data) <= 0) {
            return array();
        }
       
        $rootsRootArray = array_column($data, 'rootsRoot');
        $rootsIDArray = array_column($data, 'rootId');
        $last = array();
        $result = array();
        
        //get the first element
        $firstkey = 0;
        foreach ($rootsRootArray as $k => $v) {
            if (empty($v)) {
                $firstkey = $k;
            }
        }
        unset($rootsRootArray[$firstkey]);
        unset($rootsIDArray[$firstkey]);
        
        //add the first element to the final array
        $result = array();
        $result[0] = $data[$firstkey];
        unset($data[$firstkey]);
        //get the second level
        $key = array_search($result[0]['rootId'], $rootsRootArray);
        //count how many levels we have
        $count = count($rootsRootArray);
        //go through on the levels

        for ($i = 0; $i < $count; $i++) {
            if (isset($result[$i]['rootId'])) {
                $id = $result[$i]['rootId'];
                if ($id) {
                    $rootID = array_search($id, $rootsRootArray);
                    if (isset($data[$rootID])) {
                        $result[] = $data[$rootID];
                        unset($data[$rootID]);
                    }
                }
            }
        }
        
        $result = $this->formatBreadcrumbInsideUri($result);
        return $result;
    }
    
    /**
     * A recursive function to we can reorder the breadcrumbs data
     *
     * @param array $data
     * @param array $result
     */
    private function makeBreadcrumbFinalData(array &$data, array &$result)
    {
        //if it is using too much memory, then we will skip it
        if ((memory_get_usage() / 1024 /1024) < 70) {
            return;
        }
        
        foreach ($data as $k => $v) {
            $count = count($result);
            if ($count > 0) {
                $count = $count -1;
            }
            if ($result[$count]['rootId'] == $v['rootsRoot']) {
                $result[] = $v;
                unset($data[$k]);
            } elseif (empty($v['rootsRoot'])) {
                //because sometimes we have multiple ispartof values...
                unset($data[$k]);
            }
        }
    
        if (count($data) > 0) {
            $this->makeBreadcrumbFinalData($data, $result);
        }
    }
    
    /**
     * Creates an inside GUI uri based on the identifier
     * This is for the breadcrumb
     *
     * @param array $data
     * @return array
     */
    private function formatBreadcrumbInsideUri(array $data): array
    {
        $result = array();
        $result = $data;
        foreach ($result as $k => $v) {
            if (isset($v['rootId'])) {
                $result[$k]['insideUri'] = $this->detailViewUrlDecodeEncode($v['rootId'], 1);
            }
        }
        return $result;
    }
    
    
    /**
      *
      * Create turtle file from the resource
      *
      * @param string $fedoraUrl
      * @return type
      */
    public function turtleDissService(string $fedoraUrl)
    {
        $result = array();
        $client = new \GuzzleHttp\Client();
        
        try {
            $request = $client->request('GET', $fedoraUrl.'/fcr:metadata', ['Accept' => ['application/n-triples']]);
            if ($request->getStatusCode() == 200) {
                $body = "";
                $body = $request->getBody()->getContents();
                if (!empty($body)) {
                    $graph = new \EasyRdf_Graph();
                    $graph->parse($body);
                    return $graph->serialise('turtle');
                }
            }
        } catch (\GuzzleHttp\Exception\ClientException $ex) {
            return "";
        } catch (\Exception $ex) {
            return "";
        }
    }
    
    /**
     * Handle the default shibboleth user for the federated login
     *
     */
    public function handleShibbolethUser()
    {
        //the global drupal shibboleth username
        $shib = user_load_by_name('shibboleth');
        //if we dont have it then we will create it
        if ($shib === false) {
            $user = \Drupal\user\Entity\User::create();
            // Mandatory.
            $user->setPassword(RC::get('shibbolethUserPWD'));
            $user->enforceIsNew();
            $user->setEmail('sh_guest@acdh.oeaw.ac.at');
            $user->setUsername('shibboleth');
            $user->activate();
            $user->save();
            $shib = user_load_by_name('shibboleth');
            user_login_finalize($user);
        } elseif ($shib->id() != 0) {
            $user = \Drupal\User\Entity\User::load($shib->id());
            $user->activate();
            user_login_finalize($user);
        }
    }
    
    /**
     * The error message generating for the detail view
     *
     * @param string $response
     * @param string $msg_translation
     * @param string $message
     * @param string $uuid
     * @return Response/array
     */
    public function detailViewGuiErrosMsg(string $response = "html", string $msg_translation, string $message, string $uuid)
    {
        if (!empty($message)) {
            $result = drupal_set_message($this->langConf->get($message).' identifier: '.$uuid, 'error');
        } else {
            $result = drupal_set_message($msg_translation.' identifier: '.$uuid, 'error');
        }
        
        if ($response == "html") {
            return array();
        }
        $result = ($this->langConf->get($message)) ? $this->langConf->get($message).' identifier: '.$uuid : $msg_translation.' identifier: '.$uuid;
        return new Response($result, 200, ['Content-Type'=> 'text/html']);
    }
}
