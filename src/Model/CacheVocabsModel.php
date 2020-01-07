<?php

namespace Drupal\oeaw\Model;

use acdhOeaw\util\RepoConfig as RC;

class CacheVocabsModel
{
    private $db;
    private $query;
    private $table = "cache_vocabs";
    
    public function __construct()
    {
        $this->db = \Drupal\Core\Database\Database::getConnection('default', 'external');
    }
    
    private function changeBackDBConnection()
    {
        \Drupal\Core\Database\Database::setActiveConnection();
    }
    
    /**
     * Get all cache for testing
     *
     * @return array
     */
    public function getAllCache(string $lang = "en"): array
    {
        $sql = "SELECT * FROM {".$this->table."} WHERE language = :language ";
        $this->query = $this->db->query($sql, [':language' => $lang]);
        $result = $this->query->fetchAll();
        $this->changeBackDBConnection();
        return $result;
    }
    
        
    public function getAllCacheByProperty(string $prop, string $lang = "en"): array
    {
        $sql = "SELECT * FROM {".$this->table."} WHERE language = :language and property = :property ";
        $this->query = $this->db->query($sql, [':language' => $lang, ':property' => $prop]);
        $result = $this->query->fetchAll();
        $this->changeBackDBConnection();
        return $result;
    }
    
    
    /**
     * Get the already cached resource data
     *
     * @param string $uuid
     * @return stdClass
     */
    public function getCacheByPropUri(string $prop, string $label, string $uri, string $lang = "en"): \stdClass
    {
        $result = new \stdClass();
        $lang = strtolower($lang);
        $uri = strtolower($uri);
        
        try {
            $sql = "SELECT * FROM {".$this->table."} where property = :prop and uri = :uri and language = :language ";
            $this->query = $this->db->query($sql, [':prop' => $prop, ':uri' => $uri, ':language' => $lang]);
            $result = $this->query->fetchObject();
            if ($result === false) {
                $result = new \stdClass();
            }

            $this->changeBackDBConnection();
        } catch (Exception $ex) {
            $result = new \stdClass();
        } catch (\Drupal\Core\Database\DatabaseExceptionWrapper $ex) {
            $result = new \stdClass();
        }
        
        return $result;
    }
    
    /**
     * Delete the existing resource from the DB to we can update it with new data
     *
     * @param string $uuid
     * @return bool
     */
    private function deleteCacheByPropUri(string $prop, string $label, string $uri, string $lang = "en"): bool
    {
        $lang = strtolower($lang);
        $uri = strtolower($uri);
        $result = false;
            
        try {
            $sql = "DELETE FROM {".$this->table."} where property = :prop and uri = :uri and language = :language";
            $res = $this->query = $this->db->query($sql, [':prop' => $prop, ':uri' => $uri, ':language' => $lang]);
            $result = true;
            $this->changeBackDBConnection();
        } catch (Exception $ex) {
            $result = false;
        } catch (\Drupal\Core\Database\DatabaseExceptionWrapper $ex) {
            $result = false;
        }
        return $result;
    }
    
    /**
     *
     * Add the resource cache to Database
     *
     * @param string $uuid
     * @param string $data
     * @param string $type
     * @param int $modifydate
     * @return bool
     */
    public function addCacheToDB(string $prop, string $label, string $uri, string $lang = "en"): bool
    {
        $lang = strtolower($lang);
        $uri = strtolower($uri);
        $exists = $this->getCacheByPropUri($prop, $label, $uri, $lang);
        
        if (count((array)$exists) > 0) {
            if (!$this->deleteCacheByPropUri($prop, $label, $uri, $lang)) {
                return false;
            }
        }
        
        try {
            $result = $this->db->insert($this->table)
                ->fields(
                    array(
                        'property' => $prop,
                        'label' => $label,
                        'uri' => $uri,
                        'modify_date' => strtotime(date("Y-m-d H:i:s")),
                        'language' => $lang
                    )
                )->execute();
            return true;
        } catch (Exception $ex) {
            return false;
        } catch (\Drupal\Core\Database\DatabaseExceptionWrapper $ex) {
            return false;
        } catch (\Drupal\Core\Database\IntegrityConstraintViolationException $ex) {
            return false;
        }
    }
}
