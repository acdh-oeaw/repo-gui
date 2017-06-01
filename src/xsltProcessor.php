<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


namespace Drupal\oeaw;

class xsltProcessor
{
    static function getData($xsltURL, $dataURL)
    {
        $xslDoc = new \DOMDocument();
        $xslDoc->loadXML(file_get_contents($xsltURL));
	
        $xmlDoc = new \DOMDocument();
        $xmlDoc->loadXML(file_get_contents($dataURL));
           
        $proc = new \XSLTProcessor();   
        $proc->importStylesheet($xslDoc);
        $data = $proc->transformToXML($xmlDoc);
        
        return $data;        
    }
}
