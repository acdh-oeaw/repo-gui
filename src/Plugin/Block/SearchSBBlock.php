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
 *   admin_label = @Translation("Search"),
 *   category = @Translation("Custom complex search oeaw")
 * )
 */
class SearchSBBlock extends BlockBase {

    /**
     * Search Sb block
     * 
     * @return type
     */
    public function build() 
    {
        $form = \Drupal::formBuilder()->getForm('Drupal\oeaw\Form\ComplexSearchForm');
        return $form;
    }
    
}
