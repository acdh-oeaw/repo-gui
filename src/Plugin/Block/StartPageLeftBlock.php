<?php
/**
 * @file
 * Contains \Drupal\oeaw\Plugin\Block\StartPageLeftBlock.
 */

namespace Drupal\oeaw\Plugin\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\oeaw\Controller\FrontendController;

/**
 * Provides a 'StartPageLeftBlock' block.
 *
 * @Block(
 *   id = "startpageleftblock",
 *   admin_label = @Translation("Start Page Left Block"),
 *   category = @Translation("Provides search bar and latest additions linked to Fedora")
 * )
 */
class StartPageLeftBlock extends BlockBase {

    /**
    * {@inheritdoc}
    */
    public function build() {
    
	    $this->FrontendController = new FrontendController();
	    
        $data = $this->FrontendController->roots_list(3);

        $datatable['#theme'] = 'oeaw_start_left_block';
        $datatable['#result'] = $data['#result'];
		            
        return $datatable;

    }
    
}
