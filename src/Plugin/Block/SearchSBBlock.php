<?php
/**
 * @file
 * Contains \Drupal\oeaw\Plugin\Block\SearchSBBlock.
 */

namespace Drupal\oeaw\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'SearchSD' block.
 *
 * @Block(
 *   id = "searchsb_block",
 *   admin_label = @Translation("Sidebar Search OEAW"),
 *   category = @Translation("Custom sidebar search oeaw")
 * )
 */
class SearchSBBlock extends BlockBase {

    /**
    * {@inheritdoc}
    */
    public function build() {
        
        $form = \Drupal::formBuilder()->getForm('Drupal\oeaw\Form\SearchForm');
         
        return $form;
 
    }
    
}
