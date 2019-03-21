<?php

namespace Drupal\Tests\oeaw\Model\OeawResourceChildrenTest;
namespace Drupal\oeaw\Model;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use acdhOeaw\util\RepoConfig as RC;

/**
 * @coversDefaultClass \Drupal\oeaw\Model\OeawResourceChildren
 * @group oeaw
 */

class OeawResourceChildrenTest extends UnitTestCase {
    
    static private $arrayObject;
    private $cfgDir;
    
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
        $this->cfgDir = $_SERVER['TRAVIS_BUILD_DIR']."/drupal/modules/oeaw/config.unittest.ini";
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
        self::$arrayObject->offsetSet('title', 'title');
        self::$arrayObject->offsetSet('description', 'my description');
        self::$arrayObject->offsetSet('typeUri', 'http://type.com');
        self::$arrayObject->offsetSet('identifier', 'https://id.acdh.oeaw.ac.at/uuid/12313-223-11');
        self::$arrayObject->offsetSet('insideUri', 'id.acdh.oeaw.ac.at%20dictgate');
        self::$arrayObject->offsetSet('typeName', 'Collection');
    }
    
    public function testInitialization() : \Drupal\oeaw\Model\OeawResourceChildren {
        $obj = new \Drupal\oeaw\Model\OeawResourceChildren(self::$arrayObject, $this->cfgDir);
        $this->assertInstanceOf(\Drupal\oeaw\Model\OeawResourceChildren::class, $obj);
        return $obj;
    }
   
    
    public function testFalseInitialization() {
        self::$arrayObject->offsetSet('typeName', '');
        $this->expectException(\Error::class);
        $obj = new \Drupal\oeaw\Model\OeawResourceChildren(self::$arrayObject);
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
    public function testGetIdentifier($obj) {
        $data = $obj->getIdentifier();
        $this->assertNotEmpty($data);
        $this->assertInternalType('string',$data);
        $this->assertRegExp('/id.acdh.oeaw.ac.at/i',$data);
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
    public function testGetTypeName($obj) {
        $data = $obj->getTypeName();
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
    public function testGetDescription($obj) {
        $data = $obj->getDescription();
        if(!empty($data)) {
            $this->assertNotEmpty($data);
            $this->assertInternalType('string',$data);
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
    public function testGetAccessRestriction($obj) {
        $data = $obj->getAccessRestriction();
        $this->assertNotEmpty($data);
        $this->assertInternalType('string',$data);
    }
   
}

