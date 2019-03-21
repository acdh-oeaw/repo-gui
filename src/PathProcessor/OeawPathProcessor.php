<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


namespace Drupal\oeaw\PathProcessor;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Symfony\Component\HttpFoundation\Request;

class OeawPathProcessor implements InboundPathProcessorInterface
{
    public function processInbound($path, Request $request)
    {
        if (strpos($path, '/oeaw_detail/') === 0) {
            $names = preg_replace('|^\/oeaw_detail\/|', '', $path);
            $names = str_replace('/', ':', $names);
            return "/oeaw_detail/$names";
        }
        
        if (strpos($path, '/get_collection_data/') === 0) {
            $names = preg_replace('|^\/get_collection_data\/|', '', $path);
            $names = str_replace('/', ':', $names);
            return "/get_collection_data/$names";
        }
        
        if (strpos($path, '/oeaw_dl_collection/') === 0) {
            $names = preg_replace('|^\/oeaw_dl_collection\/|', '', $path);
            $names = str_replace('/', ':', $names);
            return "/oeaw_dl_collection/$names";
        }
        
        
        if (strpos($path, '/oeaw_dlc/') === 0) {
            $names = preg_replace('|^\/oeaw_dlc\/|', '', $path);
            $names = str_replace('/', ':', $names);
            return "/oeaw_dlc/$names";
        }
        
        if (strpos($path, '/oeaw_inverse_result/') === 0) {
            $names = preg_replace('|^\/oeaw_inverse_result\/|', '', $path);
            $names = str_replace('/', ':', $names);
            return "/oeaw_inverse_result/$names";
        }
        
        if (strpos($path, '/api/checkIdentifier/') === 0) {
            $names = preg_replace('|^\/api/checkIdentifier\/|', '', $path);
            $names = str_replace('/', ':', $names);
            return "/api/checkIdentifier/$names";
        }
        
        if (strpos($path, '/oeaw_ismember_result/') === 0) {
            $names = preg_replace('|^\/oeaw_ismember_result\/|', '', $path);
            $names = str_replace('/', ':', $names);
            return "/oeaw_ismember_result/$names";
        }
        
        if (strpos($path, '/oeaw_turtle_api/') === 0) {
            $names = preg_replace('|^\/oeaw_turtle_api\/|', '', $path);
            $names = str_replace('/', ':', $names);
            return "/oeaw_turtle_api/$names";
        }
        
        if (strpos($path, '/oeaw_3d_viewer/') === 0) {
            $names = preg_replace('|^\/oeaw_3d_viewer\/|', '', $path);
            $names = str_replace('/', ':', $names);
            return "/oeaw_3d_viewer/$names";
        }
        
        if (strpos($path, '/iiif_viewer/') === 0) {
            $names = preg_replace('|^\/iiif_viewer\/|', '', $path);
            $names = str_replace('/', ':', $names);
            return "/iiif_viewer/$names";
        }
        
        return $path;
    }
}
