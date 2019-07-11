<?php

namespace Drupal\oeaw\Model;

use acdhOeaw\util\RepoConfig as RC;

class CacheModel
{
    private $db;
    private $query;
    private $table = "cache";
    
    public function __construct()
    {
        \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/oeaw/config.ini');
        $this->db = \Drupal\Core\Database\Database::getConnection('default', 'external');
    }
    
    private function changeBackDBConnection()
    {
        \Drupal\Core\Database\Database::setActiveConnection();
    }
    
    private function convertUUID(string $uuid): string
    {
        return str_replace(RC::get('fedoraUuidNamespace'), "", $uuid);
    }
    
    /**
     * Get all cache for testing
     *
     * @return array
     */
    private function getAllCache(): array
    {
        $this->query = $this->db->query("SELECT * FROM {".$this->table."}");
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
    public function getCacheByUUID(string $uuid, string $lang = "en", string $type = "R"): \stdClass
    {
        $uuid = $this->convertUUID($uuid);
        $result = new \stdClass();
        $lang = strtolower($lang);
        $type = strtoupper($type);
        
        try {
            $sql = "SELECT * FROM {".$this->table."} where uuid = :uuid and type = :type and language = :language ";
            $this->query = $this->db->query($sql, [':uuid' => $uuid, ':type' => $type, ':language' => $lang]);
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
    private function deleteCacheByUUID(string $uuid, string $type = "R", string $lang = "en"): bool
    {
        $uuid = $this->convertUUID($uuid);
        $lang = strtolower($lang);
        $type = strtoupper($type);
        $result = false;
            
        try {
            $sql = "DELETE FROM {".$this->table."} where uuid = :uuid and type = :type and language = :language";
            $res = $this->query = $this->db->query($sql, [':uuid' => $uuid, ':type' => $type, ':language' => $lang]);
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
    public function addCacheToDB(string $uuid, string $data, string $type = "R", int $modifydate, string $lang = "en"): bool
    {
        $lang = strtolower($lang);
        $type = strtoupper($type);
        $exists = $this->getCacheByUUID($uuid, $lang, $type);      
        
        if (count((array)$exists) > 0) {
            if (!$this->deleteCacheByUUID($uuid, $type, $lang)) {
                return false;
            }
        }
       
        $uuid = $this->convertUUID($uuid);
        
        try {
            $result = $this->db->insert($this->table)
                ->fields(
                    array(
                        'uuid' => $uuid,
                        'type' => $type,
                        'data' => $data,
                        'modify_date' => $modifydate,
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
