<?php
/**
 * @file
 * Contains \Drupal\oeaw\Plugin\Block\SidebarTypeOfResourceBlock.
 */

namespace Drupal\oeaw\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'SidebarDateBlock' block.
 *
 * @Block(
 *   id = "sdate_block",
 *   admin_label = @Translation("Sidebar Date of Publication Filter OEAW"),
 *   category = @Translation("Custom sidebar date of resource filter oeaw")
 * )
 */
class SidebarDateBlock extends BlockBase {
    
    /**
     * Sidebar date block
     * 
     * @return type
     */
    public function build() 
    {
        $form = \Drupal::formBuilder()->getForm('Drupal\oeaw\Form\SidebarDateForm');
        return $form;
    }
    
}
