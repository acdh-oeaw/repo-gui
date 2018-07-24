<?php
/**
 * @file
 * Contains \Drupal\oeaw\Plugin\Block\ClassBlock.
 */

namespace Drupal\oeaw\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'ClassBlock' block.
 *
 * @Block(
 *   id = "classsb_block",
 *   admin_label = @Translation("Sidebar Class List OEAW"),
 *   category = @Translation("Custom sidebar class list oeaw")
 * )
 */
class ClassBlock extends BlockBase {

    /**
     * Class block
     * 
     * @return type
     */
    public function build() 
    {
        $form = \Drupal::formBuilder()->getForm('Drupal\oeaw\Form\ClassForm');
        return $form;
    }
    
}
