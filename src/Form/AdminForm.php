<?php

/**
 * @file
 * Contains Drupal\xai\Form\SettingsForm.
 */

namespace Drupal\oeaw\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SettingsForm.
 *
 * @package Drupal\oeaw\Form
 */
class AdminForm extends ConfigFormBase
{

  /**
   * {@inheritdoc}
   */
    protected function getEditableConfigNames()
    {
        return [
      'oeaw.settings',
    ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'admin_settings_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form['intro'] = [
            '#markup' => '<p>' . $this->t('<h2>Please check your config.ini in your module directory.</h2><br/>') . '</p>',
        ];
        
        return parent::buildForm($form, $form_state);
    }

   
   
    
    /**
    * Ajax submit to add new field.
    */
    public function addfieldsubmit(array &$form, FormStateInterface &$form_state)
    {
        $max = $form_state->get('fields_count') + 1;
        $form_state->set('fields_count', $max);
        $form_state->setRebuild(true);
    }
    
    /**
    * Ajax submit to remove field.
    */
    public function removefieldsubmit(array &$form, FormStateInterface &$form_state)
    {
        $max = $form_state->get('fields_count') -1;
        $form_state->set('fields_count', $max);
        $form_state->setRebuild(true);
    }

    /**
    * Ajax callback to add new field.
    */
    public function addfieldCallback(array &$form, FormStateInterface &$form_state)
    {
        return $form['fields']['modules'];
    }
    
    /**
    * {@inheritdoc}
    */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        parent::validateForm($form, $form_state);
    }

    /**
   * {@inheritdoc}
   */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        parent::submitForm($form, $form_state);

     
        $prefNum = $form_state->getValue('prefixes_num');
        
        for ($index = 0; $index < count($prefNum) + 1; $index++) {
            $val = $form_state->getValue('value_'.$index);
            $url = urldecode($form_state->getValue('prefix_'.$index));
            //$prefixes[$val] = urldecode($form_state->getValue('prefix_'.$index));
            $this->config('oeaw.settings')->set($url, $val)->save();
            $this->config('oeaw.settings')->set('prefix_'.$index, $url)->save();
            $this->config('oeaw.settings')->set('value_'.$index, $val)->save();
        }
        
        $this->config('oeaw.settings')->set('prefNum', $prefNum)->save();

        $this->config('oeaw.settings')->set('fedora_url', $form_state->getValue('fedora_url'))->save();
    
        $this->config('oeaw.settings')->set('sparql_endpoint', $form_state->getValue('sparql_endpoint'))->save();
    }
}
