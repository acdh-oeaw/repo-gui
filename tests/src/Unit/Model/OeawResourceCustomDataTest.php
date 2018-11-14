<?php

namespace Drupal\Tests\oeaw\Model\OeawResourceCustomDataTest;
namespace Drupal\oeaw\Model;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use acdhOeaw\util\RepoConfig as RC;

require_once $_SERVER['HOME'].'/html/vendor/autoload.php';

/**
 * @coversDefaultClass \Drupal\oeaw\Model\OeawResourceCustomData
 * @group oeaw
 */

class OeawResourceCustomDataTest extends UnitTestCase {
    
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
    
    
    static public function setUpBeforeClass() {
        self::$arrayObject = new \ArrayObject();
        self::$arrayObject->offsetSet('uri', 'http://localhost');
        self::$arrayObject->offsetSet('title', 'my title');
        self::$arrayObject->offsetSet('type', 'Collection');
        self::$arrayObject->offsetSet('identifiers', array('https://id.acdh.oeaw.ac.at/uuid/12313-223-11', 'https://id.acdh.oeaw.ac.at/myId', 'https://outside.url.at'));
        self::$arrayObject->offsetSet('insideUri', 'id.acdh.oeaw.ac.at%20dictgate');
        self::$arrayObject->offsetSet('typeUri', 'http://type.com');
        self::$arrayObject->offsetSet('basicProperties', array('acdh:HasTitle' => "my title"));
        self::$arrayObject->offsetSet('extendedProperties', array());
    }
    
    public function testInitialization() : \Drupal\oeaw\Model\OeawResourceCustomData {
        $obj = new \Drupal\oeaw\Model\OeawResourceCustomData(self::$arrayObject, $this->cfgDir);
        $this->assertInstanceOf(\Drupal\oeaw\Model\OeawResourceCustomData::class, $obj);
        return $obj;
    }
   
    
    public function testFalseInitialization() {
        self::$arrayObject->offsetSet('type', '');
        $this->expectException(\Error::class);
        $obj = new \Drupal\oeaw\Model\OeawResourceCustomData(self::$arrayObject, $this->cfgDir);
    }
    
    /**
     * @depends testInitialization
     */
    public function testGetUri($obj) {
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
        $data = $obj->getIdentifiers();
        //the result is an array with the ids
        $this->assertInternalType('array',$data);
        $this->assertArraySubset(array('https://id.acdh.oeaw.ac.at/uuid/12313-223-11'),$data);
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
    public function testGetAccessRestriction($obj) {
        $data = $obj->getAccessRestriction();
        $this->assertNotEmpty($data);
        $this->assertInternalType('string',$data);
    }
    
    /**
     * @depends testInitialization
     */        
    public function testGetBasicProperties($obj) {
        $data = $obj->getBasicProperties();
        if(!empty($data)) {
            $this->assertNotEmpty($data);
            $this->assertInternalType('array',$data);
        } else {
            $this->assertEmpty($data);
        }    
    }
    
    /**
     * @depends testInitialization
     */        
    public function testGetExtendedProperties($obj) {
        $data = $obj->getExtendedProperties();
        if(!empty($data)) {
            $this->assertNotEmpty($data);
            $this->assertInternalType('array',$data);
        } else {
            $this->assertEmpty($data);
        }
    }
    
    /**
     * @depends testInitialization
    */        
    public function testGetAcdhIdentifier($obj) {
        $data = $obj->getAcdhIdentifier();
        $this->assertNotEmpty($data);
        $this->assertInternalType('string',$data);
       
    }
    
    /**
     * @depends testInitialization
    */        
    public function testSetupBasicExtendedData($obj) {
        $obj = $obj;
        $this->assertObjectHasAttribute('bpKeys', $obj);
        $this->assertObjectHasAttribute('epKeys', $obj);
    }
}

