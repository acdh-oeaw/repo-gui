<?php
/**
 * @file
 * Contains \Drupal\oeaw\Plugin\Block\SidebarTypeOfResourceBlock.
 */

namespace Drupal\oeaw\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'SidebarTypeOfResourceBlock' block.
 *
 * @Block(
 *   id = "stor_block",
 *   admin_label = @Translation("Sidebar Type of Resource Filter OEAW"),
 *   category = @Translation("Custom sidebar type of resource filter oeaw")
 * )
 */
class SidebarTypeOfResourceBlock extends BlockBase
{
    
    /**
     * Sidebar of type resources
     * @return type
     */
    public function build()
    {
        $form = \Drupal::formBuilder()->getForm('Drupal\oeaw\Form\SidebarTypeOfResourceForm');
        return $form;
    }
}
