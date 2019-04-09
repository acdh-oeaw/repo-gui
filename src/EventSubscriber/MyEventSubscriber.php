<?php

namespace Drupal\oeaw\EventSubscriber;

use Drupal\User\Entity\User;
use Drupal\Core\DrupalKernel;
// This is the interface we are going to implement.
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
// This class contains the event we want to subscribe to.
use Symfony\Component\HttpKernel\KernelEvents;
// Our event listener method will receive one of these.
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
// We'll use this to perform a redirect if necessary.
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\oeaw\OeawFunctions;
use acdhOeaw\util\RepoConfig as RC;
class MyEventSubscriber implements EventSubscriberInterface
{
    
    /**
     * Check the shibboleth user logins
     *
     * @global type $user
     * @param GetResponseEvent $event
     * @return TrustedRedirectResponse
     */
    public function checkForShibboleth(GetResponseEvent $event)
    {
        if (($event->getRequest()->getPathInfo() == '/user/logout') /*&&  (\Drupal::currentUser()->getUsername() == "shibboleth") */) {
            unset($_SERVER['HTTP_AUTHORIZATION']);
            unset($_SERVER['HTTP_EPPN']);
            $_SERVER['HTTP_AUTHORIZATION'] = "";
            $_SERVER['HTTP_EPPN'] = "";
            foreach (headers_list() as $header) {
                header_remove($header);
            }
            $host = \Drupal::request()->getSchemeAndHttpHost();
            $userid = \Drupal::currentUser()->id();
            \Drupal::service('session_manager')->delete($userid);
            $event->setResponse(new TrustedRedirectResponse($host."/Shibboleth.sso/Logout?return=".$host."/browser/"));
        }
       
        if ($event->getRequest()->getPathInfo() == '/federated_login') {
            global $user;
            //the actual user id, if the user is logged in
            $userid = \Drupal::currentUser()->id();
            //if it is a shibboleth login and there is no user logged in
            if (isset($_SERVER['HTTP_EPPN'])
                    && $_SERVER['HTTP_EPPN'] != "(null)"
                    && $userid == 0
                    && \Drupal::currentUser()->isAnonymous()) {
                
                    $oF = new  \Drupal\oeaw\OeawFunctions();
                    $oF->handleShibbolethUser();
                    
                    $host = \Drupal::request()->getSchemeAndHttpHost();
                    return new TrustedRedirectResponse($host."/browser/federated_login/");
            }
        }
    }


    /**
     * This is the event handler main method
     *
     * @return string
     */
    public static function getSubscribedEvents()
    {
        $events = [];
        $events[KernelEvents::REQUEST][] = array('checkForShibboleth', 300);
        return $events;
    }
    
}
