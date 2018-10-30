<?php
/**
 * @file
 * Contains \Drupal\oeaw\Plugin\Block\LangSwitcherBlock.
 */

namespace Drupal\oeaw\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'LangSwitcherBlock' block.
 *
 * @Block(
 *   id = "lang_switcher_block",
 *   admin_label = @Translation("OEAW Language Switcher"),
 *   category = @Translation("Custom oeaw language switcher")
 * )
 */
class LangSwitcherBlock extends BlockBase {

    /**
     * Class block
     * 
     * @return type
     */
    public function build() 
    {
        if(isset($_SESSION['language'])){ $lang = strtolower($_SESSION['language']); } else { $lang = "en"; }
        
        $return = array(
            '#theme' => 'oeaw_lng_switcher',
            '#language' => $lang,
            '#attached' => [
                'library' => [
                'oeaw/oeaw-styles', //include our custom library for this response
                ]
            ]
        );
        return $return;
    }
    
}
