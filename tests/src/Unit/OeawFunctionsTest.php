<?php

declare(strict_types=1);

namespace Drupal\Tests\oeaw\Model\OeawFunctionsTest;

namespace Drupal\oeaw\Model;

use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use acdhOeaw\util\RepoConfig as RC;

/**
 * @coversDefaultClass \Drupal\oeaw\OeawFunctions
 * @group oeaw
 */

class OeawFunctionsTest extends UnitTestCase
{
    private $oeawFunctions;
    private $cfgDir;
    private $acdhId = 'https://id.acdh.oeaw.ac.at/myidentifier';
    private $acdhUUID = 'https://id.acdh.oeaw.ac.at/uuid/myidentifier';
    private $pid = 'http://hdl.handle.net/21.11115/0000-0000';
    
    protected function setUp()
    {
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
        $this->oeawFunctions = new \Drupal\oeaw\OeawFunctions($this->cfgDir);
    }
    
    
    public function testCreateDetailViewUrlAcdhID()
    {
        $data = array("title" => "my example title", "identifier" => $this->acdhId.",".$this->acdhUUID);
        $this->assertArrayHasKey('identifier', $data);
        
        $idRes = $this->oeawFunctions->createDetailViewUrl($data);
        $this->assertContains('http', $idRes);
    }
    
    public function testCreateDetailViewUrlPID()
    {
        $data = array("title" => "my example title", "identifier" => $this->acdhId.",".$this->acdhUUID, "pid" => $this->pid);
        $this->assertArrayHasKey('identifier', $data);
        
        $idRes = $this->oeawFunctions->createDetailViewUrl($data);
        $this->assertContains('handle', $idRes);
    }
    
    public function testCreateDetailViewUrlEmptyID()
    {
        $data = array("title" => "my example title", "identifier" => "");
        $this->assertArrayHasKey('identifier', $data);
        $this->assertEmpty($data['identifier'], 'this is empty!');
    }
    /*
    public function testInitFedora()
    {
     //   $fd = $this->oeawFunctions->initFedora();
    }
     *
     */
}
    




//$this->expectException(\ErrorException::class);
