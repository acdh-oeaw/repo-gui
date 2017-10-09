<?php

namespace Drupal\oeaw\Form;


use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ChangedCommand;
use Drupal\Core\Ajax\CssCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class AutoCompleteForm extends FormBase
{
    
    public function getFormId()
    {
        return "autocomplete_form";
    }
    
    /*
    * {@inheritdoc}.
    */
    public function buildForm(array $form, FormStateInterface $form_state) {
        
        $assd = "https://vocabs.acdh.oeaw.ac.at/schema#depositor";
        
        $label = "test";
        
        $form[$label] = array(
                '#type' => 'textfield',
                '#title' => $this->t($label),                                
                '#description' => 'Please enter in a username',                
                '#autocomplete_route_name' => 'oeaw.autocomplete',
                '#autocomplete_route_parameters' => array('prop1' => strtr(base64_encode($assd), '+/=', '-_,'), 'prop2' => strtr(base64_encode($assd), '+/=', '-_,')),
                '#ajax' => [
                    // Function to call when event on form element triggered.
                    'callback' => 'Drupal\oeaw\Form\AutoCompleteForm::usernameValidateCallback',
                    'effect' => 'fade',
                    // Javascript event to trigger Ajax. Currently for: 'onchange'.
                    'event' => 'autocompleteclose',
                    'progress' => array(
                        // Graphic shown to indicate ajax. Options: 'throbber' (default), 'bar'.
                        'type' => 'throbber',
                        // Message to show along progress graphic. Default: 'Please wait...'.
                        'message' => NULL,
                    ),
                    
                  ],
            );
            
            
        
        $form['user_name'] = array(
            '#type' => 'textfield',
            '#title' => 'Username',
            '#description' => 'Please enter in a username',
            '#ajax' => array(
                // Function to call when event on form element triggered.
                'callback' => 'Drupal\oeaw\Form\AutoCompleteForm::usernameValidateCallback',
                // Effect when replacing content. Options: 'none' (default), 'slide', 'fade'.
                'effect' => 'fade',
                // Javascript event to trigger Ajax. Currently for: 'onchange'.
                'event' => 'change',
                'progress' => array(
                    // Graphic shown to indicate ajax. Options: 'throbber' (default), 'bar'.
                    'type' => 'throbber',
                    // Message to show along progress graphic. Default: 'Please wait...'.
                    'message' => NULL,
                ),
            ),
        );
        
         $form['user_name2'] = array(
            '#type' => 'textfield',
            '#title' => 'Username',
            '#description' => 'Please enter in a username',
            
        );

        $form['random_user'] = array(
            '#type' => 'button',
            '#value' => 'Random Username',
            '#ajax' => array(
                'callback' => 'Drupal\oeaw\Form\AutoCompleteForm::randomUsernameCallback',
                'event' => 'click',
                'progress' => array(
                    'type' => 'throbber',
                    'message' => 'Getting Random Username',
                ),
            ),
        );
        
        return $form;
    }
  
    public function submitForm(array &$form, FormStateInterface $form_state) {
        drupal_set_message('Nothing Submitted. Just an Example.');
    }
  
    public function usernameValidateCallback(array &$form, FormStateInterface $form_state) {
       

        // Instantiate an AjaxResponse Object to return.
        $ajax_response = new AjaxResponse();
    
        // Check if Username exists and is not Anonymous User (''). 
        if (user_load_by_name($form_state->getValue('user_name')) && $form_state->getValue('user_name') != false) {
            $text = 'User Found';
            $color = 'green';
        } else {
            $text = 'No User Found';
            $color = 'red';
        }
    
        // Add a command to execute on form, jQuery .html() replaces content between tags.
        // In this case, we replace the desription with wheter the username was found or not.
        $ajax_response->addCommand(new HtmlCommand('#edit-user-name--description', $text));
    
        // CssCommand did not work.
        //$ajax_response->addCommand(new CssCommand('#edit-user-name--description', array('color', $color)));

        // Add a command, InvokeCommand, which allows for custom jQuery commands.
        // In this case, we alter the color of the description.
        $ajax_response->addCommand(new InvokeCommand('#edit-user-name--description', 'css', array('color', $color)));
    
        // Return the AjaxResponse Object.
        return $ajax_response;
    }
    
    public function randomUsernameCallback(array &$form, FormStateInterface $form_state) {
        // Get all User Entities.
        $all_users = entity_load_multiple('user');

        // Remove Anonymous User.
        array_shift($all_users);

        // Pick Random User.
        $random_user = $all_users[array_rand($all_users)];
        // Instantiate an AjaxResponse Object to return.
        $ajax_response = new AjaxResponse();
    
        // ValCommand does not exist, so we can use InvokeCommand.
        $ajax_response->addCommand(new InvokeCommand('#edit-user-name', 'val' , array($random_user->get('name')->getString())));

        // ChangedCommand did not work.
        //$ajax_response->addCommand(new ChangedCommand('#edit-user-name', '#edit-user-name'));

        // We can still invoke the change command on #edit-user-name so it triggers Ajax on that element to validate username.
        $ajax_response->addCommand(new InvokeCommand('#edit-user-name', 'change'));

        // Return the AjaxResponse Object.
        return $ajax_response;
    }
  
}

