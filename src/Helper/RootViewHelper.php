<?php

namespace Drupal\oeaw\Helper;

/**
 * Description of RootViewHelper
 *
 * @author nczirjak
 */

use acdhOeaw\util\RepoConfig as RC;
use Drupal\oeaw\OeawFunctions;
use Drupal\oeaw\Model\RootViewModel;
use Drupal\oeaw\Helper\HelperFunctions as HF;

class RootViewHelper
{
    private $siteLang;
    private $oeawFunctions;
    private $oeawStorage;
    private $model;
    private $fedora;
    
    public function __construct(
        $siteLang,
        \Drupal\oeaw\OeawFunctions $oeawFunctions,
        \Drupal\oeaw\Model\OeawStorage $oeawStorage,
        $fedora
    ) {
        $this->siteLang = $siteLang;
        $this->oeawFunctions = $oeawFunctions;
        $this->oeawStorage = $oeawStorage;
        $this->fedora = $fedora;
        $this->model = new RootViewModel($this->fedora);
    }
    
    
    /**
     * Count the actual root elements from the database
     *
     * @return type
     */
    public function countRoots()
    {
        //count all root resource for the pagination
        try {
            return $this->model->getRootFromDB(0, 0, true, "datedesc", $this->siteLang);
        } catch (\Exception $ex) {
            drupal_set_message($ex->getMessage(), 'error');
            return array();
        } catch (\InvalidArgumentException $ex) {
            drupal_set_message($ex->getMessage(), 'error');
            return array();
        }
    }
    
    /**
     * Get the actual root resources
     *
     * @param string $limit
     * @param string $offsetRoot
     * @param string $order
     * @return type
     */
    public function getRoots(string $limit, string $offsetRoot, string $order)
    {
        try {
            return $this->model->getRootFromDB($limit, $offsetRoot, false, $order, $this->siteLang);
        } catch (Exception $ex) {
            drupal_set_message($ex->getMessage(), 'error');
            return array();
        } catch (\InvalidArgumentException $ex) {
            drupal_set_message($ex->getMessage(), 'error');
            return array();
        }
    }


    /**
     * Create the oeawresource object with data
     *
     * @param array $data
     * @return \Drupal\oeaw\Model\OeawResource
     * @throws \ErrorException
     */
    public function createRootViewObject(array $data)
    {
        $result = array();
        foreach ($data as $value) {
            $tblArray = array();

            $arrayObject = new \ArrayObject();
            $arrayObject->offsetSet('title', $value['title']);

            $resourceIdentifier = $this->oeawFunctions->createDetailViewUrl($value);
            $arrayObject->offsetSet('uri', $resourceIdentifier);
            $arrayObject->offsetSet('fedoraUri', $value['uri']);
            $arrayObject->offsetSet('insideUri', $this->oeawFunctions->detailViewUrlDecodeEncode($resourceIdentifier, 1));
            $arrayObject->offsetSet('identifiers', $value['identifier']);
            $arrayObject->offsetSet('pid', $value['pid']);
            $arrayObject->offsetSet('type', str_replace(RC::get('fedoraVocabsNamespace'), '', $value['acdhType']));
            $arrayObject->offsetSet('typeUri', $value['acdhType']);
            $arrayObject->offsetSet('availableDate', $value['availableDate']);
            
            if (isset($value['accessRestriction']) && !empty($value['accessRestriction'])) {
                $arrayObject->offsetSet('accessRestriction', $value['accessRestriction']);
                if (strpos($value['accessRestriction'], '/public') !== false) {
                    if (isset($value['image']) && !empty($value['image'])) {
                        $arrayObject->offsetSet('imageUrl', $value['image']);
                    } elseif (isset($value['hasTitleImage']) && !empty($value['hasTitleImage'])) {
                        $imageUrl = $this->oeawStorage->getTitleImageByidentifier($value['hasTitleImage']);
                        //thumbnail service is not supporting svg, so thatsh will be a normal image
                        if (count($imageUrl) < 1) {
                            $arrayObject->offsetSet('imageThumbUrl', HF::createThumbnailUrl($value['hasTitleImage']));
                        } elseif (isset($imageUrl['format']) && strpos($imageUrl['format'], 'svg') === false) {
                            $arrayObject->offsetSet('imageThumbUrl', HF::createThumbnailUrl($value['hasTitleImage']));
                        }
                        
                        if ($imageUrl) {
                            $arrayObject->offsetSet('imageUrl', $imageUrl['uri']);
                        }
                    }
                }
            }

            if (isset($value['contributor']) && !empty($value['contributor'])) {
                $contrArr = explode(',', $value['contributor']);
                $tblArray['contributors'] = $this->oeawFunctions->createContribAuthorData($contrArr);
            }
            if (isset($value['author']) && !empty($value['author'])) {
                $authArr = explode(',', $value['author']);
                $tblArray['authors'] = $this->oeawFunctions->createContribAuthorData($authArr);
            }

            

            if (isset($value['description']) && !empty($value['description'])) {
                $tblArray['description'] = $value['description'];
            }

            if (count($tblArray) == 0) {
                $tblArray['title'] = $value['title'];
            }
            
            $arrayObject->offsetSet('table', $tblArray);

            try {
                $obj = new \Drupal\oeaw\Model\OeawResource($arrayObject, null, $this->siteLang);
                $result[] = $obj;
            } catch (\ErrorException $ex) {
                throw new \ErrorException(t('Error message').':  FrontendController -> OeawResource Exception ');
            }
        }
        
        return $result;
    }
}
