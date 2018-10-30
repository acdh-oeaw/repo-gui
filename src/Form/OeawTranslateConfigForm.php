<?php

/**
 * @file
 * Contains \Drupal\oeaw\Form\OeawTranslateConfigForm.
 */

namespace Drupal\oeaw\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ChangedCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\CssCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\Context\ContextInterface;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SettingsForm.
 *
 * @package Drupal\oeaw\Form
 */
class OeawTranslateConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oeaw_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'oeaw.settings',
    ];
  }
  /**
   * {@inheritdoc}
   */
    public function buildForm(array $form, FormStateInterface $form_state) {
        echo $page = pager_find_page();
    $config = $this->config('oeaw.settings');
    $rawData = $config->getRawData();
    unset($rawData['_core']);
    unset($rawData['case']);
    
    $form['cfg_update'] = array(
        '#type' => 'button',
        '#value' => 'Update config',
        '#prefix' => '<div id="cfg-reload-result"></div>',
        '#ajax' => array(
            'callback' => 'Drupal\oeaw\Form\OeawTranslateConfigForm::update_my_config',
            'event' => 'click',
            'progress' => array(
                'type' => 'throbber',
                'message' => 'Update my config file',
            ),
        ),
    );
    
    $current_path = \Drupal::service('path.current')->getPath();
    
    $form['translation_page'] = array(
        '#type' => 'label',
        '#prefix' => '<a href="/browser'.$current_path.'/translate">Open the Translate site</a>',
    );
    
    foreach($rawData as $k => $v) {
        $form[$k] = array(
            '#type' => 'textarea',
            '#title' => t($k),
            '#default_value' => $v,
            '#required' => FALSE,
        );
    }

    return parent::buildForm($form, $form_state);
  }
  
  
    public function update_my_config(array &$form, FormStateInterface $form_state) {
        \Drupal::service('config.installer')->installDefaultConfig('module', 'oeaw');
        $ajax_response = new AjaxResponse();
        $ajax_response->addCommand(new HtmlCommand('#cfg-reload-result', 'Config and schema reloaded, please refresh the page!'));
        return $ajax_response;
        
    }
    

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
      $values = $form_state->cleanValues()->getValues();
      
      $config = \Drupal::service('config.factory')->getEditable('oeaw.settings');
      
      foreach($values as $k => $v){
        $config->set($k, $v)->save();
      }
      

    parent::submitForm($form, $form_state);
  }
}
