<?php

namespace Drupal\Tests\oeaw\Model\OeawResourceTest;
namespace Drupal\oeaw\Model;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use acdhOeaw\util\RepoConfig as RC;

require_once $_SERVER['HOME'].'/html/vendor/autoload.php';

/**
 * @coversDefaultClass \Drupal\oeaw\Model\OeawResource
 * @group oeaw
 */

class OeawResourceTest extends UnitTestCase {
    
    static private $arrayObject;
    private $cfgDir = '/var/www/html/modules/oeaw/config.ini';
        
    /**
    * Shadow t() system call.
    *
    * @param string $string
    *   A string containing the English text to translate.
    *
    * @return string
    */
    public function t($string) {
        return $string;
    }
    
    private function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    protected function setUp() {
        //we need to setup the configfactory with the "oeaw.settings" config, because of
        // the multilanguage support.
         $this->config = $this->getMockBuilder('\Drupal\Core\Config\ImmutableConfig')
            ->disableOriginalConstructor()
            ->getMock();

        $this->configFactory = $this->getMockBuilder('\Drupal\Core\Config\ConfigFactory')
            ->disableOriginalConstructor()
            ->getMock();
        
        $this->configFactory->expects($this->any())
            ->method('get')
            ->with('oeaw.settings')
            ->willReturn($this->config);

        $this->container = new ContainerBuilder();
        $this->container->set('config.factory', $this->configFactory);
        \Drupal::setContainer($this->container);
        \Drupal::config('oeaw.settings');
    }
    
    
    static public function setUpBeforeClass() 
    {
        self::$arrayObject = new \ArrayObject();
        self::$arrayObject->offsetSet('uri', 'http://localhost');
        self::$arrayObject->offsetSet('insideUri', 'id.acdh.oeaw.ac.at%20dictgate');
        self::$arrayObject->offsetSet('fedoraUri', 'http://fedora.localhost');
        self::$arrayObject->offsetSet('identifiers', array('https://id.acdh.oeaw.ac.at/uuid/12313-223-11', 'https://id.acdh.oeaw.ac.at/myId'));
        self::$arrayObject->offsetSet('title', 'title');
        self::$arrayObject->offsetSet('type', 'Collection');
        self::$arrayObject->offsetSet('typeUri', 'http://type.com');
        self::$arrayObject->offsetSet('table', array("acdh:hasIdentifier" => array("www.identifier.com"), "acdh:hasTitle" => array("My Test Title")));
        
    }
    
    public function testInitialization() : \Drupal\oeaw\Model\OeawResource
    {
        $obj = new \Drupal\oeaw\Model\OeawResource(self::$arrayObject, $this->cfgDir);
        $this->assertInstanceOf(\Drupal\oeaw\Model\OeawResource::class, $obj);
        return $obj;
    }
    
    /**
     * @depends testInitialization
     */
    public function testTableDataModify($obj)
    {
        //$obj = new \Drupal\oeaw\Model\OeawResource(self::$arrayObject, $this->cfgDir);
        $this->assertEquals(false, $obj->setTableData("acdh:hasTitle1", array("title")));
        $this->assertEquals(false, $obj->setTableData("acdh:hasTitle1", array("")));
        $this->assertEquals(true, $obj->setTableData("acdh:hasTitle", array("title")));
    }
    
    public function testFalseInitialization()
    {
        self::$arrayObject->offsetUnset('table');
        $this->expectException(\Error::class);
        $obj = new \Drupal\oeaw\Model\OeawResource(self::$arrayObject, $this->cfgDir);
    }
    
    
    /**
     * @depends testInitialization
     */
    public function testGetUri($obj)
    {
        $uri = $obj->getUri();
        $this->assertNotEmpty($uri);
        $this->assertInternalType('string',$uri);
        $this->assertRegExp('/http/i',$uri);
    }
    
    /**
     * @depends testInitialization
     */
    public function testGetInsideUri($obj) {
        $insideUri = $obj->getInsideUri();
        $this->assertNotEmpty($insideUri);
        $this->assertInternalType('string',$insideUri);
        $this->assertRegExp('/id.acdh.oeaw.ac.at/i',$insideUri);
    }
    
    /**
     * @depends testInitialization
     */
    public function testGetIdentifiers($obj) {
        $ids = $obj->getIdentifiers();
        //we have values
        $this->assertNotEmpty($ids);
        //the result is an array with the ids
        $this->assertInternalType('array',$ids);
        
        foreach ($ids as $id){
            //check that we have acdh identifier
            $this->assertRegExp('/id.acdh.oeaw.ac.at/i',$id);
        }
    }        
    /**
     * @depends testInitialization
     */
    public function testGetFedoraUri($obj) {
        $acdhID = $obj->getAcdhIdentifier();
        $this->assertNotEmpty($acdhID);
        $this->assertInternalType('string',$acdhID);
        $this->assertRegExp('/id.acdh.oeaw.ac.at/i',$acdhID);
    }
    
    /**
     * @depends testInitialization
     */
    public function testGetTitle($obj) {
        $data = $obj->getTitle();
        $this->assertNotEmpty($data);
        $this->assertInternalType('string',$data);
    }
    
    /**
     * @depends testInitialization
     */
    public function testGetType($obj) {
        $data = $obj->getType();
        $this->assertNotEmpty($data);
        $this->assertInternalType('string',$data);
    }
    
    /**
     * @depends testInitialization
     */
    public function testGetTypeUri($obj) {
        $data = $obj->getTypeUri();
        $this->assertNotEmpty($data);
        $this->assertInternalType('string',$data);
        $this->assertRegExp('/http/i',$data);
    }
    /**
     * @depends testInitialization
     */
    public function testGetImageUrl($obj) {
        $data = $obj->getImageUrl();
        if(!empty($data)) {
            $this->assertNotEmpty($data);
            $this->assertInternalType('string',$data);
            $this->assertRegExp('http/i',$data);
        } else {
            $this->assertEmpty($data);
        }
    }
    /**
     * @depends testInitialization
     */        
    public function testGetPID($obj) {
        $data = $obj->getPID();
        if(!empty($data)) {
            $this->assertNotEmpty($data);
            $this->assertInternalType('string',$data);
            $this->assertRegExp('http/i',$data);
        } else {
            $this->assertEmpty($data);
        }
    }
    /**
     * @depends testInitialization
     */
    public function testGetTable($obj) {
        $data = $obj->getTable();
        $this->assertNotEmpty($data);
        $this->assertInternalType('array',$data);
    }
    /**
     * @depends testInitialization
     */        
    public function testGetAvailableDate($obj) {
        $data = $obj->getAvailableDate();
        if(!empty($data)) {
            $this->assertNotEmpty($data);
            $this->assertInternalType('string',$data);
            $this->assertEquals(true, $this->validateDate($data));
        } else {
            $this->assertEmpty($data);
        }
    }
    /**
     * @depends testInitialization
     */        
    public function testGetAccessRestriction($obj) {
        $data = $obj->getAccessRestriction();
        $this->assertNotEmpty($data);
        $this->assertInternalType('string',$data);
    }
    /**
     * @depends testInitialization
     */   
    public function testGetTableData($obj) {
        //valid
        $data = $obj->getTableData("acdh:hasIdentifier");
        $this->assertNotEmpty($data);
        $this->assertInternalType('array',$data);
        
        //not valid
        $data = $obj->getTableData("acdh:hasIdentifier1");
        $this->assertEmpty($data);
    }
    /**
     * @depends testInitialization
     */        
    public function testGetHighlighting($obj) {
        $data = $obj->getHighlighting();
        if(!empty($data)) {
            $this->assertNotEmpty($data);
            $this->assertInternalType('array',$data);
        } else {
            $this->assertEmpty($data);
        }
    }
}