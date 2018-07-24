<?php
/**
 * @file
 * Contains \Drupal\oeaw\Plugin\Block\SidebarKeywordSearchBlock.
 */

namespace Drupal\oeaw\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'SidebarKeywordSearch' block.
 *
 * @Block(
 *   id = "sks_block",
 *   admin_label = @Translation("Sidebar Keyword Search OEAW"),
 *   category = @Translation("Custom sidebar keyword search oeaw")
 * )
 */
class SidebarKeywordSearchBlock extends BlockBase {

    /**
     * Sidebar keyword search
     * 
     * @return type
     */
    public function build() 
    {
        $form = \Drupal::formBuilder()->getForm('Drupal\oeaw\Form\SidebarKeywordSearchForm');
        return $form;
    }
    
}
