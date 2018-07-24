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
class StartPageLeftBlock extends BlockBase 
{
    /**
     * Left block build function
     * @return type
     */
    public function build() 
    {
        $result = array();
	$this->FrontendController = new FrontendController();
        $data = $this->FrontendController->roots_list(3,1,'datedesc');
        
        if(count($data) > 0){
            if(isset($data['#result'])){
                $result = $data['#result'];    
            }
        }
        $datatable['#theme'] = 'oeaw_start_left_block';
        $datatable['#result'] = $result;
		            
        return $datatable;
    }
}
